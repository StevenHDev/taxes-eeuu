<?php

namespace App\Services\Lectura\Reglas;

use App\Models\Entrevista\Entrevista;
use App\Models\Lectura\DocumentoDato;
use Illuminate\Support\Collection;

/**
 * Una regla de validación del catálogo (F4.5). La regla es dato (código,
 * severidad, descripción, año); esta clase es lo que hace. Sus números salen
 * de los parámetros del año, nunca del código.
 */
interface Regla
{
    /** El código de la regla en el catálogo (`R-04`). */
    public function codigo(): string;

    /**
     * @param  Collection<int, Collection<int, DocumentoDato>>  $datosPorItem  los datos vigentes de cada ítem, por id del ítem
     * @return list<Hallazgo>
     */
    public function evaluar(Entrevista $entrevista, Collection $datosPorItem): array;
}
