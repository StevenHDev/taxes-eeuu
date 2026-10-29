<?php

/**
 * Ajustes del año fiscal 2026 sobre la copia de 2025 (catalogo-fiscal:copiar 2025 2026).
 *
 * Origen: docs/mejora-plataforma/documento-madre.md, sección 3 (tabla "Valores que cambian cada año"
 * y texto del bloque A1) y sección 4 (aviso de la revisión de diciembre de 2026 del 1099-NEC:
 * la compensación pasa de la casilla 1 a la 1a). Todo lo demás de 2026 queda pendiente.
 */
return [
    'parametros' => [
        'dependiente_calificado.limite_ingreso_bruto_pariente_calificado' => 5300,
        'edad.nacido_antes_de_mayor_65' => '1962-01-02',
    ],
    'casillas' => [
        '1099-nec' => ['1' => ['casilla' => '1a']],
    ],
    'lineas' => [
        'sch-c:1' => ['origen_clave' => '1099-nec:1a;1099-k:1a'],
    ],
    'preguntas' => [
        'A1' => ['fuente_texto' => 'Encabezado y firma del 1040 (la firma pide la ocupación). 1040 línea 12d: "nació antes del 2 de enero de 1962"'],
        // El límite del pariente calificado de 2026 (USD 5.300, tabla de la sección 3).
        'C13' => ['texto_es' => '¿Esta persona tuvo ingresos de menos de USD 5.300 en {anio}?'],
    ],
];
