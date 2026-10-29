<?php

namespace App\Services\CatalogoFiscal;

use App\Enums\CatalogoFiscal\ObjetivoActivacion;
use App\Enums\CatalogoFiscal\OrigenLinea;
use App\Models\CatalogoFiscal\CatalogoActivacion;
use App\Models\CatalogoFiscal\CatalogoCasilla;
use App\Models\CatalogoFiscal\CatalogoDocumento;
use App\Models\CatalogoFiscal\CatalogoFormulario;
use App\Models\CatalogoFiscal\CatalogoLinea;
use App\Models\CatalogoFiscal\CatalogoPregunta;
use App\Models\CatalogoFiscal\CatalogoRegla;
use App\Rules\CondicionCatalogoValida;
use Illuminate\Support\Facades\Validator;

/**
 * Revisa la integridad del catálogo fiscal de un año (F1.3): que el 1040
 * esté y sea el único "siempre", que toda referencia por código apunte a
 * algo activo del mismo año y que toda pieza principal tenga su fuente.
 * Mira solo lo activo.
 */
class CatalogoFiscalVerificador
{
    /**
     * @return list<string> Problemas encontrados; vacío si está bien.
     */
    public function verificar(int $anio): array
    {
        $errores = [];

        $preguntas = CatalogoPregunta::query()->delAnio($anio)->activos()->get();
        $documentos = CatalogoDocumento::query()->delAnio($anio)->activos()->get();
        $formularios = CatalogoFormulario::query()->delAnio($anio)->activos()->get();
        $reglas = CatalogoRegla::query()->delAnio($anio)->activos()->get();

        if ($preguntas->isEmpty() && $documentos->isEmpty() && $formularios->isEmpty()) {
            return ["{$anio} no tiene catálogo cargado."];
        }

        $codigos = [
            ObjetivoActivacion::Pregunta->value => $preguntas->pluck('codigo')->all(),
            ObjetivoActivacion::Documento->value => $documentos->pluck('codigo')->all(),
            ObjetivoActivacion::Formulario->value => $formularios->pluck('codigo')->all(),
        ];

        // El 1040 siempre presente (9.5) y es el único "siempre".
        $siempre = $formularios->where('siempre', true)->pluck('codigo')->all();
        if ($siempre !== ['1040']) {
            $errores[] = 'El 1040 debe existir y ser el único formulario con "siempre" (hoy: '.($siempre === [] ? 'ninguno' : implode(', ', $siempre)).').';
        }

        $activaciones = CatalogoActivacion::query()->delAnio($anio)->activos()->with('pregunta')->get();
        foreach ($activaciones as $activacion) {
            if (! in_array($activacion->objetivo_codigo, $codigos[$activacion->objetivo_tipo->value], true)) {
                $errores[] = "La pregunta {$activacion->pregunta->codigo} activa {$activacion->objetivo_tipo->value} \"{$activacion->objetivo_codigo}\", que no existe en {$anio}.";
            }
        }

        foreach ($preguntas->whereNotNull('condicion') as $pregunta) {
            $validacion = Validator::make(['condicion' => $pregunta->condicion], ['condicion' => [new CondicionCatalogoValida($anio)]]);

            if ($validacion->fails()) {
                $errores[] = "Condición de {$pregunta->codigo}: ".$validacion->errors()->first('condicion');
            }
        }

        $casillas = CatalogoCasilla::query()->delAnio($anio)->activos()->with('documento')->get()
            ->map(fn (CatalogoCasilla $c) => "{$c->documento->codigo}:{$c->casilla}")->all();
        $lineas = CatalogoLinea::query()->delAnio($anio)->activos()->with('formulario')->get();
        $codigosLinea = $lineas->map(fn (CatalogoLinea $l) => "{$l->formulario->codigo}:{$l->linea}")->all();

        foreach ($lineas as $linea) {
            $donde = "{$linea->formulario->codigo} línea {$linea->linea}";
            $claves = array_filter(array_map('trim', explode(';', (string) $linea->origen_clave)));

            if ($linea->origen_tipo !== OrigenLinea::Calculo && $claves === []) {
                $errores[] = "{$donde}: origen {$linea->origen_tipo->value} sin clave.";
            }

            foreach ($claves as $clave) {
                $existe = match ($linea->origen_tipo) {
                    OrigenLinea::Casilla => in_array($clave, $casillas, true),
                    OrigenLinea::Linea => in_array($clave, $codigosLinea, true),
                    OrigenLinea::Respuesta => in_array($clave, $codigos[ObjetivoActivacion::Pregunta->value], true),
                    OrigenLinea::Calculo => true,
                };

                if (! $existe) {
                    $errores[] = "{$donde}: el origen \"{$clave}\" no existe en {$anio}.";
                }
            }
        }

        foreach (['pregunta' => $preguntas, 'documento' => $documentos, 'formulario' => $formularios, 'regla' => $reglas] as $tipo => $piezas) {
            foreach ($piezas as $pieza) {
                if (blank($pieza->fuente_texto) && blank($pieza->fuente_url)) {
                    $errores[] = "{$tipo} {$pieza->codigo}: sin fuente del IRS.";
                }
            }
        }

        return $errores;
    }

    /**
     * @return array<string, int>
     */
    public function conteos(int $anio): array
    {
        return [
            'preguntas' => CatalogoPregunta::query()->delAnio($anio)->activos()->count(),
            'documentos' => CatalogoDocumento::query()->delAnio($anio)->activos()->count(),
            'casillas' => CatalogoCasilla::query()->delAnio($anio)->activos()->count(),
            'formularios' => CatalogoFormulario::query()->delAnio($anio)->activos()->count(),
            'lineas' => CatalogoLinea::query()->delAnio($anio)->activos()->count(),
            'activaciones' => CatalogoActivacion::query()->delAnio($anio)->activos()->count(),
            'reglas' => CatalogoRegla::query()->delAnio($anio)->activos()->count(),
        ];
    }
}
