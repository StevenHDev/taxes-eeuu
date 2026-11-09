<?php

namespace App\Enums\Lectura;

/**
 * Qué clase de archivo de registros del negocio se detectó (F6 6.3b).
 */
enum ClaseDetectada: string
{
    case Plantilla = 'plantilla';
    case ExtractoBanco = 'extracto_banco';
    case ExtractoTarjeta = 'extracto_tarjeta';
    case Recibo = 'recibo';
    case HojaCalculo = 'hoja_calculo';
    case Millaje = 'millaje';
    case Otro = 'otro';
}
