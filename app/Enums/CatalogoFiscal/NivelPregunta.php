<?php

namespace App\Enums\CatalogoFiscal;

/**
 * Los tres niveles de la entrevista (sección 3): lo que se pregunta a todos,
 * lo que solo se pregunta si una respuesta lo abre, y lo que no se pregunta
 * porque se lee de un documento.
 */
enum NivelPregunta: string
{
    case Entrada = 'entrada';
    case Seguimiento = 'seguimiento';
    case NoSePregunta = 'no_se_pregunta';
}
