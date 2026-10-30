<?php

namespace App\Services\Entrevista;

use App\Enums\TaxForm;
use App\Models\CampoCatalogo;
use App\Models\CampoCliente;
use App\Services\CatalogoFiscal\CatalogoFiscal;
use Illuminate\Support\Collection;

/**
 * Equivalencias del formulario actual (`catalogo_campos` / `campos_cliente`)
 * con el catálogo fiscal nuevo (F2.3). El contenido vive en
 * database/data/catalogo-fiscal/equivalencias-campos-cliente.php (formato y
 * transformaciones documentados ahí). Lo usan el reporte de
 * `catalogo-fiscal:equivalencias` y la migración de 2.10.
 *
 * Nunca lee valores de `campos_cliente`: el reporte solo cuenta filas.
 */
class EquivalenciasCamposCliente
{
    public const TRANSFORMACIONES = [
        'tal_cual', 'si_no', 'numero', 'fecha', 'monto_a_si', 'texto_a_si', 'si_no_a_usted',
        'texto_a_usted', 'casado_si_verdadero', 'viudo_si_verdadero', 'relacion_a_c3',
        'menor_al_limite_c13', 'parte_de_dato',
    ];

    private const CLAVES_ENTRADA = ['pregunta', 'transforma', 'documento', 'responde', 'subcampos', 'por_persona', 'sin_equivalente', 'nota'];

    /** @var array<string, array<string, mixed>> */
    private readonly array $mapa;

    public function __construct(?string $ruta = null)
    {
        $this->mapa = require $ruta ?? database_path('data/catalogo-fiscal/equivalencias-campos-cliente.php');
    }

    /**
     * Formas del formulario actual que pasan a la entrevista: las de persona
     * natural y las dos pseudoformas. Empresas y 1040-NR quedan fuera (F0.6).
     *
     * @return list<string>
     */
    public static function formasEnAlcance(): array
    {
        return [...TaxForm::valoresEnAlcance(), ...CampoCatalogo::pseudoFormas()];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function todas(): array
    {
        return $this->mapa;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function de(string $forma, string $campo): ?array
    {
        return $this->mapa["{$forma}.{$campo}"] ?? null;
    }

    /**
     * Qué pasa con un campo, para el reporte: `pregunta`, `documento`,
     * `persona`, `subcampos` o `sin_equivalente`.
     *
     * @param  array<string, mixed>  $entrada
     */
    public static function destino(array $entrada): string
    {
        return match (true) {
            ! empty($entrada['por_persona']) => 'persona',
            isset($entrada['documento']) => 'documento',
            isset($entrada['pregunta']) || isset($entrada['responde']) => 'pregunta',
            isset($entrada['subcampos']) => 'subcampos',
            default => 'sin_equivalente',
        };
    }

    /**
     * Problemas del archivo frente a los dos catálogos: claves en alcance
     * que faltan o sobran, subcampos que no calzan, códigos que no existen
     * en el catálogo nuevo y transformaciones desconocidas.
     *
     * @return list<string>
     */
    public function problemas(int $anioViejo, int $anioNuevo, CatalogoFiscal $catalogo): array
    {
        $problemas = [];
        $viejos = CampoCatalogo::query()
            ->where('tax_year', $anioViejo)
            ->whereIn('forma', self::formasEnAlcance())
            ->get(['forma', 'clave', 'subcampos'])
            ->keyBy(fn (CampoCatalogo $c) => "{$c->forma}.{$c->clave}");

        foreach ($viejos->keys()->diff(array_keys($this->mapa)) as $clave) {
            $problemas[] = "Falta en el archivo: {$clave}.";
        }

        foreach (collect(array_keys($this->mapa))->diff($viejos->keys()) as $clave) {
            $problemas[] = "No existe en catalogo_campos {$anioViejo}: {$clave}.";
        }

        $preguntas = $catalogo->preguntas($anioNuevo)->keyBy('codigo');
        $documentos = $catalogo->documentos($anioNuevo)->pluck('codigo')->all();

        foreach ($this->mapa as $clave => $entrada) {
            $subcampos = $viejos->get($clave)?->subcampos;

            if (isset($entrada['subcampos']) && $subcampos !== null) {
                $distintos = array_merge(array_diff($subcampos, array_keys($entrada['subcampos'])), array_diff(array_keys($entrada['subcampos']), $subcampos));

                if ($distintos !== []) {
                    $problemas[] = "{$clave}: los subcampos no calzan con catalogo_campos (".implode(', ', $distintos).').';
                }
            }

            foreach ([$entrada, ...array_values($entrada['subcampos'] ?? [])] as $parte) {
                array_push($problemas, ...$this->problemasDeEntrada($clave, $parte, $preguntas, $documentos));
            }
        }

        return $problemas;
    }

    /**
     * Filas de `campos_cliente` del año por forma y campo (solo cuenta; no
     * lee ni descifra valores).
     *
     * @return Collection<int, object{forma: string, campo: string, filas: int, clientes: int}>
     */
    public function conteos(int $anioViejo): Collection
    {
        return CampoCliente::query()
            ->where('tax_year', $anioViejo)
            ->selectRaw('forma, campo, count(*) as filas, count(distinct user_id) as clientes')
            ->groupBy('forma', 'campo')
            ->orderBy('forma')
            ->orderBy('campo')
            ->toBase()
            ->get();
    }

    /**
     * @param  array<string, mixed>  $entrada
     * @param  Collection<string, array<string, mixed>>  $preguntas
     * @param  list<string>  $documentos
     * @return list<string>
     */
    private function problemasDeEntrada(string $clave, array $entrada, Collection $preguntas, array $documentos): array
    {
        $problemas = [];

        if (($sobran = array_diff(array_keys($entrada), self::CLAVES_ENTRADA)) !== []) {
            $problemas[] = "{$clave}: claves desconocidas (".implode(', ', $sobran).').';
        }

        if (! isset($entrada['pregunta']) && ! isset($entrada['documento']) && ! isset($entrada['responde']) && ! isset($entrada['subcampos']) && ! isset($entrada['sin_equivalente'])) {
            $problemas[] = "{$clave}: no dice a dónde va ni por qué no tiene equivalente.";
        }

        if (isset($entrada['pregunta'])) {
            if (! $preguntas->has($entrada['pregunta'])) {
                $problemas[] = "{$clave}: la pregunta {$entrada['pregunta']} no existe en el catálogo nuevo.";
            }

            if (! in_array($entrada['transforma'] ?? null, self::TRANSFORMACIONES, true)) {
                $problemas[] = "{$clave}: transformación desconocida para {$entrada['pregunta']}.";
            }
        }

        if (isset($entrada['documento']) && ! in_array($entrada['documento'], $documentos, true)) {
            $problemas[] = "{$clave}: el documento {$entrada['documento']} no existe en el catálogo nuevo.";
        }

        foreach ($entrada['responde'] ?? [] as $codigo => $valor) {
            $pregunta = $preguntas->get($codigo);

            if ($pregunta === null) {
                $problemas[] = "{$clave}: la pregunta {$codigo} no existe en el catálogo nuevo.";

                continue;
            }

            try {
                ValorRespuesta::desde($pregunta, $valor);
            } catch (RespuestaRechazada $e) {
                $problemas[] = "{$clave}: {$codigo} → {$e->getMessage()}";
            }
        }

        return $problemas;
    }
}
