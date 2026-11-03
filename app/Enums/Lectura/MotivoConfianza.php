<?php

namespace App\Enums\Lectura;

/**
 * Por qué un dato leído tiene la confianza que tiene (F4.3c). Se guarda con
 * cada dato y se le muestra al preparador y al administrador: un número solo
 * (0,60) no dice qué hacer; el motivo sí.
 *
 * El número lo da CalculadoraConfianza; aquí va lo que se le explica a una
 * persona, en lenguaje sencillo.
 */
enum MotivoConfianza: string
{
    case EnTextoDelPdf = 'en_texto_del_pdf';
    case Claro = 'claro';
    case Dudoso = 'dudoso';
    case FormatoIncorrecto = 'formato_incorrecto';
    case NoSeVe = 'no_se_ve';
    case SinValor = 'sin_valor';

    public function titulo(): string
    {
        return match ($this) {
            self::EnTextoDelPdf => 'Está en el texto del PDF',
            self::Claro => 'Leído con claridad',
            self::Dudoso => 'Leído con dudas',
            self::FormatoIncorrecto => 'Formato incorrecto',
            self::NoSeVe => 'No se ve',
            self::SinValor => 'Sin valor',
        };
    }

    public function explicacion(): string
    {
        return match ($this) {
            self::EnTextoDelPdf => 'El mismo valor aparece en el texto del PDF, que viene escrito en el archivo y no se lee con inteligencia artificial. Es la lectura más segura.',
            self::Claro => 'La inteligencia artificial lo leyó con claridad y tiene la forma que corresponde a la casilla. Como viene de una imagen (foto o PDF escaneado), no se puede comprobar contra el texto del archivo.',
            self::Dudoso => 'La inteligencia artificial no está segura de algún carácter. Hay que compararlo con el documento.',
            self::FormatoIncorrecto => 'Lo que se leyó no tiene la forma que corresponde a la casilla (por ejemplo, un monto con tres decimales o un SSN de 8 dígitos). Casi seguro se leyó mal: hay que revisarlo.',
            self::NoSeVe => 'La casilla está borrosa, cortada o tapada y no se pudo leer. Si el documento la trae, hay que escribir el dato a mano.',
            self::SinValor => 'No se leyó ningún valor en esta casilla. Si el documento la trae, hay que escribir el dato a mano; si no la trae, se deja vacía.',
        };
    }
}
