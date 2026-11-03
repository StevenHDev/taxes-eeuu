<?php

namespace App\Services\Lectura\Reglas;

use App\Models\Entrevista\Entrevista;
use Illuminate\Support\Collection;

/**
 * R-09: un 1095-A con adelanto del crédito de la prima (columna C de los
 * meses, 21 a 32, o el total anual, 33) mayor que cero hace obligatorio el
 * Form 8962. Es informativa: el 8962 ya lo activa la respuesta de F4; la
 * regla le dice al preparador que aquí no es opcional.
 */
class R09Adelanto8962 implements Regla
{
    public function codigo(): string
    {
        return 'R-09';
    }

    public function evaluar(Entrevista $entrevista, Collection $datosPorItem): array
    {
        $casillas = [...array_map(fn (int $fila) => "{$fila}-C", range(21, 32)), '33-C'];
        $hallazgos = [];

        foreach ($entrevista->documentos()->vigentes()->where('documento_codigo', '1095-a')->get() as $item) {
            $con = $datosPorItem->get($item->id, collect())
                ->first(fn ($d) => in_array($d->casilla, $casillas, true) && is_numeric($d->valor) && (float) $d->valor > 0);

            if ($con !== null) {
                $hallazgos[] = new Hallazgo("R-09#item:{$item->id}", $item, $con, ['problema' => 'Hay adelanto del crédito de la prima: el Form 8962 es obligatorio.']);
            }
        }

        return $hallazgos;
    }
}
