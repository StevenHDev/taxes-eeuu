<?php

/**
 * Reglas de validación del año fiscal 2025 (R-01 a R-13).
 *
 * Origen: docs/mejora-plataforma/documento-madre.md, sección 6.2 (tabla "Reglas para una persona
 * natural (año fiscal 2025)"). Descripción tomada de la columna "Regla" (sin las negritas de markdown);
 * fuente_texto viene de la columna "Referencia". Las referencias de tipo "Proceso" no tienen enlace IRS.
 */
return [
    ['codigo' => 'R-01', 'descripcion' => 'El SSN o ITIN del documento coincide con el de la persona (contribuyente, cónyuge o dependiente) y tiene formato válido', 'severidad' => 'bloqueante', 'orden' => 1, 'fuente_texto' => 'Encabezado del 1040', 'fuente_url' => 'https://www.irs.gov/pub/irs-pdf/f1040.pdf'],
    ['codigo' => 'R-02', 'descripcion' => 'Todo documento activado está validado o en no aplica con motivo aceptado', 'severidad' => 'bloqueante', 'orden' => 2, 'fuente_texto' => 'Proceso (sección 5)', 'fuente_url' => null],
    ['codigo' => 'R-03', 'descripcion' => 'Dato de una casilla clave con confianza menor de 0,90', 'severidad' => 'revision', 'orden' => 3, 'fuente_texto' => 'Proceso (umbral propuesto, a calibrar)', 'fuente_url' => null],
    ['codigo' => 'R-04', 'descripcion' => 'W-2: casilla 2 ≤ casilla 1; casilla 3 ≤ USD 176.100 (tope del Seguro Social 2025)', 'severidad' => 'bloqueante', 'orden' => 4, 'fuente_texto' => 'Instrucciones del 1040 de 2025', 'fuente_url' => 'https://www.irs.gov/pub/irs-pdf/i1040gi.pdf'],
    ['codigo' => 'R-05', 'descripcion' => 'Dos o más W-2 con Seguro Social retenido (casilla 4) que suma más de USD 10.918,20 → posible crédito por exceso retenido', 'severidad' => 'informativa', 'orden' => 5, 'fuente_texto' => 'Instrucciones del 1040; Schedule 3 línea 11', 'fuente_url' => 'https://www.irs.gov/pub/irs-pdf/i1040gi.pdf'],
    ['codigo' => 'R-06', 'descripcion' => 'El año del documento es el año fiscal que se declara', 'severidad' => 'bloqueante', 'orden' => 6, 'fuente_texto' => 'Proceso', 'fuente_url' => null],
    ['codigo' => 'R-07', 'descripcion' => 'El mismo archivo ya se subió (para este cliente o para otro)', 'severidad' => 'revision', 'orden' => 7, 'fuente_texto' => 'Proceso', 'fuente_url' => null],
    ['codigo' => 'R-08', 'descripcion' => 'Lo respondido contradice el documento. Ejemplos: dijo que no tuvo salario y subió un W-2; el W-2 trae casilla 10 y no marcó guardería; la persona del 1095-A no está en la declaración', 'severidad' => 'revision', 'orden' => 8, 'fuente_texto' => 'Sección 3 vs. sección 4', 'fuente_url' => null],
    ['codigo' => 'R-09', 'descripcion' => '1095-A con adelanto del crédito (columna C > 0) → el Form 8962 es obligatorio', 'severidad' => 'bloqueante', 'orden' => 9, 'fuente_texto' => 'Instrucciones del Form 8962 de 2025', 'fuente_url' => 'https://www.irs.gov/pub/irs-pdf/i8962.pdf'],
    ['codigo' => 'R-10', 'descripcion' => 'Ingresos del Schedule C menores que la suma de los 1099-NEC y 1099-K recibidos', 'severidad' => 'revision', 'orden' => 10, 'fuente_texto' => 'Schedule C, línea 1', 'fuente_url' => 'https://www.irs.gov/pub/irs-pdf/f1040sc.pdf'],
    ['codigo' => 'R-11', 'descripcion' => 'Hijo declarado para el crédito por hijos sin SSN válido para trabajar', 'severidad' => 'bloqueante', 'orden' => 11, 'fuente_texto' => 'Instrucciones del Schedule 8812', 'fuente_url' => 'https://www.irs.gov/pub/irs-pdf/i1040s8.pdf'],
    ['codigo' => 'R-12', 'descripcion' => 'Proveedor de cuidado sin SSN o EIN', 'severidad' => 'revision', 'orden' => 12, 'fuente_texto' => 'Form 2441, Parte I', 'fuente_url' => 'https://www.irs.gov/pub/irs-pdf/f2441.pdf'],
    ['codigo' => 'R-13', 'descripcion' => 'Variación de más de 25 % frente al año anterior por fuente de ingreso, o una fuente del año pasado que no aparece', 'severidad' => 'informativa', 'orden' => 13, 'fuente_texto' => 'Proceso (necesita la declaración anterior)', 'fuente_url' => null],
];
