<?php

// Catálogo mínimo para las pruebas de carga (CargaTest). No es contenido real.
return [
    [
        'codigo' => 'B1', 'bloque' => 'B', 'orden' => 1,
        'texto_es' => 'Al 31 de diciembre de {anio}, ¿cuál era tu estado civil?',
        'tipo_respuesta' => 'opciones',
        'opciones' => [['valor' => 'soltero', 'texto_es' => 'Nunca me casé'], ['valor' => 'casado', 'texto_es' => 'Casado']],
        'nivel' => 'entrada', 'fuente_texto' => 'Pub. 501', 'fuente_url' => 'https://www.irs.gov/publications/p501',
        'activa' => [['respuesta' => 'casado', 'objetivo' => 'pregunta:B2']],
    ],
    [
        'codigo' => 'B2', 'bloque' => 'B', 'orden' => 2,
        'texto_es' => '¿Estabas casado el último día del año?',
        'tipo_respuesta' => 'si_no', 'nivel' => 'seguimiento',
        'condicion' => ['pregunta' => 'B1', 'es' => 'casado'],
        'fuente_texto' => 'Pub. 501',
    ],
    [
        'codigo' => 'C2', 'bloque' => 'C', 'orden' => 2, 'por_persona' => true,
        'texto_es' => 'Fecha de nacimiento', 'tipo_respuesta' => 'fecha', 'nivel' => 'entrada',
        'efecto_es' => '< 17 → crédito por hijos (Schedule 8812)',
        'fuente_texto' => 'Pub. 501',
        'activa' => [['respuesta' => 'menor_17', 'objetivo' => 'formulario:sch-8812']],
    ],
    [
        'codigo' => 'D1', 'bloque' => 'D', 'orden' => 1,
        'texto_es' => '¿Recibiste salario como empleado en {anio}?',
        'tipo_respuesta' => 'si_no', 'nivel' => 'entrada', 'fuente_texto' => '1040 línea 1a',
        'activa' => [['respuesta' => 'si', 'objetivo' => 'documento:w2']],
    ],
];
