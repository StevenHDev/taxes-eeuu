<?php

namespace App\Enums\Lectura;

/**
 * Hacia dónde va un renglón de los registros del negocio (F6 6.3b). Solo
 * `ingreso` y `gasto` suman al Schedule C; `transferencia` (entre cuentas
 * propias), `personal` y `no_se` se quedan fuera de las sumas.
 */
enum SentidoRenglon: string
{
    case Ingreso = 'ingreso';
    case Gasto = 'gasto';
    case Transferencia = 'transferencia';
    case Personal = 'personal';
    case NoSe = 'no_se';

    public function suma(): bool
    {
        return $this === self::Ingreso || $this === self::Gasto;
    }
}
