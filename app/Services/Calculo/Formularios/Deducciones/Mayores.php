<?php

namespace App\Services\Calculo\Formularios\Deducciones;

use App\Services\Calculo\Nucleo\PersonaEntrada;
use App\Services\Calculo\Nucleo\RolPersona;

/**
 * "Nacido antes del 2 de enero de 1961" (Form 1040 de 2025, línea 12d; Schedule 1-A, línea 36a):
 * es tener 65 años al cierre de 2025, porque "a person is considered to reach age 65 on the day
 * before the person's 65th birthday" (instrucciones del 1040 de 2025, p. 110). La usan la 12d
 * y la Parte V del Schedule 1-A.
 *
 * `PersonaEntrada` trae la edad al 31 de diciembre, no la fecha: quien nació el 1 de enero de
 * 1961 llega con 64 años y para el IRS ya tiene 65. Con 64 años no se puede saber y se devuelve
 * null (el preparador confirma la fecha), igual que hace `Eitc` con los 24 años.
 */
final class Mayores
{
    /** La edad de la línea 12d: 65 años (estatutaria, no cambia por año). */
    public const EDAD = 65;

    /** true / false, o null si falta la edad o tiene justo un año menos (puede haber nacido el 1 de enero). */
    public static function esMayor(PersonaEntrada $persona): ?bool
    {
        $edad = $persona->edadAlFinDeAnio;

        if ($edad === null || $edad === self::EDAD - 1) {
            return null;
        }

        return $edad >= self::EDAD;
    }

    /** El motivo cuando `esMayor()` devuelve null. */
    public static function motivo(PersonaEntrada $persona): string
    {
        $esConyuge = $persona->rol === RolPersona::Conyuge;

        if ($persona->edadAlFinDeAnio === null) {
            return $esConyuge
                ? __('Falta la fecha de nacimiento del cónyuge (pregunta B4.1).')
                : __('Falta la fecha de nacimiento del contribuyente (pregunta A1.1).');
        }

        return $esConyuge
            ? __('El cónyuge tiene 64 años al cierre del año: si cumple los 65 el 1 de enero siguiente, para el IRS ya los tiene (instrucciones del 1040, p. 110). Lo confirma el preparador con la fecha de nacimiento.')
            : __('El contribuyente tiene 64 años al cierre del año: si cumple los 65 el 1 de enero siguiente, para el IRS ya los tiene (instrucciones del 1040, p. 110). Lo confirma el preparador con la fecha de nacimiento.');
    }

    public static function etiqueta(PersonaEntrada $persona): string
    {
        return $persona->rol === RolPersona::Conyuge
            ? __('El cónyuge tiene 65 años o más al cierre del año')
            : __('El contribuyente tiene 65 años o más al cierre del año');
    }

    /** Referencia para la traza: la persona y el dato, nunca la fecha. */
    public static function referencia(PersonaEntrada $persona): string
    {
        return 'persona:'.$persona->id.':edad';
    }
}
