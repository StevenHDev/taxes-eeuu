<?php

namespace App\Enums\Expediente;

enum TipoEventoExpediente: string
{
    case Estado = 'estado';
    case Reabierto = 'reabierto';
    case Decision = 'decision';
    case Punto = 'punto';
    case CambioTrasPresentar = 'cambio_tras_presentar';

    public function label(): string
    {
        return match ($this) {
            self::Estado => __('Cambio de estado'),
            self::Reabierto => __('Revisión reabierta'),
            self::Decision => __('Decisión del preparador'),
            self::Punto => __('Punto de la revisión'),
            self::CambioTrasPresentar => __('Cambio después de presentar'),
        };
    }
}
