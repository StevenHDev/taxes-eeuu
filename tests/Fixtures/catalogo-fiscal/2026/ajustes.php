<?php

return [
    'parametros' => [
        'dependiente_calificado.limite_ingreso_bruto_pariente_calificado' => 5300,
        'edad.nacido_antes_de_mayor_65' => '1962-01-02',
        'no.existe' => 1,
    ],
    'casillas' => ['1099-nec' => ['1' => ['casilla' => '1a']]],
    'lineas' => ['sch-c:1' => ['origen_clave' => '1099-nec:1a']],
    'preguntas' => ['D1' => ['fuente_texto' => '1040 línea 1a (2026)']],
];
