<?php

namespace App\Services\Entrevista;

use DomainException;

/**
 * El guardador no aceptó una respuesta (pregunta que no existe o no se ve,
 * valor de otro tipo, pregunta sensible por WhatsApp…). No se escribe nada.
 * El portal la muestra como error del campo y el agente se la explica al
 * cliente.
 */
class RespuestaRechazada extends DomainException
{
    public function __construct(public readonly string $codigo, string $mensaje)
    {
        parent::__construct($mensaje);
    }
}
