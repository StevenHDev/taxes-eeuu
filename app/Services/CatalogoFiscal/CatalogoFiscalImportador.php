<?php

namespace App\Services\CatalogoFiscal;

use App\Enums\CatalogoFiscal\EstadoAnio;
use App\Enums\CatalogoFiscal\OrigenCambio;
use App\Models\CatalogoFiscal\CatalogoActivacion;
use App\Models\CatalogoFiscal\CatalogoAnio;
use App\Models\CatalogoFiscal\CatalogoCambio;
use App\Models\CatalogoFiscal\CatalogoCasilla;
use App\Models\CatalogoFiscal\CatalogoDocumento;
use App\Models\CatalogoFiscal\CatalogoFormulario;
use App\Models\CatalogoFiscal\CatalogoLinea;
use App\Models\CatalogoFiscal\CatalogoPregunta;
use App\Models\CatalogoFiscal\CatalogoRegla;
use App\Models\ParametroFiscal;
use App\Support\ParametrosFiscales;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Carga el catálogo fiscal de un año desde los archivos de
 * `database/data/catalogo-fiscal/{anio}/` (F1.3; formato en el README de esa
 * carpeta).
 *
 * - Idempotente: hace upsert por código; correrla dos veces no duplica.
 * - No pisa lo que un administrador editó desde la pantalla (hay un cambio
 *   con origen "pantalla" en `catalogo_cambios`), salvo con `$forzar`.
 * - Nunca desactiva ni borra lo que no está en los archivos.
 * - `$dryRun` hace todo dentro de una transacción y la deshace.
 * - Un año copiado de otro (2026 de 2025) se carga con los archivos del año
 *   de origen y su `ajustes.php` aplicado en memoria: hereda lo nuevo del
 *   origen (F2.2) y una segunda carga no deja cambios en el historial.
 */
class CatalogoFiscalImportador
{
    private const CLAVES = [
        'preguntas' => ['codigo', 'bloque', 'pantalla', 'orden', 'texto_es', 'texto_en', 'ayuda_es', 'ayuda_en', 'tipo_respuesta', 'opciones', 'nivel', 'por_persona', 'sensible', 'condicion', 'efecto_es', 'fuente_texto', 'fuente_url', 'activa'],
        'activa' => ['respuesta', 'objetivo', 'nota'],
        'documentos' => ['codigo', 'grupo', 'nombre_oficial', 'nombre_sencillo_es', 'nombre_sencillo_en', 'emisor_es', 'emisor_en', 'fecha_llegada', 'si_no_llega_es', 'si_no_llega_en', 'formatos', 'uno_por', 'orden', 'fuente_texto', 'fuente_url', 'casillas'],
        'casillas' => ['casilla', 'descripcion', 'tipo_dato', 'clave', 'orden'],
        'formularios' => ['codigo', 'nombre', 'para_que', 'siempre', 'ficha', 'orden', 'fuente_texto', 'fuente_url', 'lineas'],
        'lineas' => ['linea', 'descripcion', 'origen_tipo', 'origen_clave', 'campo_pdf', 'orden'],
        'reglas' => ['codigo', 'descripcion', 'severidad', 'implementada', 'orden', 'fuente_texto', 'fuente_url'],
        'parametros' => ['categoria', 'clave', 'valor', 'grupo', 'tipo', 'nombre_es', 'condicion_es', 'formulario_linea', 'orden', 'fuente_texto', 'fuente_url'],
    ];

    private readonly string $base;

    private ResultadoCarga $resultado;

    private bool $forzar = false;

    public function __construct(?string $base = null)
    {
        $this->base = $base ?? database_path('data/catalogo-fiscal');
    }

