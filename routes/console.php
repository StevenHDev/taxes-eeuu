<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Corrida automática del meta-agente — ver AnalizarConversacionesAgenteCommand
// y META_AGENTE_HORA_DIARIA (config/meta_agente.php). El scheduler de
// Laravel necesita algo que invoque `schedule:run` cada minuto — ver el loop
// agregado a docker/entrypoint.sh en el rol "worker", no hay un cron del
// sistema operativo en este despliegue.
Schedule::command('meta-agente:analizar')
    ->dailyAt((string) config('meta_agente.hora_diaria'))
    ->onOneServer()
    ->withoutOverlapping();
