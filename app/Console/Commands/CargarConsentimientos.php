<?php

namespace App\Console\Commands;

use App\Services\Consentimiento\ConsentimientosCargador;
use Illuminate\Console\Command;

/**
 * Carga los textos de consentimiento desde database/data/consentimientos
 * (F2.4). Idempotente.
 */
class CargarConsentimientos extends Command
{
    protected $signature = 'consentimientos:cargar';

    protected $description = 'Carga los textos de consentimiento desde database/data/consentimientos';

    public function handle(ConsentimientosCargador $cargador): int
    {
        $resultado = $cargador->cargar();

        $this->table(['Creados', 'Actualizados', 'Sin cambios'], [[
            implode(', ', $resultado['creados']) ?: '—',
            implode(', ', $resultado['actualizados']) ?: '—',
            implode(', ', $resultado['sin_cambios']) ?: '—',
        ]]);

        return self::SUCCESS;
    }
}
