<?php

namespace App\Enums;

/**
 * Resultado calculado por App\Services\Reglas\FilingStatusCalculator — nunca
 * se le pide directamente al cliente, se deriva de los hechos en el campo
 * `estado_civil`. MFS (Married Filing Separately) no está incluido: es una
 * elección del contribuyente, no derivable de estos hechos (limitación
 * documentada de la Fase 2, no un bug).
 */
enum FilingStatus: string
{
    case MarriedFilingJointly = 'mfj';
    case Single = 'single';
    case HeadOfHousehold = 'hoh';
    case QualifyingSurvivingSpouse = 'qss';

    public function label(): string
    {
        return match ($this) {
            self::MarriedFilingJointly => 'Married Filing Jointly',
            self::Single => 'Single',
            self::HeadOfHousehold => 'Head of Household',
            self::QualifyingSurvivingSpouse => 'Qualifying Surviving Spouse',
        };
    }
}
