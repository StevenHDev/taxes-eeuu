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
     * Un archivo que no se pudo leer (F4.8a) sí le deja algo por hacer: subirlo
     * de nuevo, más nítido o en PDF.
     */
    public function resueltoParaElCliente(): bool
    {
        return ! in_array($this, [self::Pendiente, self::ConObservacion, self::NoAplicaRechazado, self::NoSePudoLeer], true);
    }

    /**
     * Cuenta para el checklist completo (5.3): validado o "no aplica".
     */
    public function cerrado(): bool
    {
        return in_array($this, [self::Validado, self::NoAplica], true);
    }

    /**
     * Cómo lo cuenta el preparador (F3.4): `faltan` (el cliente tiene algo
     * que hacer), `por_revisar`, `validados` o `no_aplica`.
     */
    public function grupo(): string
    {
        return match ($this) {
            self::Pendiente, self::ConObservacion, self::NoAplicaRechazado => 'faltan',
            self::Subido, self::Leido, self::NoSePudoLeer => 'por_revisar',
            self::Validado => 'validados',
            self::NoAplica => 'no_aplica',
        };
    }
}
