<?php

return [
    'parametros' => [
        // Forma con fuente (['valor' => X, 'fuente_texto' => …, 'fuente_url' => …]): un parámetro que está en parametros.php del año de origen...
        'edad.nacido_antes_de_mayor_65' => ['valor' => '1962-01-02', 'fuente_texto' => 'Form 1040 de 2026, p. 2, línea 12d', 'fuente_url' => 'https://example.test/f1040-2026.pdf'],
        // ...y uno que solo vive en la base (lo siembra la migración de 2025).
        'dependiente_calificado.limite_ingreso_bruto_pariente_calificado' => ['valor' => 5300, 'fuente_texto' => 'Rev. Proc. de prueba, sección 4.23, p. 20', 'fuente_url' => 'https://example.test/rp.pdf'],
        // Una tabla con fuente y otra en la forma vieja (la lista suelta, sin fuente).
        'credito_cuidado_dependientes.tabla' => ['valor' => [['hasta' => 15000, 'hasta_conjunta' => 15000, 'decimal' => '0.50'], ['hasta' => null, 'hasta_conjunta' => null, 'decimal' => '0.20']], 'fuente_texto' => 'Instrucciones de prueba, p. 6', 'fuente_url' => 'https://example.test/i2441.pdf'],
        'tax_brackets.single' => [['desde' => 0, 'tasa' => 0.10], ['desde' => 12400, 'tasa' => 0.12]],
        // La forma con fuente puede dejar el valor vacío a propósito (pendiente).
        'self_employment_tax.tasa_medicare' => ['valor' => null, 'fuente_texto' => null, 'fuente_url' => null],
        'no.existe' => 1,
    ],
    'casillas' => ['1099-nec' => ['1' => ['casilla' => '1a']]],
    'lineas' => ['sch-c:1' => ['origen_clave' => '1099-nec:1a']],
    'preguntas' => ['D1' => ['fuente_texto' => '1040 línea 1a (2026)']],
];
