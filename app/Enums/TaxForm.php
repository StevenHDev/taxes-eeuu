<?php

namespace App\Enums;

enum TaxForm: string
{
    case Form1040 = 'form_1040';
    case ScheduleC = 'schedule_c';
    case ScheduleE = 'schedule_e';
    case Form1065 = 'form_1065';
    case Form1120 = 'form_1120';
    case Form1120S = 'form_1120_s';
    case ScheduleF = 'schedule_f';
    case Form1041 = 'form_1041';
    case Form990 = 'form_990';
    case Form1040Nr = 'form_1040_nr';

    public function label(): string
    {
        return match ($this) {
            self::Form1040 => 'Form 1040 (Individual)',
            self::ScheduleC => 'Schedule C (Negocio propio)',
            self::ScheduleE => 'Schedule E (Alquiler)',
            self::Form1065 => 'Form 1065 (Sociedad)',
            self::Form1120 => 'Form 1120 (Corporación C)',
            self::Form1120S => 'Form 1120-S (Corporación S)',
            self::ScheduleF => 'Schedule F (Granja)',
            self::Form1041 => 'Form 1041 (Fideicomiso/sucesión)',
            self::Form990 => 'Form 990 (Sin fines de lucro)',
            self::Form1040Nr => 'Form 1040-NR (No residente)',
        };
    }
}
