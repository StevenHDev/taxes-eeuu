<?php

return [
    [
        'codigo' => '1040', 'nombre' => 'Form 1040', 'siempre' => true, 'ficha' => 'completa', 'orden' => 1,
        'fuente_texto' => 'Form 1040 de 2025',
        'lineas' => [
            ['linea' => '1a', 'descripcion' => 'Salarios', 'origen_tipo' => 'casilla', 'origen_clave' => 'w2:1'],
            ['linea' => '19', 'descripcion' => 'Crédito por hijos', 'origen_tipo' => 'linea', 'origen_clave' => 'sch-8812:14'],
            ['linea' => '25a', 'descripcion' => 'Retención de los W-2', 'origen_tipo' => 'casilla', 'origen_clave' => 'w2:2'],
        ],
    ],
    [
        'codigo' => 'sch-8812', 'nombre' => 'Schedule 8812', 'ficha' => 'completa', 'orden' => 2,
        'fuente_texto' => 'Schedule 8812 de 2025',
        'lineas' => [
            ['linea' => '4', 'descripcion' => 'Hijos menores de 17 con SSN', 'origen_tipo' => 'respuesta', 'origen_clave' => 'C2'],
            ['linea' => '14', 'descripcion' => 'Crédito', 'origen_tipo' => 'calculo', 'origen_clave' => null],
        ],
    ],
    [
        'codigo' => 'sch-c', 'nombre' => 'Schedule C', 'ficha' => 'completa', 'orden' => 3,
        'fuente_texto' => 'Schedule C de 2025',
        'lineas' => [['linea' => '1', 'descripcion' => 'Ingresos brutos', 'origen_tipo' => 'casilla', 'origen_clave' => '1099-nec:1']],
    ],
];
