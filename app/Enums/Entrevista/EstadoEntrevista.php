<?php

namespace App\Enums\Entrevista;

/**
 * Estado de la entrevista de un cliente en un año (F2.2). Responder algo en
 * una entrevista confirmada la pasa a reabierta: el cliente vuelve a
 * confirmar en el portal (2.7).
 */
enum EstadoEntrevista: string
{
    case EnCurso = 'en_curso';
    case Confirmada = 'confirmada';
    case Reabierta = 'reabierta';
}
