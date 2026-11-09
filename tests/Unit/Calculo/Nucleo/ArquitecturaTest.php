<?php

use App\Services\Calculo\Nucleo\Calculadora;

arch('el motor no usa coma flotante ni redondeo ni formato numérico de PHP')
    ->expect('App\Services\Calculo')
    ->not->toUse(['floatval', 'doubleval', 'round', 'number_format', 'floor', 'ceil']);

it('ningún archivo de app/Services/Calculo declara ni convierte a float', function () {
    $archivos = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 4).'/app/Services/Calculo'));
    $revisados = 0;

    foreach ($archivos as $archivo) {
        if (! str_ends_with((string) $archivo, '.php')) {
            continue;
        }
        $revisados++;

        foreach (token_get_all(file_get_contents((string) $archivo)) as $token) {
            if (! is_array($token)) {
                continue;
            }
            // Comentarios y cadenas no cuentan: solo código.
            expect($token[0])->not->toBe(T_DNUMBER, "{$archivo}: literal decimal")
                ->and($token[0])->not->toBe(T_DOUBLE_CAST, "{$archivo}: cast a float");

            if ($token[0] === T_STRING) {
                expect(strtolower($token[1]))->not->toBeIn(['float', 'double', 'round', 'number_format', 'floatval'], "{$archivo}: {$token[1]}");
            }
        }
    }

    expect($revisados)->toBeGreaterThan(5);
});

arch('Calculadora es una interfaz')
    ->expect(Calculadora::class)->toBeInterface();

arch('los objetos de valor y DTO del núcleo son final readonly')
    ->expect([
        'App\Services\Calculo\Nucleo\Dinero',
        'App\Services\Calculo\Nucleo\Linea',
        'App\Services\Calculo\Nucleo\Traza',
        'App\Services\Calculo\Nucleo\PasoTraza',
        'App\Services\Calculo\Nucleo\EntradasDeclaracion',
        'App\Services\Calculo\Nucleo\DatoEntrada',
    ])->toBeReadonly()->toBeFinal();
