<?php

namespace App\Enums\Entrevista;

/**
 * Estado de un documento del checklist (documento madre, 5.3). `leido` y
 * `no_se_pudo_leer` los pone la lectura de la fase 4.
 */
enum EstadoDocumento: string
{
    case Pendiente = 'pendiente';
    case Subido = 'subido';
    case Leido = 'leido';
    case NoSePudoLeer = 'no_se_pudo_leer';
    case ConObservacion = 'con_observacion';
    case Validado = 'validado';
    case NoAplica = 'no_aplica';
    case NoAplicaRechazado = 'no_aplica_rechazado';

    /**
     * Ya no le falta nada al cliente (decisión 4 del plan de la fase 3: un
     * "no aplica" cuenta apenas lo marca; el preparador lo puede rechazar).
     */
    public function resueltoParaElCliente(): bool
    {
        return ! in_array($this, [self::Pendiente, self::ConObservacion, self::NoAplicaRechazado], true);
    }

    /**
     * Cuenta para el checklist completo (5.3): validado o "no aplica".
     */
    public function cerrado(): bool
    {
        return in_array($this, [self::Validado, self::NoAplica], true);
    }
}
