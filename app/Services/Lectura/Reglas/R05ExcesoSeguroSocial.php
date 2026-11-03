<?php

namespace App\Services\Lectura\Reglas;

use App\Models\Entrevista\Entrevista;
use App\Support\ParametrosFiscales;
use Illuminate\Support\Collection;

/**
 * R-05: una persona con dos o más W-2 cuyo Seguro Social retenido (casilla 4)
 * suma más del máximo del año (parámetro
 * `self_employment_tax.maximo_seguro_social_retenido_por_persona`) tiene un
 * posible crédito por exceso (Schedule 3, línea 11). Es informativa. Se suma
 * por persona: el W-2 de cada cónyuge cuenta aparte, y la persona se
 * reconoce por los últimos 4 del SSN del W-2 (casilla a); un W-2 sin ese
 * número no se suma. El detalle dice cuántos W-2, nunca el monto.
 */
class R05ExcesoSeguroSocial implements Regla
{
    public function codigo(): string
    {
        return 'R-05';
    }

    public function evaluar(Entrevista $entrevista, Collection $datosPorItem): array
    {
        $maximo = ParametrosFiscales::valor($entrevista->tax_year, 'self_employment_tax', 'maximo_seguro_social_retenido_por_persona');

        if (! is_numeric($maximo)) {
            return [];
        }

        $personas = [];

        foreach ($entrevista->documentos()->vigentes()->where('documento_codigo', 'w2')->orderBy('id')->get() as $item) {
            $datos = $datosPorItem->get($item->id, collect())->keyBy('casilla');
            $persona = R01Ssn::ultimos4($datos->get('a')?->valor);
            $retenido = $datos->get('4')?->valor;

            if ($persona !== null && is_numeric($retenido)) {
                $personas[$persona][] = [$item, $datos->get('4'), (float) $retenido];
            }
        }

        $hallazgos = [];

        foreach ($personas as $w2s) {
            if (count($w2s) < 2 || array_sum(array_column($w2s, 2)) <= (float) $maximo) {
                continue;
            }

            [$primero, $dato] = $w2s[0];
            $hallazgos[] = new Hallazgo("R-05#item:{$primero->id}", $primero, $dato, ['casilla' => '4', 'w2' => count($w2s), 'problema' => 'El Seguro Social retenido de varios W-2 de una persona pasa del máximo del año: posible crédito por exceso.']);
        }

        return $hallazgos;
    }
}
