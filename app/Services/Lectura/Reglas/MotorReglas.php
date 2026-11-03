<?php

namespace App\Services\Lectura\Reglas;

use App\Enums\Lectura\EstadoExcepcion;
use App\Models\CatalogoFiscal\CatalogoRegla;
use App\Models\Entrevista\Entrevista;
use App\Models\Lectura\Excepcion;
use App\Services\Lectura\DatosDocumento;
use Illuminate\Support\Facades\DB;

/**
 * Corre las reglas del año sobre una entrevista y mantiene sus excepciones
 * (F4.5). Corre después de cada lectura y cada corrección; correr dos veces
 * no duplica nada.
 *
 * - Solo corren las reglas activas y marcadas `implementada` en el catálogo
 *   del año, y la severidad es la del catálogo.
 * - Por cada hallazgo hay una sola excepción (`entrevista_id` + `clave`): si
 *   es nueva queda abierta; si estaba corregida se reabre; si el preparador ya
 *   la aceptó, no se toca.
 * - La excepción abierta cuyo hallazgo ya no aparece pasa a corregida.
 */
class MotorReglas
{
    /** @var list<class-string<Regla>> */
    private const REGLAS = [
        R03Confianza::class,
        R04TopesW2::class,
        R06AnioYTipo::class,
        R07Repetido::class,
        R09Adelanto8962::class,
    ];

    public function __construct(private readonly DatosDocumento $datos) {}

    public function correr(Entrevista $entrevista): void
    {
        $catalogo = CatalogoRegla::query()->delAnio($entrevista->tax_year)->activos()->where('implementada', true)->get()->keyBy('codigo');

        if ($catalogo->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($entrevista, $catalogo) {
            Entrevista::query()->whereKey($entrevista->id)->lockForUpdate()->first();

            $datosPorItem = $entrevista->documentos()->vigentes()->get()
                ->mapWithKeys(fn ($item) => [$item->id => $this->datos->vigentes($item)]);

            foreach (self::REGLAS as $clase) {
                $regla = app($clase);
                $registro = $catalogo->get($regla->codigo());

                if ($registro === null) {
                    continue;
                }

                $vistas = [];

                foreach ($regla->evaluar($entrevista, $datosPorItem) as $hallazgo) {
                    $vistas[] = $hallazgo->clave;
                    $this->registrar($entrevista, $regla->codigo(), $registro, $hallazgo);
                }

                Excepcion::query()
                    ->where('entrevista_id', $entrevista->id)
                    ->where('regla_codigo', $regla->codigo())
                    ->whereIn('estado', [EstadoExcepcion::Abierta, EstadoExcepcion::Consultada])
                    ->whereNotIn('clave', $vistas)
                    ->update(['estado' => EstadoExcepcion::Corregida, 'resuelta_en' => now()]);
            }
        });
    }

    private function registrar(Entrevista $entrevista, string $codigo, CatalogoRegla $registro, Hallazgo $hallazgo): void
    {
        $excepcion = Excepcion::query()->firstOrNew(['entrevista_id' => $entrevista->id, 'clave' => $hallazgo->clave]);

        if ($excepcion->exists && $excepcion->estado === EstadoExcepcion::Aceptada) {
            return;
        }

        $excepcion->fill([
            'entrevista_documento_id' => $hallazgo->item?->id,
            'documento_dato_id' => $hallazgo->dato?->id,
            'regla_codigo' => $codigo,
            'severidad' => $registro->severidad,
            'detalle' => $hallazgo->detalle,
        ]);

        // Una nueva queda abierta; una corregida que vuelve a fallar se reabre.
        if (! $excepcion->exists || $excepcion->estado === EstadoExcepcion::Corregida) {
            $excepcion->fill(['estado' => EstadoExcepcion::Abierta, 'resuelta_por' => null, 'resuelta_en' => null]);
        }

        $excepcion->save();
    }
}
