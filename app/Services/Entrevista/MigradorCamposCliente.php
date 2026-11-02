<?php

namespace App\Services\Entrevista;

use App\Enums\Entrevista\CanalRespuesta;
use App\Models\CampoCliente;
use App\Models\Documento;
use App\Models\Entrevista\Entrevista;
use App\Models\User;
use App\Services\CatalogoFiscal\CatalogoFiscal;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Pasa los datos del formulario actual (`campos_cliente`) de un cliente a
 * la entrevista del catálogo nuevo (F2.10), con el mapa de 2.3
 * (EquivalenciasCamposCliente). Por cliente, en una transacción:
 * - escribe las respuestas con canal `migracion` vía RespuestaGuardador, sin
 *   pisar lo que el cliente ya respondió en la entrevista;
 * - crea una persona del bloque C por cada dependiente;
 * - pone `documento_codigo` y la entrevista a los archivos que tienen
 *   equivalente;
 * - marca la entrevista como migrada.
 *
 * `campos_cliente` no se toca: queda como histórico (L12). La entrevista
 * queda en curso: el cliente firma el consentimiento y confirma en el
 * portal. El resultado solo cuenta y da códigos; nunca lleva valores.
 */
class MigradorCamposCliente
{
    /**
     * Si una pregunta de sí o no recibe varios datos (E4 desde dos campos,
     * D17 desde varios), basta con uno "si".
     */
    private const GANA_SI = ['si', 'no'];

    /**
     * Parentesco escrito en el formulario actual → opción de C3. Lo que no
     * se reconoce no se adivina: el cliente lo responde en el portal.
     */
    private const PARENTESCOS = [
        'hijastro' => '/\b(hijastr|step(son|daughter|child))/u',
        'hijo_crianza' => '/\b(hij\w* de crianza|crianza|foster|adoptiv)/u',
        'nieto' => '/\b(niet|grand(child|son|daughter))/u',
        'hermano' => '/\b(herman|brother|sister|sibling)/u',
        'padre' => '/\b(padre|madre|pap[aá]|mam[aá]|father|mother|parent)/u',
        'hijo' => '/\b(hij[oa]s?|son|daughter|child)\b/u',
    ];

    public function __construct(
        private readonly EquivalenciasCamposCliente $equivalencias,
        private readonly RespuestaGuardador $guardador,
        private readonly MotorEntrevista $motor,
        private readonly CatalogoFiscal $catalogo,
    ) {}