    public function cargar(int $anio, bool $dryRun = false, bool $forzar = false): ResultadoCarga
    {
        $this->resultado = new ResultadoCarga;
        $this->forzar = $forzar;

        if (! is_dir("{$this->base}/{$anio}") && $this->anioConArchivos($anio) === null) {
            throw new InvalidArgumentException("No hay archivos del catálogo fiscal para {$anio} en {$this->base}/{$anio}.");
        }

        DB::beginTransaction();

        try {
            CatalogoAnio::query()->firstOrCreate(['tax_year' => $anio], ['estado' => EstadoAnio::Borrador]);

            // Un año copiado de otro (2026 de 2025) no tiene archivos propios:
            // usa los del año de origen con sus ajustes aplicados en memoria,
            // así hereda lo nuevo de ese año y una segunda carga no cambia nada.
            $origen = $this->anioConArchivos($anio);
            $ajustes = $this->leerAjustes($anio);
            $derivado = $origen !== null && $origen !== $anio;
            $parametrosDelArchivo = [];

            if ($derivado) {
                $this->renombrarCasillas($anio, $ajustes['casillas'] ?? []);
            }

            foreach ($this->archivo($origen, 'preguntas', 'preguntas') as $fila) {
                $this->cargarPregunta($anio, $derivado ? $this->ajustarPregunta($fila, $ajustes) : $fila);
            }

            foreach ($this->archivo($origen, 'documentos', 'documentos') as $fila) {
                $this->cargarDocumento($anio, $derivado ? $this->ajustarDocumento($fila, $ajustes) : $fila);
            }

            foreach ($this->archivo($origen, 'formularios', 'formularios') as $fila) {
                $this->cargarFormulario($anio, $derivado ? $this->ajustarFormulario($fila, $ajustes) : $fila);
            }

            foreach ($this->archivo($origen, 'reglas', 'reglas') as $fila) {
                $this->guardar(
                    CatalogoRegla::query()->firstOrNew(['tax_year' => $anio, 'codigo' => $fila['codigo']]),
                    $fila,
                    'regla',
                    $fila['codigo'],
                );
            }

            foreach ($this->archivo($origen, 'parametros', 'parametros') as $fila) {
                $codigo = "{$fila['categoria']}.{$fila['clave']}";

                if ($derivado && array_key_exists($codigo, $ajustes['parametros'] ?? [])) {
                    $fila['valor'] = $ajustes['parametros'][$codigo];
                }

                $parametrosDelArchivo[] = $codigo;

                $this->guardar(
                    ParametroFiscal::query()->firstOrNew(['tax_year' => $anio, 'categoria' => $fila['categoria'], 'clave' => $fila['clave']]),
                    $fila,
                    'parametro',
                    $codigo,
                );
            }

            if ($derivado) {
                // Lo único que no viene en los archivos: los parámetros sembrados
                // (ParametrosFiscalesSeeder) que el año nuevo trae pendientes.
                $this->aplicarAjustesDeParametros($anio, array_diff_key($ajustes['parametros'] ?? [], array_flip($parametrosDelArchivo)));
            } else {
                $this->aplicarAjustes($anio);
            }

            $dryRun ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            throw $e;
        }

        CatalogoFiscal::invalidar($anio);
        ParametrosFiscales::invalidate();

        return $this->resultado;
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private function cargarPregunta(int $anio, array $fila): void
    {
        $pregunta = CatalogoPregunta::query()->firstOrNew(['tax_year' => $anio, 'codigo' => $fila['codigo']]);
        $this->guardar($pregunta, $fila, 'pregunta', $fila['codigo']);

        foreach ($fila['activa'] ?? [] as $activa) {
            $this->validarClaves('activa', $activa, "pregunta {$fila['codigo']}");
            [$tipo, $codigo] = $this->objetivo($activa['objetivo'], $fila['codigo']);

            $this->guardar(
                CatalogoActivacion::query()->firstOrNew([
                    'pregunta_id' => $pregunta->id,
                    'objetivo_tipo' => $tipo,
                    'objetivo_codigo' => $codigo,
                ]),
                ['respuesta' => $activa['respuesta'], 'nota' => $activa['nota'] ?? null],
                'activacion',
                "{$fila['codigo']} → {$activa['objetivo']}",
                $anio,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private function cargarDocumento(int $anio, array $fila): void
    {
        $documento = CatalogoDocumento::query()->firstOrNew(['tax_year' => $anio, 'codigo' => $fila['codigo']]);
        $this->guardar($documento, $fila, 'documento', $fila['codigo']);

        foreach ($fila['casillas'] ?? [] as $i => $casilla) {
            $this->validarClaves('casillas', $casilla, "documento {$fila['codigo']}");

            $this->guardar(
                CatalogoCasilla::query()->firstOrNew(['documento_id' => $documento->id, 'casilla' => $casilla['casilla']]),
                ['orden' => $i + 1, ...$casilla],
                'casilla',
                "{$fila['codigo']}:{$casilla['casilla']}",
                $anio,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private function cargarFormulario(int $anio, array $fila): void
    {
        $formulario = CatalogoFormulario::query()->firstOrNew(['tax_year' => $anio, 'codigo' => $fila['codigo']]);
        $this->guardar($formulario, $fila, 'formulario', $fila['codigo']);

        foreach ($fila['lineas'] ?? [] as $i => $linea) {
            $this->validarClaves('lineas', $linea, "formulario {$fila['codigo']}");

            $this->guardar(
                CatalogoLinea::query()->firstOrNew(['formulario_id' => $formulario->id, 'linea' => $linea['linea']]),
                ['orden' => $i + 1, ...$linea],
                'linea',
                "{$fila['codigo']}:{$linea['linea']}",
                $anio,
            );
        }
    }

    /**
     * `ajustes.php`: cambios puntuales sobre lo que ya existe en el año
     * (normalmente, una copia del año anterior). Lo que no se encuentra se
     * informa como aviso, no se inventa.
     */
    private function aplicarAjustes(int $anio): void
    {
        $ruta = "{$this->base}/{$anio}/ajustes.php";

        if (! is_file($ruta)) {
            return;
        }

        $ajustes = require $ruta;

        $this->aplicarAjustesDeParametros($anio, $ajustes['parametros'] ?? []);

        foreach ($ajustes['casillas'] ?? [] as $codigoDocumento => $cambios) {
            foreach ($cambios as $casillaVieja => $atributos) {
                $casilla = CatalogoCasilla::query()
                    ->where('tax_year', $anio)
                    ->where('casilla', (string) $casillaVieja)
                    ->whereHas('documento', fn ($q) => $q->where('codigo', $codigoDocumento))
                    ->first();

                $casilla === null
                    ? $this->resultado->avisos[] = "Ajuste: la casilla {$codigoDocumento}:{$casillaVieja} no existe en {$anio}."
                    : $this->guardar($casilla, $atributos, 'casilla', "{$codigoDocumento}:{$casillaVieja}");
            }
        }

        foreach ($ajustes['lineas'] ?? [] as $codigo => $atributos) {
            [$codigoFormulario, $numero] = explode(':', $codigo, 2);
            $linea = CatalogoLinea::query()
                ->where('tax_year', $anio)
                ->where('linea', $numero)
                ->whereHas('formulario', fn ($q) => $q->where('codigo', $codigoFormulario))
                ->first();

            $linea === null
                ? $this->resultado->avisos[] = "Ajuste: la línea {$codigo} no existe en {$anio}."
                : $this->guardar($linea, $atributos, 'linea', $codigo);
        }

        foreach ($ajustes['preguntas'] ?? [] as $codigo => $atributos) {
            $pregunta = CatalogoPregunta::query()->where(['tax_year' => $anio, 'codigo' => $codigo])->first();

            $pregunta === null
                ? $this->resultado->avisos[] = "Ajuste: la pregunta {$codigo} no existe en {$anio}."
                : $this->guardar($pregunta, $atributos, 'pregunta', $codigo);
        }
    }

    /**
     * @param  array<string, mixed>  $parametros  categoria.clave => valor
     */
    private function aplicarAjustesDeParametros(int $anio, array $parametros): void
    {
        foreach ($parametros as $codigo => $valor) {
            [$categoria, $clave] = explode('.', $codigo, 2);
            $parametro = ParametroFiscal::query()->where(['tax_year' => $anio, 'categoria' => $categoria, 'clave' => $clave])->first();

            $parametro === null
                ? $this->resultado->avisos[] = "Ajuste: el parámetro {$codigo} no existe en {$anio}."
                : $this->guardar($parametro, ['valor' => $valor], 'parametro', $codigo);
        }
    }

    /**
     * El año cuyos archivos se usan para cargar `$anio`: el mismo si tiene
     * archivos propios; si no, el año del que se copió (CatalogoAnio::copiado_de).
     */
    private function anioConArchivos(int $anio): ?int
    {
        $tieneArchivos = fn (int $a) => array_diff(glob("{$this->base}/{$a}/*.php") ?: [], ["{$this->base}/{$a}/ajustes.php"]) !== [];

        if ($tieneArchivos($anio)) {
            return $anio;
        }

        $origen = CatalogoAnio::query()->where('tax_year', $anio)->value('copiado_de');

        return $origen !== null && $tieneArchivos((int) $origen) ? (int) $origen : null;
    }

    /**
     * Las casillas que el año renombra (1099-NEC 1 → 1a) se renombran en la
     * copia antes de cargar, para que la carga actualice esa fila y no cree
     * una nueva al lado de la vieja.
     *
     * @param  array<string, array<string, array<string, mixed>>>  $casillas
     */
    private function renombrarCasillas(int $anio, array $casillas): void
    {
        foreach ($casillas as $codigoDocumento => $cambios) {
            foreach ($cambios as $casillaVieja => $atributos) {
                $nueva = $atributos['casilla'] ?? null;

                if ($nueva === null || (string) $nueva === (string) $casillaVieja) {
                    continue;
                }

                $delDocumento = fn () => CatalogoCasilla::query()
                    ->where('tax_year', $anio)
                    ->whereHas('documento', fn ($q) => $q->where('codigo', $codigoDocumento));

                $vieja = $delDocumento()->where('casilla', (string) $casillaVieja)->first();

                if ($vieja !== null && ! $delDocumento()->where('casilla', (string) $nueva)->exists()) {
                    $this->guardar($vieja, ['casilla' => (string) $nueva], 'casilla', "{$codigoDocumento}:{$casillaVieja}");
                }
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function leerAjustes(int $anio): array
    {
        $ruta = "{$this->base}/{$anio}/ajustes.php";

        return is_file($ruta) ? require $ruta : [];
    }

    /**
     * @param  array<string, mixed>  $fila
     * @param  array<string, mixed>  $ajustes
     * @return array<string, mixed>
     */
    private function ajustarPregunta(array $fila, array $ajustes): array
    {
        return [...$fila, ...($ajustes['preguntas'][$fila['codigo']] ?? [])];
    }

    /**
     * @param  array<string, mixed>  $fila
     * @param  array<string, mixed>  $ajustes
     * @return array<string, mixed>
     */
    private function ajustarDocumento(array $fila, array $ajustes): array
    {
        $cambios = $ajustes['casillas'][$fila['codigo']] ?? [];

        $fila['casillas'] = array_map(
            fn (array $c) => [...$c, ...($cambios[$c['casilla']] ?? [])],
            $fila['casillas'] ?? [],
        );

        return $fila;
    }

    /**
     * @param  array<string, mixed>  $fila
     * @param  array<string, mixed>  $ajustes
     * @return array<string, mixed>
     */
    private function ajustarFormulario(array $fila, array $ajustes): array
    {
        $fila['lineas'] = array_map(
            fn (array $l) => [...$l, ...($ajustes['lineas']["{$fila['codigo']}:{$l['linea']}"] ?? [])],
            $fila['lineas'] ?? [],
        );

        return $fila;
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function guardar(Model $modelo, array $atributos, string $pieza, string $etiqueta, ?int $anio = null): void
    {
        if ($modelo->exists && ! $this->forzar && $this->editadoEnPantalla($modelo)) {
            $this->resultado->saltados[] = "{$pieza} {$etiqueta}: editado desde la pantalla (usa --forzar para reemplazarlo)";

            return;
        }

        $modelo->fill(array_diff_key($atributos, array_flip(['activa', 'casillas', 'lineas'])));

        if ($anio !== null) {
            $modelo->setAttribute('tax_year', $anio);
        }

        if (! $modelo->exists) {
            $modelo->save();
            $this->resultado->contar('creados', $pieza);
        } elseif ($modelo->isDirty()) {
            $modelo->save();
            $this->resultado->contar('actualizados', $pieza);
        } else {
            $this->resultado->contar('sinCambios', $pieza);
        }
    }

    private function editadoEnPantalla(Model $modelo): bool
    {
        return CatalogoCambio::query()
            ->where('auditable_type', $modelo::class)
            ->where('auditable_id', $modelo->getKey())
            ->where('origen', OrigenCambio::Pantalla)
            ->exists();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function archivo(?int $anio, string $nombre, string $tipo): array
    {
        $ruta = "{$this->base}/{$anio}/{$nombre}.php";

        if ($anio === null || ! is_file($ruta)) {
            return [];
        }

        $filas = require $ruta;

        foreach ($filas as $i => $fila) {
            $this->validarClaves($tipo, $fila, "{$nombre}.php fila ".($i + 1));
        }

        return $filas;
    }

    /**
     * Los modelos descartan en silencio los atributos que no conocen: una
     * clave mal escrita en un archivo de datos se perdería sin aviso.
     *
     * @param  array<string, mixed>  $fila
     */
    private function validarClaves(string $tipo, array $fila, string $donde): void
    {
        $desconocidas = array_diff(array_keys($fila), self::CLAVES[$tipo]);

        if ($desconocidas !== []) {
            throw new InvalidArgumentException("Claves desconocidas en {$donde}: ".implode(', ', $desconocidas).'.');
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function objetivo(string $objetivo, string $pregunta): array
    {
        $partes = explode(':', $objetivo, 2);

        if (count($partes) !== 2 || ! in_array($partes[0], ['pregunta', 'documento', 'formulario'], true)) {
            throw new InvalidArgumentException("Objetivo inválido \"{$objetivo}\" en la pregunta {$pregunta}.");
        }

        return [$partes[0], $partes[1]];
    }
}
