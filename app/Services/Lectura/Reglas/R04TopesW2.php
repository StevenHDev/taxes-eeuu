<?php

namespace App\Services\Lectura\Reglas;

use App\Models\Entrevista\Entrevista;
use App\Support\ParametrosFiscales;
use Illuminate\Support\Collection;

/**
 * R-04, en un W-2: la retención de impuesto federal (casilla 2) no puede ser
 * mayor que el salario (casilla 1), y los salarios del Seguro Social más las
 * propinas (casillas 3 y 7) no pueden pasar del tope del año (parámetro
 * `self_employment_tax.tope_salario_social_security`; sin el tope, solo se
 * revisa lo primero). Usa el valor vigente: lo corregido por el preparador.
 */
class R04TopesW2 implements Regla
{
    public function codigo(): string
    {
        return 'R-04';
    }

    public function evaluar(Entrevista $entrevista, Collection $datosPorItem): array
    {
        $tope = ParametrosFiscales::valor($entrevista->tax_year, 'self_employment_tax', 'tope_salario_social_security');
        $hallazgos = [];

        foreach ($entrevista->documentos()->vigentes()->where('documento_codigo', 'w2')->get() as $item) {
            $datos = $datosPorItem->get($item->id, collect())->keyBy('casilla');
            $monto = fn (string $casilla): ?float => is_numeric($datos->get($casilla)?->valor) ? (float) $datos->get($casilla)->valor : null;

            [$uno, $dos, $tres, $siete] = [$monto('1'), $monto('2'), $monto('3'), $monto('7')];

            if ($uno !== null && $dos !== null && $dos > $uno) {
                $hallazgos[] = new Hallazgo("R-04#item:{$item->id}#casilla:2", $item, $datos->get('2'), ['casilla' => '2', 'problema' => 'La casilla 2 es mayor que la casilla 1.']);
            }

            if (is_numeric($tope) && $tres !== null && $tres + ($siete ?? 0.0) > (float) $tope) {
                $hallazgos[] = new Hallazgo("R-04#item:{$item->id}#casilla:3", $item, $datos->get('3'), ['casilla' => '3', 'problema' => 'La casilla 3 más la 7 pasan del tope del Seguro Social del año.']);
            }
        }

        return $hallazgos;
    }
}
