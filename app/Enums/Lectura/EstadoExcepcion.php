<?php

namespace App\Enums\Lectura;

/**
 * Estado de una excepción de la cola (documento madre, 6.3).
 */
enum EstadoExcepcion: string
{
    case Abierta = 'abierta';
    /** La regla ya se cumple (se corrigió el dato o cambió la respuesta). */
    case Corregida = 'corregida';
    /** El preparador la aceptó con una nota. */
    case Aceptada = 'aceptada';
    /** Se le consultó al cliente; vuelve a la cola cuando responde. */
    case Consultada = 'consultada';

    public function abierta(): bool
    {
        return in_array($this, [self::Abierta, self::Consultada], true);
    }
}
