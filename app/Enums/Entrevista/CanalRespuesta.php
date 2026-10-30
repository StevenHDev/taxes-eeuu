<?php

namespace App\Enums\Entrevista;

/**
 * Por dónde llegó una respuesta de la entrevista (F2.2). Por `whatsapp`
 * nunca se guarda una pregunta sensible: eso se responde solo en el portal.
 */
enum CanalRespuesta: string
{
    case Whatsapp = 'whatsapp';
    case Portal = 'portal';
    case Preparador = 'preparador';
    case Migracion = 'migracion';
}
