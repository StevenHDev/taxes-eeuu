<?php

namespace App\Services\Entrevista;

/**
 * Una respuesta que llega del portal, del agente, del preparador o de la
 * migración, antes de validarla.
 */
final class RespuestaData
{
    public function __construct(
        public readonly string $preguntaCodigo,
        public readonly mixed $valor,
        public readonly ?int $personaId = null,
    ) {}
}
