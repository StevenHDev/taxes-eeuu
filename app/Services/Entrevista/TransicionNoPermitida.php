<?php

namespace App\Services\Entrevista;

use DomainException;

/**
 * Un cambio de estado del checklist de documentos que no se permite (F3.2):
 * el estado actual no lo deja, falta el motivo o el ítem ya no aplica. No se
 * escribe nada; el mensaje se muestra junto al documento.
 */
class TransicionNoPermitida extends DomainException {}
