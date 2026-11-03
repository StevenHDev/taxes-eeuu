<?php

namespace App\Services\Lectura\Reglas;

use App\Enums\Lectura\EstadoLectura;
use App\Models\Entrevista\Entrevista;
use App\Models\Lectura\DocumentoLectura;
use Illuminate\Support\Collection;

/**
 * R-06: el documento leído es del año que se declara y es el que se esperaba
 * en esa fila (un W-2 de 2024 o un 1099 subido en la fila del W-2). Compara
 * lo que detectó el lector en la última lectura de cada archivo; lo leído se
 * guarda igual. El detalle lleva solo los códigos y el año, no el contenido.
 */
class R06AnioYTipo implements Regla
{
    public function codigo(): string
    {
        return 'R-06';
    }

    public function evaluar(Entrevista $entrevista, Collection $datosPorItem): array
    {
        $hallazgos = [];

        foreach ($entrevista->documentos()->vigentes()->get() as $item) {
            $lecturas = DocumentoLectura::query()
                ->where('entrevista_documento_id', $item->id)
                ->where('estado', EstadoLectura::Leida)
                ->whereHas('documento')
                ->orderByDesc('id')
                ->get()
                ->unique('documento_id');

            foreach ($lecturas as $lectura) {
                if ($lectura->anio_detectado !== null && $lectura->anio_detectado !== $entrevista->tax_year) {
                    $hallazgos[] = new Hallazgo(
                        "R-06#item:{$item->id}#documento:{$lectura->documento_id}#anio",
                        $item,
                        detalle: ['campo' => 'anio', 'detectado' => $lectura->anio_detectado, 'esperado' => $entrevista->tax_year],
                    );
                }

                if ($lectura->tipo_detectado !== null && $lectura->tipo_detectado !== $item->documento_codigo) {
                    $hallazgos[] = new Hallazgo(
                        "R-06#item:{$item->id}#documento:{$lectura->documento_id}#tipo",
                        $item,
                        detalle: ['campo' => 'tipo', 'detectado' => $lectura->tipo_detectado, 'esperado' => $item->documento_codigo],
                    );
                }
            }
        }

        return $hallazgos;
    }
}
