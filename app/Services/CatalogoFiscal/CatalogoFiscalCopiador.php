<?php

namespace App\Services\CatalogoFiscal;

use App\Enums\CatalogoFiscal\EstadoAnio;
use App\Models\CatalogoFiscal\CatalogoAnio;
use App\Models\CatalogoFiscal\CatalogoDocumento;
use App\Models\CatalogoFiscal\CatalogoFormulario;
use App\Models\CatalogoFiscal\CatalogoPregunta;
use App\Models\CatalogoFiscal\CatalogoRegla;
use App\Models\ParametroFiscal;
use App\Support\ParametrosFiscales;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Copia el catálogo fiscal de un año a otro (F1.3), como punto de partida.
 *
 * - Copia solo lo activo, con sus casillas, líneas y activaciones.
 * - Todo queda "sin validar" en el año nuevo: la fuente se conserva, pero
 *   hay que volver a compararla con la publicación del IRS de ese año.
 * - Los parámetros se copian **pendientes** (sin cifra): nunca se arrastra
 *   un monto de un año a otro (decisión de Steven, 29 oct.). Los que el
 *   documento madre ya trae se ponen con `ajustes.php` del año nuevo.
 * - No copia sobre un año que ya tiene contenido.
 */
class CatalogoFiscalCopiador
{
    private const SIN_VALIDAR = ['validado_por' => null, 'validado_en' => null];

    /**
     * @return array<string, int>
     */
    public function copiar(int $desde, int $hasta): array
    {
        if ($desde === $hasta) {
            throw new InvalidArgumentException('El año de origen y el de destino son el mismo.');
        }

        if (! CatalogoPregunta::query()->delAnio($desde)->exists()) {
            throw new InvalidArgumentException("{$desde} no tiene catálogo para copiar.");
        }

        foreach ([CatalogoPregunta::class, CatalogoDocumento::class, CatalogoFormulario::class, CatalogoRegla::class] as $pieza) {
            if ($pieza::query()->delAnio($hasta)->exists()) {
                throw new InvalidArgumentException("{$hasta} ya tiene catálogo: no se copia encima.");
            }
        }

        $conteo = ['preguntas' => 0, 'activaciones' => 0, 'documentos' => 0, 'casillas' => 0, 'formularios' => 0, 'lineas' => 0, 'reglas' => 0, 'parametros' => 0];

        DB::transaction(function () use ($desde, $hasta, &$conteo) {
            CatalogoAnio::query()->updateOrCreate(
                ['tax_year' => $hasta],
                ['estado' => EstadoAnio::Borrador, 'copiado_de' => $desde],
            );

            $preguntas = CatalogoPregunta::query()->delAnio($desde)->activos()->with(['activaciones' => fn ($q) => $q->activos()])->get();
            foreach ($preguntas as $pregunta) {
                $nueva = $this->replicar($pregunta, $hasta, self::SIN_VALIDAR);
                $conteo['preguntas']++;

                foreach ($pregunta->activaciones as $activacion) {
                    $this->replicar($activacion, $hasta, ['pregunta_id' => $nueva->id]);
                    $conteo['activaciones']++;
                }
            }

            $documentos = CatalogoDocumento::query()->delAnio($desde)->activos()->with(['casillas' => fn ($q) => $q->activos()])->get();
            foreach ($documentos as $documento) {
                $nuevo = $this->replicar($documento, $hasta, self::SIN_VALIDAR);
                $conteo['documentos']++;

                foreach ($documento->casillas as $casilla) {
                    $this->replicar($casilla, $hasta, ['documento_id' => $nuevo->id]);
                    $conteo['casillas']++;
                }
            }

            $formularios = CatalogoFormulario::query()->delAnio($desde)->activos()->with(['lineas' => fn ($q) => $q->activos()])->get();
            foreach ($formularios as $formulario) {
                $nuevo = $this->replicar($formulario, $hasta, self::SIN_VALIDAR);
                $conteo['formularios']++;

                foreach ($formulario->lineas as $linea) {
                    $this->replicar($linea, $hasta, ['formulario_id' => $nuevo->id]);
                    $conteo['lineas']++;
                }
            }

            foreach (CatalogoRegla::query()->delAnio($desde)->activos()->get() as $regla) {
                $this->replicar($regla, $hasta, [...self::SIN_VALIDAR, 'implementada' => false]);
                $conteo['reglas']++;
            }

            $existentes = ParametroFiscal::query()->where('tax_year', $hasta)->get()
                ->map(fn (ParametroFiscal $p) => "{$p->categoria}.{$p->clave}")->all();

            foreach (ParametroFiscal::query()->where('tax_year', $desde)->get() as $parametro) {
                if (in_array("{$parametro->categoria}.{$parametro->clave}", $existentes, true)) {
                    continue;
                }

                $this->replicar($parametro, $hasta, [...self::SIN_VALIDAR, 'valor' => null]);
                $conteo['parametros']++;
            }
        });

        CatalogoFiscal::invalidar($hasta);
        ParametrosFiscales::invalidate();

        return $conteo;
    }

    /**
     * @template TModel of Model
     *
     * @param  TModel  $modelo
     * @param  array<string, mixed>  $cambios
     * @return TModel
     */
    private function replicar(Model $modelo, int $anio, array $cambios): Model
    {
        $copia = $modelo->replicate();
        $copia->forceFill(['tax_year' => $anio, ...$cambios]);
        $copia->save();

        return $copia;
    }
}
