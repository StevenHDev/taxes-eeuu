<?php

namespace App\Services\Lectura\Reglas;

use App\Models\Entrevista\Entrevista;
use App\Models\Expediente\Expediente;
use App\Models\Expediente\ExpedienteDatoSinDocumento;
use App\Services\Expediente\DatosSinDocumento;
use Illuminate\Support\Collection;

/**
 * R-15 (informativa, F6 6.5): un hallazgo por cada dato anotado sin
 * documento que todavía cuenta para el cálculo. Es el registro de cómo,
 * cuándo y de quién se obtuvo la información que pide el 8867. El detalle
 * lleva solo ids y la clave: nunca el valor ni el motivo. Cuando el
 * documento llega, `vigentes()` lo deja fuera y la excepción se cierra sola.
 * Se registra en MotorReglas en la ola 5 (6.6).
 */
class R15SinDocumento implements Regla
{
    public function __construct(private readonly DatosSinDocumento $datos) {}

    public function codigo(): string
    {
        return 'R-15';
    }

    public function evaluar(Entrevista $entrevista, Collection $datosPorItem): array
    {
        $expediente = Expediente::query()->where('user_id', $entrevista->user_id)->where('tax_year', $entrevista->tax_year)->first();

        if ($expediente === null) {
            return [];
        }

        return $this->datos->vigentes($expediente)
            ->map(fn (ExpedienteDatoSinDocumento $d) => new Hallazgo("R-15#dato:{$d->id}", detalle: [
                'dato_sin_documento_id' => $d->id,
                'clave' => $d->clave,
                'persona_id' => $d->persona_id,
                'anotado_por' => $d->anotado_por,
            ]))
            ->values()
            ->all();
    }
}
