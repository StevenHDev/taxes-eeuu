<?php

namespace App\Enums\CatalogoFiscal;

/**
 * Qué pasa cuando una regla de validación (6.2) no se cumple.
 */
enum SeveridadRegla: string
{
    case Bloqueante = 'bloqueante';
    case Revision = 'revision';
    case Informativa = 'informativa';
}
