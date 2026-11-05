<?php

namespace App\Enums\Expediente;

/**
 * Tipos de ingreso y situación del cliente, para filtrar la lista sin correr
 * el motor por cada cliente. Nunca montos.
 */
enum PerfilExpediente: string
{
    case Asalariado = 'asalariado';
    case Independiente = 'independiente';
    case Jubilacion = 'jubilacion';
    case Inversiones = 'inversiones';
    case Alquiler = 'alquiler';
    case Marketplace = 'marketplace';
    case Dependientes = 'dependientes';
    case K1 = 'k1';

    public function label(): string
    {
        return match ($this) {
            self::Asalariado => __('Asalariado'),
            self::Independiente => __('Independiente'),
            self::Jubilacion => __('Jubilación o Seguro Social'),
            self::Inversiones => __('Inversiones'),
            self::Alquiler => __('Alquiler'),
            self::Marketplace => __('Seguro del Mercado'),
            self::Dependientes => __('Con dependientes'),
            self::K1 => __('Socio (K-1)'),
        };
    }
}
