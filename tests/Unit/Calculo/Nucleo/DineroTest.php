<?php

use App\Services\Calculo\Nucleo\Dinero;

it('construye desde texto con centavos', function () {
    expect(Dinero::deDolares('1234.56')->centavos())->toBe(123456)
        ->and(Dinero::deDolares('1234.5')->centavos())->toBe(123450)
        ->and(Dinero::deDolares('-0.05')->centavos())->toBe(-5)
        ->and(Dinero::deDolares(7)->centavos())->toBe(700)
        ->and(Dinero::deCentavos(99)->centavos())->toBe(99);
});

it('rechaza montos con más de dos decimales o basura', function (string $malo) {
    Dinero::deDolares($malo);
})->with(['1.234', 'abc', '', '1,5'])->throws(InvalidArgumentException::class);

it('suma y resta sin error', function () {
    $a = Dinero::deDolares('0.10');
    $b = Dinero::deDolares('0.20');

    expect($a->mas($b)->centavos())->toBe(30)
        ->and($a->menos($b)->centavos())->toBe(-10)
        ->and($a->menos($b)->esNegativo())->toBeTrue();
});

it('multiplica por un factor decimal sin error de coma flotante', function () {
    // 0.9235 sobre 100.000,00 = 92.350,00 exacto
    expect(Dinero::deDolares('100000')->por('0.9235')->centavos())->toBe(9235000)
        // 123,45 * 0.9235 = 114,005… -> 114,01 (mitad hacia afuera)
        ->and(Dinero::deDolares('123.45')->por('0.9235')->centavos())->toBe(11401)
        ->and(Dinero::deDolares('0.05')->por('0.5')->centavos())->toBe(3)
        ->and(Dinero::deDolares('-0.05')->por('0.5')->centavos())->toBe(-3)
        ->and(Dinero::deDolares('10')->por('-0.124')->centavos())->toBe(-124)
        ->and(Dinero::deDolares('1000')->por('0.153')->centavos())->toBe(15300);
});

it('rechaza un factor que no es decimal', function () {
    Dinero::deDolares('1')->por('1e3');
})->throws(InvalidArgumentException::class);

it('redondea a dólares en los bordes de 49 y 50 centavos', function () {
    expect(Dinero::deCentavos(139)->aDolares()->centavos())->toBe(100)
        ->and(Dinero::deCentavos(249)->aDolares()->centavos())->toBe(200)
        ->and(Dinero::deCentavos(250)->aDolares()->centavos())->toBe(300)
        ->and(Dinero::deCentavos(49)->aDolares()->centavos())->toBe(0)
        ->and(Dinero::deCentavos(50)->aDolares()->centavos())->toBe(100)
        ->and(Dinero::deCentavos(99)->aDolares()->centavos())->toBe(100)
        ->and(Dinero::deCentavos(10050)->aDolares()->centavos())->toBe(10100);
});

it('redondea los negativos de forma simétrica', function () {
    expect(Dinero::deCentavos(-49)->aDolares()->centavos())->toBe(0)
        ->and(Dinero::deCentavos(-50)->aDolares()->centavos())->toBe(-100)
        ->and(Dinero::deCentavos(-250)->aDolares()->centavos())->toBe(-300);
});

it('compara con max y min', function () {
    $a = Dinero::deDolares('5');
    $b = Dinero::deDolares('-5');

    expect($a->max($b))->toBe($a)
        ->and($a->min($b))->toBe($b)
        ->and(Dinero::deCentavos(0)->esNegativo())->toBeFalse();
});

it('da el texto con punto de miles y coma decimal', function () {
    expect(Dinero::deDolares('1234.56')->texto())->toBe('1.234,56')
        ->and(Dinero::deDolares('1234567.5')->texto())->toBe('1.234.567,50')
        ->and(Dinero::deCentavos(5)->texto())->toBe('0,05')
        ->and(Dinero::deCentavos(-123456)->texto())->toBe('-1.234,56')
        ->and(Dinero::deCentavos(0)->texto())->toBe('0,00');
});
