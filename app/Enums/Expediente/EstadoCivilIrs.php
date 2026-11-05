<?php

namespace App\Enums\Expediente;

/**
 * Estado civil para el IRS, con las siglas que usa TaxWise.
 */
enum EstadoCivilIrs: string
{
    case Single = 'single';
    case Mfj = 'mfj';
    case Mfs = 'mfs';
    case Hoh = 'hoh';
    case Qss = 'qss';

    public function label(): string
    {
        return match ($this) {
            self::Single => __('Soltero (Single)'),
            self::Mfj => __('Casado, declaración conjunta (MFJ)'),
            self::Mfs => __('Casado, declaraciones separadas (MFS)'),
            self::Hoh => __('Cabeza de familia (HOH)'),
            self::Qss => __('Cónyuge sobreviviente calificado (QSS)'),
        };
    }
}
