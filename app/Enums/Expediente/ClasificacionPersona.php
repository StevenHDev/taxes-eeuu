<?php

namespace App\Enums\Expediente;

/**
 * Decisión del preparador sobre cada persona del bloque C.
 */
enum ClasificacionPersona: string
{
    case HijoCalificado = 'hijo_calificado';
    case ParienteCalificado = 'pariente_calificado';
    case NoDependiente = 'no_dependiente';

    public function label(): string
    {
        return match ($this) {
            self::HijoCalificado => __('Hijo calificado'),
            self::ParienteCalificado => __('Pariente calificado'),
            self::NoDependiente => __('No es dependiente'),
        };
    }

    public function esDependiente(): bool
    {
        return $this !== self::NoDependiente;
    }
}
