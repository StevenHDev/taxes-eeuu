<?php

namespace App\Services\Lectura\Reglas;

use App\Models\Documento;
use App\Models\Entrevista\Entrevista;
use App\Services\DocumentoDuplicadoService;
use Illuminate\Support\Collection;

/**
 * R-07: el mismo archivo (los mismos bytes) ya se subió, para este cliente o
 * para otro. Es un aviso de revisión: el preparador decide. El detalle dice
 * cuántas coincidencias y si alguna es de otro cliente, nunca de quién.
 */
class R07Repetido implements Regla
{
    public function __construct(private readonly DocumentoDuplicadoService $duplicados) {}

    public function codigo(): string
    {
        return 'R-07';
    }

    public function evaluar(Entrevista $entrevista, Collection $datosPorItem): array
    {
        $hallazgos = [];

        foreach ($entrevista->documentos()->vigentes()->get() as $item) {
            foreach (Documento::query()->where('entrevista_documento_id', $item->id)->get() as $archivo) {
                $coincidencias = $this->duplicados->buscarCoincidencias($archivo);

                if ($coincidencias->isEmpty()) {
                    continue;
                }

                $hallazgos[] = new Hallazgo(
                    "R-07#item:{$item->id}#documento:{$archivo->id}",
                    $item,
                    detalle: [
                        'coincidencias' => $coincidencias->count(),
                        'de_otro_cliente' => $coincidencias->contains(fn (Documento $d) => $d->user_id !== $archivo->user_id),
                    ],
                );
            }
        }

        return $hallazgos;
    }
}