    /**
     * Los clientes con datos del año en el formulario actual.
     *
     * @return Collection<int, User>
     */
    public function clientesConDatos(int $anio): Collection
    {
        return User::query()
            ->whereIn('id', CampoCliente::query()->where('tax_year', $anio)->select('user_id'))
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array{estado: string, respuestas: int, personas: int, documentos: int, sin_equivalente: int, fuera_de_alcance: int, no_aplicaron: list<string>}
     */
    public function migrar(User $cliente, bool $simular = false): array
    {
        $anio = (int) config('tax.current_tax_year');

        $existente = Entrevista::query()->where('user_id', $cliente->id)->where('tax_year', $anio)->first();

        if ($existente?->migrada_en !== null) {
            return $this->resultado('ya_migrado');
        }

        DB::beginTransaction();

        try {
            $resultado = $this->migrarEnTransaccion($cliente, $anio);
        } catch (Throwable $e) {
            DB::rollBack();

            throw $e;
        }

        $simular ? DB::rollBack() : DB::commit();

        return $resultado;
    }

    /**
     * @return array{estado: string, respuestas: int, personas: int, documentos: int, sin_equivalente: int, fuera_de_alcance: int, no_aplicaron: list<string>}
     */
    private function migrarEnTransaccion(User $cliente, int $anio): array
    {
        $resultado = $this->resultado('migrado');
        $enAlcance = EquivalenciasCamposCliente::formasEnAlcance();
        $campos = CampoCliente::query()->where('user_id', $cliente->id)->where('tax_year', $anio)->orderBy('id')->get();

        $entrevista = $this->guardador->entrevistaDe($cliente, CanalRespuesta::Migracion);
        $pendientes = [];
        $dependientes = [];
        $documentos = [];

        foreach ($campos as $campo) {
            if (! in_array($campo->forma, $enAlcance, true)) {
                $resultado['fuera_de_alcance']++;

                continue;
            }

            $entrada = $this->equivalencias->de($campo->forma, $campo->campo);

            if ($entrada === null || EquivalenciasCamposCliente::destino($entrada) === 'sin_equivalente') {
                $resultado['sin_equivalente']++;

                continue;
            }

            if (isset($entrada['documento'])) {
                $documentos[] = [$campo, $entrada['documento']];
            }

            // Un campo marcado "no aplica" o sin dato no dice nada: se pregunta.
            $valor = $campo->valor_texto;
            $tieneDato = $campo->documento_id !== null || ! $this->vacio($valor);

            if (! $tieneDato) {
                continue;
            }

            foreach ($entrada['responde'] ?? [] as $codigo => $respuesta) {
                $pendientes[] = [$codigo, null, $respuesta];
            }

            if (! empty($entrada['por_persona'])) {
                $dependientes = is_array($valor) ? array_values(array_filter($valor, 'is_array')) : [];
                $dependientes = array_map(fn (array $d) => [$d, $entrada['subcampos']], $dependientes);

                continue;
            }

            if (isset($entrada['subcampos'])) {
                if (! is_array($valor) || array_is_list($valor)) {
                    continue;
                }

                foreach ($entrada['subcampos'] as $subcampo => $parte) {
                    if (isset($parte['pregunta']) && array_key_exists($subcampo, $valor)) {
                        $pendientes[] = [$parte['pregunta'], null, $this->transformar($parte['transforma'], $valor[$subcampo], $anio)];
                    }
                }

                continue;
            }

            if (isset($entrada['pregunta'])) {
                $pendientes[] = [$entrada['pregunta'], null, $this->transformar($entrada['transforma'], $valor, $anio)];
            }
        }

        // Las personas van después de C0 = "si" (lo pone `responde`).
        $porPersona = [];

        foreach ($dependientes as $i => [$datos, $subcampos]) {
            $porPersona[$i] = [];

            foreach ($subcampos as $subcampo => $parte) {
                if (isset($parte['pregunta']) && array_key_exists($subcampo, $datos)) {
                    $porPersona[$i][] = [$parte['pregunta'], $this->transformar($parte['transforma'], $datos[$subcampo], $anio)];
                }
            }
        }

        $yaRespondidas = $entrevista->respuestas()->get()
            ->mapWithKeys(fn ($r) => [MotorEntrevista::clave($r->pregunta_codigo, $r->persona_id) => true]);

        $resultado['respuestas'] += $this->guardarTodo($entrevista, $this->combinar($pendientes), $yaRespondidas, $resultado['no_aplicaron']);

        if ($porPersona !== []) {
            foreach ($porPersona as $respuestas) {
                $persona = $this->guardador->agregarPersona($entrevista);
                $resultado['personas']++;

                $conPersona = array_map(fn (array $r) => [$r[0], $persona->id, $r[1]], $respuestas);
                $resultado['respuestas'] += $this->guardarTodo($entrevista, $this->combinar($conPersona), $yaRespondidas, $resultado['no_aplicaron']);
            }
        }

        foreach ($documentos as [$campo, $codigo]) {
            $resultado['documentos'] += Documento::query()
                ->where('user_id', $cliente->id)
                ->where('tax_year', $anio)
                ->where('forma', $campo->forma)
                ->where('campo', $campo->campo)
                ->whereNull('documento_codigo')
                ->update(['documento_codigo' => $codigo, 'entrevista_id' => $entrevista->id]);
        }

        $entrevista->update(['migrada_en' => now()]);

        $resultado['no_aplicaron'] = array_values(array_unique($resultado['no_aplicaron']));
        sort($resultado['no_aplicaron']);

        return $resultado;
    }

    /**
     * Un dato por pregunta (y persona): en las de sí o no gana "si"; en las
     * de "usted o cónyuge" se juntan; en el resto, el primero.
     *
     * @param  list<array{0: string, 1: int|null, 2: mixed}>  $pendientes
     * @return array<string, array{0: string, 1: int|null, 2: mixed}>
     */
    private function combinar(array $pendientes): array
    {
        $combinadas = [];

        foreach ($pendientes as [$codigo, $personaId, $valor]) {
            if ($valor === null) {
                continue;
            }

            $clave = MotorEntrevista::clave($codigo, $personaId);
            $actual = $combinadas[$clave][2] ?? null;

            $combinadas[$clave] = [$codigo, $personaId, match (true) {
                $actual === null => $valor,
                in_array($actual, self::GANA_SI, true) && in_array($valor, self::GANA_SI, true) => in_array('si', [$actual, $valor], true) ? 'si' : 'no',
                is_array($actual) && is_array($valor) => array_values(array_unique([...$actual, ...$valor])),
                is_array($valor) && $actual === 'no' => $valor,
                default => $actual,
            }];
        }

        return $combinadas;
    }

    /**
     * Guarda en varias vueltas: una respuesta puede depender de otra (B2 de
     * B1, C12 de C3). Lo que al final no se ve o no calza queda en
     * `no_aplicaron` (solo el código).
     *
     * @param  array<string, array{0: string, 1: int|null, 2: mixed}>  $respuestas
     * @param  Collection<string, true>  $yaRespondidas
     * @param  list<string>  $noAplicaron
     */
    private function guardarTodo(Entrevista $entrevista, array $respuestas, Collection $yaRespondidas, array &$noAplicaron): int
    {
        $guardadas = 0;
        $pendientes = array_filter($respuestas, fn (string $clave) => ! $yaRespondidas->has($clave), ARRAY_FILTER_USE_KEY);

        while ($pendientes !== []) {
            $avance = false;
            $situacion = $this->motor->situacion($entrevista->unsetRelation('respuestas')->unsetRelation('personas'));

            foreach ($pendientes as $clave => [$codigo, $personaId, $valor]) {
                if (! $situacion->esVisible($codigo, $personaId)) {
                    continue;
                }

                unset($pendientes[$clave]);
                $avance = true;

                try {
                    $this->guardador->guardar($entrevista, new RespuestaData($codigo, $valor, $personaId), CanalRespuesta::Migracion, null);
                    $guardadas++;
                } catch (RespuestaRechazada) {
                    $noAplicaron[] = $codigo;
                }
            }

            if (! $avance) {
                break;
            }
        }

        foreach ($pendientes as [$codigo]) {
            $noAplicaron[] = $codigo;
        }

        return $guardadas;
    }

    private function transformar(string $transformacion, mixed $valor, int $anio): mixed
    {
        if ($this->vacio($valor)) {
            return null;
        }

        $siNo = $this->siNo($valor);

        return match ($transformacion) {
            'tal_cual' => is_scalar($valor) ? trim((string) $valor) : null,
            'si_no' => $siNo,
            'numero' => is_numeric($valor) ? (int) $valor : null,
            'fecha' => $this->fecha($valor),
            'monto_a_si' => $this->monto($valor) > 0 ? 'si' : null,
            'texto_a_si' => $siNo ?? (is_string($valor) ? 'si' : null),
            'si_no_a_usted', 'texto_a_usted' => match ($siNo ?? (is_string($valor) ? 'si' : null)) {
                'si' => ['usted'],
                'no' => 'no',
                default => null,
            },
            'casado_si_verdadero' => $siNo === 'si' ? 'casado' : null,
            'viudo_si_verdadero' => $siNo === 'si' ? 'viudo' : null,
            'relacion_a_c3' => is_string($valor) ? $this->parentesco($valor) : null,
            'menor_al_limite_c13' => is_numeric($valor) ? ((float) $valor < $this->limiteIngresoPariente($anio) ? 'si' : 'no') : null,
            'parte_de_dato' => is_array($valor)
                ? (implode(', ', array_filter(array_map(fn ($p) => is_scalar($p) ? trim((string) $p) : '', $valor))) ?: null)
                : trim((string) $valor),
            default => null,
        };
    }

    private function siNo(mixed $valor): ?string
    {
        if (is_bool($valor)) {
            return $valor ? 'si' : 'no';
        }

        if (is_int($valor) && in_array($valor, [0, 1], true)) {
            return $valor === 1 ? 'si' : 'no';
        }

        if (! is_string($valor)) {
            return null;
        }

        return match (Str::lower(Str::ascii(trim($valor)))) {
            'si', 'yes', 'true', '1', 's', 'y' => 'si',
            'no', 'false', '0', 'n' => 'no',
            default => null,
        };
    }

    private function monto(mixed $valor): float
    {
        if (is_array($valor)) {
            return array_sum(array_map(fn ($v) => $this->monto($v), $valor));
        }

        if (is_string($valor)) {
            $valor = str_replace([',', '$', ' '], '', $valor);
        }

        return is_numeric($valor) ? (float) $valor : 0.0;
    }

    private function fecha(mixed $valor): ?string
    {
        if (! is_string($valor)) {
            return null;
        }

        foreach (['Y-m-d', 'm/d/Y', 'd/m/Y'] as $formato) {
            try {
                $fecha = CarbonImmutable::createFromFormat("!{$formato}", trim($valor));
            } catch (InvalidFormatException) {
                continue;
            }

            if ($fecha instanceof CarbonImmutable && $fecha->format($formato) === trim($valor)) {
                return $fecha->format('Y-m-d');
            }
        }

        return null;
    }

    private function parentesco(string $texto): ?string
    {
        $texto = Str::lower(trim($texto));

        foreach (self::PARENTESCOS as $opcion => $patron) {
            if (preg_match($patron, $texto) === 1) {
                return $opcion;
            }
        }

        return null;
    }

    private function limiteIngresoPariente(int $anio): float
    {
        return (float) data_get($this->catalogo->parametros($anio)->firstWhere('clave', 'limite_ingreso_bruto_pariente_calificado'), 'valor', 5200);
    }

    private function vacio(mixed $valor): bool
    {
        return $valor === null || $valor === '' || $valor === [];
    }

    /**
     * @return array{estado: string, respuestas: int, personas: int, documentos: int, sin_equivalente: int, fuera_de_alcance: int, no_aplicaron: list<string>}
     */
    private function resultado(string $estado): array
    {
        return ['estado' => $estado, 'respuestas' => 0, 'personas' => 0, 'documentos' => 0, 'sin_equivalente' => 0, 'fuera_de_alcance' => 0, 'no_aplicaron' => []];
    }
}
