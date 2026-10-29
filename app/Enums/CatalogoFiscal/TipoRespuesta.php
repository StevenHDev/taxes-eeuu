<?php

namespace App\Enums\CatalogoFiscal;

/**
 * Formas de responder una pregunta del 13614-C (sección 3 del documento madre).
 */
enum TipoRespuesta: string
{
    case SiNo = 'si_no';
    case UstedConyugeNo = 'usted_conyuge_no';
    case Opciones = 'opciones';
    case Casillas = 'casillas';
    case Dato = 'dato';
    case Fecha = 'fecha';
    case Numero = 'numero';

    /**
     * Las que necesitan una lista de `opciones`.
     */
    public function llevaOpciones(): bool
    {
        return $this === self::Opciones || $this === self::Casillas;
    }
}
