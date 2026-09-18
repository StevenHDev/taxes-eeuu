<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Modelo de OpenAI para el meta-agente
    |--------------------------------------------------------------------------
    |
    | Sin META_AGENTE_MODEL definido, usa el mismo modelo del agente
    | conversacional (OPENAI_MODEL) — decisión explícita: el volumen de este
    | análisis es bajo (por lotes, no por cada mensaje de cliente), así que
    | usar un modelo más capaz acá tiene costo marginal si se quiere ajustar
    | más adelante sin tocar código, solo la variable de entorno.
    |
    */
    'modelo' => env('META_AGENTE_MODEL', env('OPENAI_MODEL')),

    /*
    |--------------------------------------------------------------------------
    | Hora de la corrida automática diaria
    |--------------------------------------------------------------------------
    |
    | Formato "HH:MM", hora del servidor (ver APP_TIMEZONE). Leída una sola
    | vez al registrar el schedule (routes/console.php) — cambiarla requiere
    | reiniciar el contenedor worker (config:cache), igual que OPENAI_MODEL.
    |
    */
    'hora_diaria' => env('META_AGENTE_HORA_DIARIA', '03:00'),

    /*
    |--------------------------------------------------------------------------
    | Alcance de la corrida automática diaria
    |--------------------------------------------------------------------------
    |
    | "actividad": analiza cualquier conversación con mensajes nuevos desde
    | su último análisis, haya terminado o no — detecta antes una
    | conversación que quedó atascada.
    | "cierre": solo conversaciones cuyo cliente ya llegó a la fase de Cierre
    | en las últimas 24h — menos volumen, pero no ve conversaciones
    | inconclusas.
    |
    */
    'alcance_diario' => env('META_AGENTE_ALCANCE_DIARIO', 'actividad'),

];
