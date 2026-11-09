<?php

use App\Services\Calculo\Nucleo\Dinero;
use App\Services\Calculo\Nucleo\OrigenTraza;
use App\Services\Calculo\Nucleo\PasoTraza;
use App\Services\Calculo\Nucleo\TipoPaso;
use App\Services\Calculo\Nucleo\Traza;

it('es inmutable: cada método devuelve otra traza', function () {
    $vacia = Traza::vacia();
    $uno = $vacia->conEntrada('W-2 casilla 1', Dinero::deDolares('100'), OrigenTraza::Leido, 'dato:12');
    $dos = $uno->conParametro('Tasa', '0.9235', 'se.neto')->conOperacion('Multiplicar')->conResultado('Línea 4a', Dinero::deDolares('92.35'));

    expect($vacia->pasos)->toBeEmpty()
        ->and($uno)->not->toBe($vacia)
        ->and($uno->pasos)->toHaveCount(1)
        ->and($dos)->not->toBe($uno)
        ->and($dos->pasos)->toHaveCount(4)
        ->and(array_map(fn (PasoTraza $p) => $p->tipo, $dos->pasos))
        ->toBe([TipoPaso::Entrada, TipoPaso::Parametro, TipoPaso::Operacion, TipoPaso::Resultado])
        ->and($dos->pasos[0]->origen)->toBe(OrigenTraza::Leido)
        ->and($dos->pasos[1]->origen)->toBe(OrigenTraza::Parametro);
});

it('rechaza una referencia con forma de SSN', function (string $ref) {
    Traza::vacia()->conEntrada('SSN', null, OrigenTraza::Leido, $ref);
})->with(['123-45-6789', '123456789', 'dato 123-456789'])->throws(InvalidArgumentException::class);

it('acepta referencias que son ids, códigos o claves', function () {
    $t = Traza::vacia()->conEntrada('x', null, OrigenTraza::Respuesta, 'G3')
        ->conEntrada('y', null, OrigenTraza::Leido, 'dato:4821')
        ->conParametro('z', null, 'tarifas.2025.se_tasa');

    expect($t->pasos)->toHaveCount(3);
});
