<?php

// Declaración conjunta donde solo UNO de los cónyuges es educador elegible: el tope sigue siendo USD 300 (el de USD 600 exige que los dos sean elegibles).
// Inferencia anotada: el texto del IRS condiciona los USD 600 a "both of you were eligible educators"; con uno solo se aplica el "up to $300" de la misma frase.
return [
    'codigo' => 'schedule1-educador-tope-mfj-uno',
    'anio' => 2025,
    'descripcion' => 'Matrimonio en conjunto en el que solo uno es maestro elegible, con USD 450 de gastos calificados: la línea 11 queda en USD 300.',
    'entradas' => [
        'estado_civil' => 'casado_conjunto',
        'educadores_elegibles' => 1,
        'gastos_calificados_conyuge_1' => '450',
    ],
    'pasos' => [
        [
            'formulario' => 'sch-1',
            'linea' => '11-gastos',
            'texto' => 'Gastos calificados del cónyuge educador: USD 450.',
            'valor' => '450',
            'fuente' => ['doc' => 'i1040gi', 'pagina' => 'p. 93 (Line 11, Educator Expenses)', 'url' => 'https://www.irs.gov/pub/irs-prior/i1040gi--2025.pdf'],
        ],
        [
            'formulario' => 'sch-1',
            'linea' => '11-tope',
            'texto' => 'Con declaración conjunta, el máximo de USD 600 aplica solo si los dos son educadores elegibles; aquí solo uno lo es: USD 300.',
            'valor' => '300',
            'fuente' => ['doc' => 'i1040gi', 'pagina' => 'p. 93 (Line 11, Educator Expenses)', 'url' => 'https://www.irs.gov/pub/irs-prior/i1040gi--2025.pdf'],
        ],
        [
            'formulario' => 'sch-1',
            'linea' => '11',
            'texto' => 'Línea 11 = el menor entre 450 y 300 = USD 300.',
            'valor' => '300',
            'fuente' => ['doc' => 'f1040s1', 'pagina' => 'p. 2 (línea 11, Educator expenses)', 'url' => 'https://www.irs.gov/pub/irs-prior/f1040s1--2025.pdf'],
        ],
    ],
    'lineas' => [
        'sch-1' => ['11' => '300'],
    ],
    'cubre' => ['sch-1:11'],
    'contraste' => null,
];
