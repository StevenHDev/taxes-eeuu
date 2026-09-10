<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Usuario administrador sembrado por DatabaseSeeder
    |--------------------------------------------------------------------------
    |
    | env() fuera de config/ devuelve null cuando la configuración está
    | cacheada en producción (php artisan config:cache) — estos valores deben
    | leerse siempre vía config('seeding.admin.*'), nunca con env() directo
    | en el seeder.
    |
    */
    'admin' => [
        'email' => env('ADMIN_EMAIL', 'admin@example.com'),
        'name' => env('ADMIN_NAME', 'Admin'),
        'password' => env('ADMIN_PASSWORD', 'password'),
    ],

];
