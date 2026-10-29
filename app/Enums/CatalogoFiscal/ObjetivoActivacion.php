<?php

namespace App\Enums\CatalogoFiscal;

/**
 * Qué abre una respuesta: otra pregunta, un documento a pedir o un
 * formulario a llenar (columna "Activa / descarta" de la sección 3).
 */
enum ObjetivoActivacion: string
{
    case Pregunta = 'pregunta';
    case Documento = 'documento';
    case Formulario = 'formulario';
}
