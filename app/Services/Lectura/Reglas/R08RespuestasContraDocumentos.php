<?php

namespace App\Services\Lectura\Reglas;

use App\Models\Entrevista\Entrevista;
use App\Models\Entrevista\EntrevistaRespuesta;
use Illuminate\Support\Collection;

/**
 * R-08: lo respondido contradice un documento (revisión). Tres casos:
 * (a) un W-2 con casilla 10 (beneficios de cuidado de dependientes) mayor
 * que cero y E6 distinto de `si`; (b) más W-2 con datos leídos que los
 * empleos de D1.1; (c) una persona cubierta del 1095-A (parte II) cuyos
 * últimos 4 del SSN no están en la entrevista (solo se revisa con todas las
 * tarjetas leídas y con el número del cónyuge, para no acusar de más). El
 * detalle dice qué caso y qué casilla, nunca un número.
 */
class R08RespuestasContraDocumentos implements Regla
{
    public function codigo(): string
    {
        return 'R-08';
    }

    public function evaluar(Entrevista $entrevista, Collection $datosPorItem): array
    {
        $respuesta = fn (string $codigo) => EntrevistaRespuesta::query()
            ->where(['entrevista_id' => $entrevista->id, 'pregunta_codigo' => $codigo])->whereNull('persona_id')->first()?->valor;

        $hallazgos = [];
        $w2s = $entrevista->documentos()->vigentes()->where('documento_codigo', 'w2')->orderBy('id')->get();

        if ($respuesta('E6') !== 'si') {
            foreach ($w2s as $item) {
                $dato = $datosPorItem->get($item->id, collect())->firstWhere('casilla', '10');

                if ($dato !== null && is_numeric($dato->valor) && (float) $dato->valor > 0) {
                    $hallazgos[] = new Hallazgo("R-08#a#item:{$item->id}", $item, $dato, ['caso' => 'a', 'casilla' => '10', 'problema' => 'El W-2 trae beneficios de cuidado de dependientes y la entrevista dice que no se pagó cuidado.']);
                }
            }
        }

        $empleos = $respuesta('D1.1');
        $leidos = $w2s->filter(fn ($item) => $datosPorItem->get($item->id, collect())->isNotEmpty());

        if (is_numeric($empleos) && $leidos->count() > (int) $empleos) {
            $hallazgos[] = new Hallazgo('R-08#b', detalle: ['caso' => 'b', 'problema' => 'Hay más W-2 leídos que empleos en la entrevista.']);
        }

        $conocidos = R01Ssn::conocidos($entrevista, $datosPorItem);

        if ($conocidos['numeros'] !== [] && ! $conocidos['conyuge_pendiente'] && ! $conocidos['tarjetas_pendientes']) {
            foreach ($entrevista->documentos()->vigentes()->where('documento_codigo', '1095-a')->get() as $item) {
                $cubiertas = $datosPorItem->get($item->id, collect())->filter(fn ($d) => preg_match('/^(16|17|18|19|20)-B$/', $d->casilla) === 1);

                foreach ($cubiertas as $dato) {
                    $ultimos = R01Ssn::ultimos4($dato->valor);

                    if ($ultimos !== null && ! in_array($ultimos, $conocidos['numeros'], true)) {
                        $hallazgos[] = new Hallazgo("R-08#c#item:{$item->id}#casilla:{$dato->casilla}", $item, $dato, ['caso' => 'c', 'casilla' => $dato->casilla, 'problema' => 'La persona cubierta del 1095-A no está en la entrevista.']);
                    }
                }
            }
        }

        return $hallazgos;
    }
}
