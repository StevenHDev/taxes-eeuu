<?php

namespace App\Enums\Expediente;

/**
 * Créditos que piden la debida diligencia del Form 8867.
 */
enum Credito8867: string
{
    case Eic = 'eic';
    case Ctc = 'ctc';
    case Aotc = 'aotc';
    case Hoh = 'hoh';

    public function label(): string
    {
        return match ($this) {
            self::Eic => __('Crédito por ingreso del trabajo (EIC)'),
            self::Ctc => __('Crédito por hijos y otros dependientes (CTC/ACTC/ODC)'),
            self::Aotc => __('Crédito de oportunidad americana (AOTC)'),
            self::Hoh => __('Cabeza de familia (HOH)'),
        };
    }

    /** Parte del Form 8867 que corresponde al crédito. */
    public function parte(): string
    {
        return match ($this) {
            self::Eic => 'parte-ii',
            self::Ctc => 'parte-iii',
            self::Aotc => 'parte-iv',
            self::Hoh => 'parte-v',
        };
    }
}
