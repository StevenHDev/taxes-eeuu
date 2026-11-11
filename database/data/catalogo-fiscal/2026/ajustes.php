<?php

/**
 * Ajustes del año fiscal 2026 sobre la copia de 2025 (catalogo-fiscal:copiar 2025 2026).
 *
 * Parámetros (6.1b, 9 nov. 2026): cada cifra sale de un documento oficial del IRS (irs.gov) y
 * lleva su fuente y su página; lo que el IRS no ha publicado, o no cabe en la forma actual del
 * parámetro, NO está aquí y queda pendiente en 2026 (ver "Pendientes" más abajo).
 *
 * Fuentes (todas descargadas de irs.gov el 9 nov. 2026; "p. N" es la página del PDF tal cual se
 * descarga: en los borradores de irs-dft/ la 1 es la carátula del IRS y el formulario empieza en la 2):
 *  - Rev. Proc. 2025-32 (ajustes por inflación de 2026, Bulletin 2025-45): https://www.irs.gov/pub/irs-drop/rp-25-32.pdf
 *  - Schedule 8812, Schedule SE y Schedule C de 2026 (versiones finales de irs-pdf/).
 *  - Borradores de 2026 (irs-dft/): Form 1040, Schedule 1-A, Form 2441 y sus instrucciones, Form 8959,
 *    Form 8960 y sus instrucciones, Form 8995, instrucciones del Form 8962, instrucciones del Schedule C
 *    y Form 4868.
 *
 * Pendientes (sin valor en 2026 a propósito):
 *  - schedule_c.millaje_estandar: 2026 tiene dos tarifas (72,5 centavos del 1 ene. al 30 jun. y 76 centavos
 *    del 1 jul. al 31 dic.; instrucciones del Schedule C de 2026, p. 2) y el parámetro es un solo número.
 *  - credito_cuidado_dependientes.agi_umbral_porcentaje_minimo: en 2026 el umbral es distinto para la declaración
 *    conjunta (USD 206.000) y para las demás (USD 103.000); el cálculo usa la tabla `credito_cuidado_dependientes.tabla`.
 *  - credito_ftc.umbral_de_minimis_soltero y _mfj: el borrador de 2026 del Schedule 3 y de las instrucciones del
 *    1040 no trae la cifra (las instrucciones del 1040 de 2026 aún no están publicadas).
 *  - self_employment_tax.maximo_seguro_social_retenido_por_persona: ningún documento del IRS de 2026 revisado trae la cifra.
 *  - credito_cuidado_dependientes.tope_beneficios_excluibles y additional_medicare_tax.tasa_medicare_regular:
 *    agregados en 2025 al cierre de la ola 3 (11 nov.); falta verificar su cifra en los formularios de 2026.
 *
 * Línea por línea, los formularios de 2026 cambian de número frente a 2025 (ver el informe de 6.1b):
 * `lineas` queda como estaba porque este mecanismo solo edita líneas que ya existen y no puede crear ni
 * renumerar sin chocar con las de 2025.
 *
 * Origen de lo demás: docs/mejora-plataforma/documento-madre.md, sección 3 (tabla "Valores que cambian
 * cada año" y texto del bloque A1) y sección 4 (aviso de la revisión de diciembre de 2026 del 1099-NEC:
 * la compensación pasa de la casilla 1 a la 1a).
 */
$rp = 'https://www.irs.gov/pub/irs-drop/rp-25-32.pdf';
$dft = fn (string $codigo) => "https://www.irs.gov/pub/irs-dft/{$codigo}--dft.pdf";
$final = fn (string $codigo) => "https://www.irs.gov/pub/irs-pdf/{$codigo}.pdf";

/** Un parámetro con su fuente: formato `['valor' => X, 'fuente_texto' => '… p. N', 'fuente_url' => '…']`. */
$p = fn (mixed $valor, string $fuenteTexto, string $fuenteUrl) => ['valor' => $valor, 'fuente_texto' => $fuenteTexto, 'fuente_url' => $fuenteUrl];

$ctc8812 = 'Schedule 8812 de 2026, p. 1';
$s1a = 'Schedule 1-A de 2026 (borrador)';
$g8959 = 'Form 8959 de 2026 (borrador), p. 2, línea 5';
$i8960 = 'Instrucciones del Form 8960 de 2026 (borrador), p. 3, "Threshold amounts"';
$i8962 = 'Instrucciones del Form 8962 de 2026 (borrador)';

return [
    'parametros' => [
        // Rev. Proc. 2025-32, sección 4.23 (p. 20).
        'dependiente_calificado.limite_ingreso_bruto_pariente_calificado' => $p(5300, 'Rev. Proc. 2025-32, sección 4.23, p. 20 ("the exemption amount referred to in § 152(d)(1)(B) is $5,300")', $rp),
        'edad.nacido_antes_de_mayor_65' => $p('1962-01-02', 'Form 1040 de 2026 (borrador), p. 3, línea 12d ("Were born before January 2, 1962")', $dft('f1040')),
        'vencimientos.presentacion' => $p('2027-04-15', 'Form 4868 de 2026 (borrador), p. 3, "When To File Form 4868" ("For a 2026 calendar-year return, this is April 15, 2027, for most people")', $dft('f4868')),
        'vencimientos.extension' => $p('2027-10-15', 'Form 4868 de 2026 (borrador), p. 3 ("6 months (October 15, 2027, for most calendar-year taxpayers)")', $dft('f4868')),

        // Deducción estándar: Rev. Proc. 2025-32, sección 4.14 (p. 18); el recuadro de la p. 3 del borrador del Form 1040 de 2026 trae las mismas cifras.
        'deduccion_estandar.monto_soltero' => $p(16100, 'Rev. Proc. 2025-32, sección 4.14(1), p. 18 (no casados, "Unmarried Individuals": $16,100); Form 1040 de 2026 (borrador), p. 3, recuadro "Standard deduction for"', $rp),
        'deduccion_estandar.monto_mfj' => $p(32200, 'Rev. Proc. 2025-32, sección 4.14(1), p. 18 (casados declarando juntos y cónyuge sobreviviente: $32,200); Form 1040 de 2026 (borrador), p. 3', $rp),
        'deduccion_estandar.monto_hoh' => $p(24150, 'Rev. Proc. 2025-32, sección 4.14(1), p. 18 (cabeza de familia: $24,150); Form 1040 de 2026 (borrador), p. 3', $rp),
        'deduccion_estandar.monto_mfs' => $p(16100, 'Rev. Proc. 2025-32, sección 4.14(1), p. 18 (casados declarando por separado: $16,100); Form 1040 de 2026 (borrador), p. 3', $rp),
        'deduccion_estandar.adicional_65_ciego_casado' => $p(1650, 'Rev. Proc. 2025-32, sección 4.14(3), p. 18 ("the additional standard deduction amount under § 63(f) for the aged or the blind is $1,650")', $rp),
        'deduccion_estandar.adicional_65_ciego_soltero_hoh' => $p(2050, 'Rev. Proc. 2025-32, sección 4.14(3), p. 18 ("increased to $2,050 if the individual is also unmarried and not a surviving spouse")', $rp),
        'deduccion_estandar.dependiente_minimo' => $p(1350, 'Rev. Proc. 2025-32, sección 4.14(2), p. 18 ("the greater of (1) $1,350, or (2) the sum of $450 and the individual\'s earned income")', $rp),
        'deduccion_estandar.dependiente_adicional_ingreso_trabajo' => $p(450, 'Rev. Proc. 2025-32, sección 4.14(2), p. 18 ("the sum of $450 and the individual\'s earned income")', $rp),

        // Crédito por hijos (CTC), por otros dependientes (ODC) y ACTC: Schedule 8812 de 2026 (final) y Rev. Proc. 2025-32, sección 4.05 (p. 14).
        'credito_ctc.monto_por_dependiente' => $p(2200, "{$ctc8812}, línea 5 (\"Multiply line 4 by \$2,200\"); Rev. Proc. 2025-32, sección 4.05(1), p. 14", $final('f1040s8')),
        'credito_ctc.phase_out_umbral_soltero' => $p(200000, "{$ctc8812}, línea 9 (\"All other filing statuses—\$200,000\")", $final('f1040s8')),
        'credito_ctc.phase_out_umbral_mfj' => $p(400000, "{$ctc8812}, línea 9 (\"Married filing jointly—\$400,000\")", $final('f1040s8')),
        'credito_ctc.reduccion_por_1000_exceso' => $p(50, "{$ctc8812}, líneas 10 y 11 (múltiplo de \$1,000 hacia arriba; \"Multiply line 10 by 5% (0.05)\")", $final('f1040s8')),
        'credito_odc.monto_por_dependiente' => $p(500, "{$ctc8812}, línea 7 (\"Multiply line 6 by \$500\")", $final('f1040s8')),
        'credito_odc.phase_out_umbral_soltero' => $p(200000, "{$ctc8812}, línea 9 (\"All other filing statuses—\$200,000\")", $final('f1040s8')),
        'credito_odc.phase_out_umbral_mfj' => $p(400000, "{$ctc8812}, línea 9 (\"Married filing jointly—\$400,000\")", $final('f1040s8')),
        'credito_odc.reduccion_por_1000_exceso' => $p(50, "{$ctc8812}, líneas 10 y 11", $final('f1040s8')),
        'credito_actc.tope_reembolsable_por_hijo' => $p(1700, 'Schedule 8812 de 2026, p. 2, línea 16b ("x $1,700"); Rev. Proc. 2025-32, sección 4.05(2), p. 14', $final('f1040s8')),
        'credito_actc.umbral_ingreso_trabajo' => $p(2500, 'Schedule 8812 de 2026, p. 2, línea 19 ("more than $2,500")', $final('f1040s8')),
        'credito_actc.tasa_ingreso_trabajo' => $p('0.15', 'Schedule 8812 de 2026, p. 2, línea 20 ("15% (0.15)")', $final('f1040s8')),

        // Crédito por cuidado de hijos y dependientes (Form 2441). En 2026 el porcentaje máximo sube de 35 % a 50 % y la
        // tabla tiene dos columnas (conjunta y demás): cada fila lleva `hasta` (demás) y `hasta_conjunta`.
        'credito_cuidado_dependientes.tope_gastos_1_persona' => $p(3000, 'Form 2441 de 2026 (borrador), p. 2, línea 3 ("Don\'t enter more than $3,000 if you had one qualifying person")', $dft('f2441')),
        'credito_cuidado_dependientes.tope_gastos_2_mas_personas' => $p(6000, 'Form 2441 de 2026 (borrador), p. 2, línea 3 ("or $6,000 if you had two or more persons")', $dft('f2441')),
        'credito_cuidado_dependientes.porcentaje_maximo' => $p(0.50, 'Instrucciones del Form 2441 de 2026 (borrador), p. 2, "What\'s New" (de 35 % a 50 %) y p. 6, "2026 Phaseout Schedule" ($0 a $15,000: .50)', $dft('i2441')),
        'credito_cuidado_dependientes.porcentaje_minimo' => $p(0.20, 'Instrucciones del Form 2441 de 2026 (borrador), p. 6, "2026 Phaseout Schedule" (última fila: .20)', $dft('i2441')),
        'credito_cuidado_dependientes.agi_umbral_porcentaje_maximo' => $p(15000, 'Instrucciones del Form 2441 de 2026 (borrador), p. 6, "2026 Phaseout Schedule" ($0 a $15,000: .50, igual para conjunta y demás)', $dft('i2441')),
        'credito_cuidado_dependientes.edad_limite_dependiente' => $p(13, 'Instrucciones del Form 2441 de 2026 (borrador), p. 2 ("A qualifying child under age 13")', $dft('i2441')),
        'credito_cuidado_dependientes.tabla' => $p([
            ['hasta' => 15000, 'hasta_conjunta' => 15000, 'decimal' => '0.50'],
            ['hasta' => 17000, 'hasta_conjunta' => 17000, 'decimal' => '0.49'],
            ['hasta' => 19000, 'hasta_conjunta' => 19000, 'decimal' => '0.48'],
            ['hasta' => 21000, 'hasta_conjunta' => 21000, 'decimal' => '0.47'],
            ['hasta' => 23000, 'hasta_conjunta' => 23000, 'decimal' => '0.46'],
            ['hasta' => 25000, 'hasta_conjunta' => 25000, 'decimal' => '0.45'],
            ['hasta' => 27000, 'hasta_conjunta' => 27000, 'decimal' => '0.44'],
            ['hasta' => 29000, 'hasta_conjunta' => 29000, 'decimal' => '0.43'],
            ['hasta' => 31000, 'hasta_conjunta' => 31000, 'decimal' => '0.42'],
            ['hasta' => 33000, 'hasta_conjunta' => 33000, 'decimal' => '0.41'],
            ['hasta' => 35000, 'hasta_conjunta' => 35000, 'decimal' => '0.40'],
            ['hasta' => 37000, 'hasta_conjunta' => 37000, 'decimal' => '0.39'],
            ['hasta' => 39000, 'hasta_conjunta' => 39000, 'decimal' => '0.38'],
            ['hasta' => 41000, 'hasta_conjunta' => 41000, 'decimal' => '0.37'],
            ['hasta' => 43000, 'hasta_conjunta' => 43000, 'decimal' => '0.36'],
            ['hasta' => 75000, 'hasta_conjunta' => 150000, 'decimal' => '0.35'],
            ['hasta' => 77000, 'hasta_conjunta' => 154000, 'decimal' => '0.34'],
            ['hasta' => 79000, 'hasta_conjunta' => 158000, 'decimal' => '0.33'],
            ['hasta' => 81000, 'hasta_conjunta' => 162000, 'decimal' => '0.32'],
            ['hasta' => 83000, 'hasta_conjunta' => 166000, 'decimal' => '0.31'],
            ['hasta' => 85000, 'hasta_conjunta' => 170000, 'decimal' => '0.30'],
            ['hasta' => 87000, 'hasta_conjunta' => 174000, 'decimal' => '0.29'],
            ['hasta' => 89000, 'hasta_conjunta' => 178000, 'decimal' => '0.28'],
            ['hasta' => 91000, 'hasta_conjunta' => 182000, 'decimal' => '0.27'],
            ['hasta' => 93000, 'hasta_conjunta' => 186000, 'decimal' => '0.26'],
            ['hasta' => 95000, 'hasta_conjunta' => 190000, 'decimal' => '0.25'],
            ['hasta' => 97000, 'hasta_conjunta' => 194000, 'decimal' => '0.24'],
            ['hasta' => 99000, 'hasta_conjunta' => 198000, 'decimal' => '0.23'],
            ['hasta' => 101000, 'hasta_conjunta' => 202000, 'decimal' => '0.22'],
            ['hasta' => 103000, 'hasta_conjunta' => 206000, 'decimal' => '0.21'],
            ['hasta' => null, 'hasta_conjunta' => null, 'decimal' => '0.20'],
        ], 'Instrucciones del Form 2441 de 2026 (borrador), p. 6, "2026 Phaseout Schedule" (la fila aplica si la línea 7 es mayor que la fila anterior y no pasa de `hasta` para las demás declaraciones o de `hasta_conjunta` para la conjunta)', $dft('i2441')),

        // Tramos del impuesto: Rev. Proc. 2025-32, sección 4.01, Tablas 1 a 4 (p. 10 a 12).
        'tax_brackets.single' => $p([
            ['desde' => 0, 'tasa' => 0.10],
            ['desde' => 12400, 'tasa' => 0.12],
            ['desde' => 50400, 'tasa' => 0.22],
            ['desde' => 105700, 'tasa' => 0.24],
            ['desde' => 201775, 'tasa' => 0.32],
            ['desde' => 256225, 'tasa' => 0.35],
            ['desde' => 640600, 'tasa' => 0.37],
        ], 'Rev. Proc. 2025-32, sección 4.01, Tabla 3 (p. 11)', $rp),
        'tax_brackets.mfj' => $p([
            ['desde' => 0, 'tasa' => 0.10],
            ['desde' => 24800, 'tasa' => 0.12],
            ['desde' => 100800, 'tasa' => 0.22],
            ['desde' => 211400, 'tasa' => 0.24],
            ['desde' => 403550, 'tasa' => 0.32],
            ['desde' => 512450, 'tasa' => 0.35],
            ['desde' => 768700, 'tasa' => 0.37],
        ], 'Rev. Proc. 2025-32, sección 4.01, Tabla 1 (p. 10)', $rp),
        'tax_brackets.hoh' => $p([
            ['desde' => 0, 'tasa' => 0.10],
            ['desde' => 17700, 'tasa' => 0.12],
            ['desde' => 67450, 'tasa' => 0.22],
            ['desde' => 105700, 'tasa' => 0.24],
            ['desde' => 201750, 'tasa' => 0.32],
            ['desde' => 256200, 'tasa' => 0.35],
            ['desde' => 640600, 'tasa' => 0.37],
        ], 'Rev. Proc. 2025-32, sección 4.01, Tabla 2 (p. 10 y 11)', $rp),
        'tax_brackets.mfs' => $p([
            ['desde' => 0, 'tasa' => 0.10],
            ['desde' => 12400, 'tasa' => 0.12],
            ['desde' => 50400, 'tasa' => 0.22],
            ['desde' => 105700, 'tasa' => 0.24],
            ['desde' => 201775, 'tasa' => 0.32],
            ['desde' => 256225, 'tasa' => 0.35],
            ['desde' => 384350, 'tasa' => 0.37],
        ], 'Rev. Proc. 2025-32, sección 4.01, Tabla 4 (p. 11 y 12)', $rp),

        // Schedule SE de 2026 (final).
        'self_employment_tax.tope_salario_social_security' => $p(184500, 'Schedule SE de 2026, p. 1, línea 7 ($184,500)', $final('f1040sse')),
        'self_employment_tax.tasa_seguro_social' => $p('0.124', 'Schedule SE de 2026, p. 1, línea 10 ("12.4% (0.124)")', $final('f1040sse')),
        'self_employment_tax.tasa_medicare' => $p('0.029', 'Schedule SE de 2026, p. 1, línea 11 ("2.9% (0.029)")', $final('f1040sse')),
        'self_employment_tax.factor_ingreso_neto' => $p('0.9235', 'Schedule SE de 2026, p. 1, línea 4a ("92.35% (0.9235)")', $final('f1040sse')),
        'self_employment_tax.minimo_ingreso_neto' => $p(400, 'Schedule SE de 2026, p. 1, línea 4c ("If less than $400, stop; you don\'t owe self-employment tax")', $final('f1040sse')),
        'self_employment_tax.factor_deduccion_mitad' => $p('0.50', 'Schedule SE de 2026, p. 1, línea 13 ("50% (0.50)")', $final('f1040sse')),

        // Impuesto adicional de Medicare (Form 8959) e impuesto sobre el ingreso neto de inversión (Form 8960).
        'additional_medicare_tax.tasa' => $p('0.009', 'Form 8959 de 2026 (borrador), p. 2, línea 7 ("0.9% (0.009)")', $dft('f8959')),
        'additional_medicare_tax.umbral_soltero' => $p(200000, "{$g8959} (\"Single, Head of household, or Qualifying surviving spouse \$200,000\")", $dft('f8959')),
        'additional_medicare_tax.umbral_mfj' => $p(250000, "{$g8959} (\"Married filing jointly \$250,000\")", $dft('f8959')),
        'additional_medicare_tax.umbral_mfs' => $p(125000, "{$g8959} (\"Married filing separately \$125,000\")", $dft('f8959')),
        'niit.tasa' => $p('0.038', 'Form 8960 de 2026 (borrador), p. 2, línea 17 ("3.8% (0.038)")', $dft('f8960')),
        'niit.umbral_soltero' => $p(200000, "{$i8960} (\"Single or Head of household is \$200,000\")", $dft('i8960')),
        'niit.umbral_mfj' => $p(250000, "{$i8960} (casados declarando juntos y cónyuge sobreviviente: \$250,000)", $dft('i8960')),
        'niit.umbral_mfs' => $p(125000, "{$i8960} (\"Married filing separately is \$125,000\")", $dft('i8960')),

        // Deducción QBI: Rev. Proc. 2025-32, sección 4.26 (p. 22) y Form 8995 de 2026 (borrador). Ojo: en 2026 el umbral de
        // casado por separado (USD 201.775) ya no es igual al de soltero (USD 201.750); no hay un parámetro `qbi.umbral_mfs`.
        'qbi.umbral_soltero' => $p(201750, 'Rev. Proc. 2025-32, sección 4.26, p. 22 (todos los demás: $201,750; casado por separado $201,775); Form 8995 de 2026 (borrador), p. 2, encabezado', $rp),
        'qbi.umbral_mfj' => $p(403500, 'Rev. Proc. 2025-32, sección 4.26, p. 22 (casados declarando juntos: $403,500); Form 8995 de 2026 (borrador), p. 2, encabezado', $rp),
        'qbi.tasa' => $p('0.20', 'Form 8995 de 2026 (borrador), p. 2, líneas 5 y 9 ("20% (0.20)")', $dft('f8995')),

        // Crédito por ingreso del trabajo (EIC): Rev. Proc. 2025-32, sección 4.06 (p. 15). Las tasas de entrada y de reducción son
        // las del estatuto (26 U.S.C. § 32(b)(1)); se comprobó que coinciden con maximo / ingreso_ganado y
        // maximo / (fin - inicio de la reducción) de la tabla de la Rev. Proc.
        'credito_eic.por_hijos' => $p([
            ['hasta' => 0, 'hijos' => '0', 'tasa_entrada' => '0.0765', 'tasa_reduccion' => '0.0765', 'ingreso_ganado' => 8680, 'maximo' => 664, 'inicio_reduccion_mfj' => 18140, 'fin_reduccion_mfj' => 26820, 'inicio_reduccion_otros' => 10860, 'fin_reduccion_otros' => 19540],
            ['hasta' => 1, 'hijos' => '1', 'tasa_entrada' => '0.34', 'tasa_reduccion' => '0.1598', 'ingreso_ganado' => 13020, 'maximo' => 4427, 'inicio_reduccion_mfj' => 31160, 'fin_reduccion_mfj' => 58863, 'inicio_reduccion_otros' => 23890, 'fin_reduccion_otros' => 51593],
            ['hasta' => 2, 'hijos' => '2', 'tasa_entrada' => '0.40', 'tasa_reduccion' => '0.2106', 'ingreso_ganado' => 18290, 'maximo' => 7316, 'inicio_reduccion_mfj' => 31160, 'fin_reduccion_mfj' => 65899, 'inicio_reduccion_otros' => 23890, 'fin_reduccion_otros' => 58629],
            ['hasta' => null, 'hijos' => '3+', 'tasa_entrada' => '0.45', 'tasa_reduccion' => '0.2106', 'ingreso_ganado' => 18290, 'maximo' => 8231, 'inicio_reduccion_mfj' => 31160, 'fin_reduccion_mfj' => 70244, 'inicio_reduccion_otros' => 23890, 'fin_reduccion_otros' => 62974],
        ], 'Rev. Proc. 2025-32, sección 4.06(1), p. 15 (tabla "Number of Qualifying Children"); tasas de 26 U.S.C. § 32(b)(1), comprobadas contra esa tabla', $rp),
        'credito_eic.tope_ingreso_inversion' => $p(12200, 'Rev. Proc. 2025-32, sección 4.06(2), p. 15 ("exceeds $12,200")', $rp),

        // Schedule 1-A de 2026 (borrador). La numeración de las líneas cambió frente a 2025; las cifras son las mismas que en 2025.
        'schedule_1a.propinas_tope' => $p(25000, "{$s1a}, p. 2, línea 9 (\"the smaller of the amount on line 8 or \$25,000\")", $dft('f1040s1a')),
        'schedule_1a.propinas_umbral_magi_soltero' => $p(150000, "{$s1a}, p. 2, línea 11 (\"Enter \$150,000\")", $dft('f1040s1a')),
        'schedule_1a.propinas_umbral_magi_mfj' => $p(300000, "{$s1a}, p. 2, línea 11 (\"(\$300,000 if married filing jointly)\")", $dft('f1040s1a')),
        'schedule_1a.propinas_reduccion_por_1000' => $p(100, "{$s1a}, p. 2, líneas 13 y 14 (\"Divide line 12 by \$1,000\"; \"Multiply line 13 by \$100\")", $dft('f1040s1a')),
        'schedule_1a.horas_extra_tope_soltero' => $p(12500, "{$s1a}, p. 3, línea 21 (\"the smaller of the amount on line 20 or \$12,500\")", $dft('f1040s1a')),
        'schedule_1a.horas_extra_tope_mfj' => $p(25000, "{$s1a}, p. 3, línea 21 (\"(\$25,000 if married filing jointly)\")", $dft('f1040s1a')),
        'schedule_1a.horas_extra_umbral_magi_soltero' => $p(150000, "{$s1a}, p. 3, línea 23 (\"Enter \$150,000\")", $dft('f1040s1a')),
        'schedule_1a.horas_extra_umbral_magi_mfj' => $p(300000, "{$s1a}, p. 3, línea 23 (\"(\$300,000 if married filing jointly)\")", $dft('f1040s1a')),
        'schedule_1a.horas_extra_reduccion_por_1000' => $p(100, "{$s1a}, p. 3, líneas 25 y 26 (\"Divide line 24 by \$1,000\"; \"Multiply line 25 by \$100\")", $dft('f1040s1a')),
        'schedule_1a.vehiculo_tope' => $p(10000, "{$s1a}, p. 4, línea 30 (\"the smaller of the amount on line 29 or \$10,000\")", $dft('f1040s1a')),
        'schedule_1a.vehiculo_umbral_magi_soltero' => $p(100000, "{$s1a}, p. 4, línea 32 (\"Enter \$100,000\")", $dft('f1040s1a')),
        'schedule_1a.vehiculo_umbral_magi_mfj' => $p(200000, "{$s1a}, p. 4, línea 32 (\"(\$200,000 if married filing jointly)\")", $dft('f1040s1a')),
        'schedule_1a.vehiculo_reduccion_por_1000' => $p(200, "{$s1a}, p. 4, líneas 34 y 35 (\"Multiply line 34 by \$200\")", $dft('f1040s1a')),
        'schedule_1a.mayores_tope' => $p(6000, "{$s1a}, p. 4, líneas 39 y 41 (\"Subtract line 40 from \$6,000\")", $dft('f1040s1a')),
        'schedule_1a.mayores_umbral_magi_soltero' => $p(75000, "{$s1a}, p. 4, línea 38 (\"Enter \$75,000\")", $dft('f1040s1a')),
        'schedule_1a.mayores_umbral_magi_mfj' => $p(150000, "{$s1a}, p. 4, línea 38 (\"(\$150,000 if married filing jointly)\")", $dft('f1040s1a')),
        'schedule_1a.mayores_tasa_reduccion' => $p('0.06', "{$s1a}, p. 4, línea 40 (\"Multiply line 39 by 6% (0.06)\")", $dft('f1040s1a')),

        // Crédito de la prima (Form 8962): para 2026 se usan las líneas federales de pobreza de 2025 (instrucciones, p. 10).
        'credito_prima_salud.linea_pobreza_48_estados' => $p([
            ['hasta' => 1, 'monto' => 15650],
            ['hasta' => 2, 'monto' => 21150],
            ['hasta' => 3, 'monto' => 26650],
            ['hasta' => 4, 'monto' => 32150],
            ['hasta' => 5, 'monto' => 37650],
            ['hasta' => 6, 'monto' => 43150],
            ['hasta' => 7, 'monto' => 48650],
            ['hasta' => 8, 'monto' => 54150],
        ], "{$i8962}, p. 10, Tabla 1-1 (\"For 2026, the 2025 federal poverty lines are used\")", $dft('i8962')),
        'credito_prima_salud.linea_pobreza_48_estados_adicional' => $p(5500, "{$i8962}, p. 10, nota de la Tabla 1-1 (\"add \$5,500 for each additional person\")", $dft('i8962')),
        'credito_prima_salud.linea_pobreza_alaska' => $p([
            ['hasta' => 1, 'monto' => 19550],
            ['hasta' => 2, 'monto' => 26430],
            ['hasta' => 3, 'monto' => 33310],
            ['hasta' => 4, 'monto' => 40190],
            ['hasta' => 5, 'monto' => 47070],
            ['hasta' => 6, 'monto' => 53950],
            ['hasta' => 7, 'monto' => 60830],
            ['hasta' => 8, 'monto' => 67710],
        ], "{$i8962}, p. 10, Tabla 1-2", $dft('i8962')),
        'credito_prima_salud.linea_pobreza_alaska_adicional' => $p(6880, "{$i8962}, p. 10, nota de la Tabla 1-2 (\"add \$6,880 for each additional person\")", $dft('i8962')),
        'credito_prima_salud.linea_pobreza_hawaii' => $p([
            ['hasta' => 1, 'monto' => 17990],
            ['hasta' => 2, 'monto' => 24320],
            ['hasta' => 3, 'monto' => 30650],
            ['hasta' => 4, 'monto' => 36980],
            ['hasta' => 5, 'monto' => 43310],
            ['hasta' => 6, 'monto' => 49640],
            ['hasta' => 7, 'monto' => 55970],
            ['hasta' => 8, 'monto' => 62300],
        ], "{$i8962}, p. 10, Tabla 1-3", $dft('i8962')),
        'credito_prima_salud.linea_pobreza_hawaii_adicional' => $p(6330, "{$i8962}, p. 10, nota de la Tabla 1-3 (\"add \$6,330 for each additional person\")", $dft('i8962')),
        // Tabla 2: "less than 133" = 0.0210; de 133 a 299 una fila por porcentaje; "300 thru 400" = 0.0996. Con más de 400 % ya no hay crédito (p. 2, "What's New"), por eso la tabla termina en 400.
        'credito_prima_salud.tabla_cifra_aplicable' => $p([
            ['hasta' => 132, 'decimal' => '0.0210'],
            ['hasta' => 133, 'decimal' => '0.0314'],
            ['hasta' => 134, 'decimal' => '0.0320'],
            ['hasta' => 135, 'decimal' => '0.0326'],
            ['hasta' => 136, 'decimal' => '0.0333'],
            ['hasta' => 137, 'decimal' => '0.0339'],
            ['hasta' => 138, 'decimal' => '0.0345'],
            ['hasta' => 139, 'decimal' => '0.0351'],
            ['hasta' => 140, 'decimal' => '0.0357'],
            ['hasta' => 141, 'decimal' => '0.0363'],
            ['hasta' => 142, 'decimal' => '0.0370'],
            ['hasta' => 143, 'decimal' => '0.0376'],
            ['hasta' => 144, 'decimal' => '0.0382'],
            ['hasta' => 145, 'decimal' => '0.0388'],
            ['hasta' => 146, 'decimal' => '0.0394'],
            ['hasta' => 147, 'decimal' => '0.0400'],
            ['hasta' => 148, 'decimal' => '0.0407'],
            ['hasta' => 149, 'decimal' => '0.0413'],
            ['hasta' => 150, 'decimal' => '0.0419'],
            ['hasta' => 151, 'decimal' => '0.0424'],
            ['hasta' => 152, 'decimal' => '0.0429'],
            ['hasta' => 153, 'decimal' => '0.0433'],
            ['hasta' => 154, 'decimal' => '0.0438'],
            ['hasta' => 155, 'decimal' => '0.0443'],
            ['hasta' => 156, 'decimal' => '0.0448'],
            ['hasta' => 157, 'decimal' => '0.0453'],
            ['hasta' => 158, 'decimal' => '0.0458'],
            ['hasta' => 159, 'decimal' => '0.0462'],
            ['hasta' => 160, 'decimal' => '0.0467'],
            ['hasta' => 161, 'decimal' => '0.0472'],
            ['hasta' => 162, 'decimal' => '0.0477'],
            ['hasta' => 163, 'decimal' => '0.0482'],
            ['hasta' => 164, 'decimal' => '0.0486'],
            ['hasta' => 165, 'decimal' => '0.0491'],
            ['hasta' => 166, 'decimal' => '0.0496'],
            ['hasta' => 167, 'decimal' => '0.0501'],
            ['hasta' => 168, 'decimal' => '0.0506'],
            ['hasta' => 169, 'decimal' => '0.0511'],
            ['hasta' => 170, 'decimal' => '0.0515'],
            ['hasta' => 171, 'decimal' => '0.0520'],
            ['hasta' => 172, 'decimal' => '0.0525'],
            ['hasta' => 173, 'decimal' => '0.0530'],
            ['hasta' => 174, 'decimal' => '0.0535'],
            ['hasta' => 175, 'decimal' => '0.0540'],
            ['hasta' => 176, 'decimal' => '0.0544'],
            ['hasta' => 177, 'decimal' => '0.0549'],
            ['hasta' => 178, 'decimal' => '0.0554'],
            ['hasta' => 179, 'decimal' => '0.0559'],
            ['hasta' => 180, 'decimal' => '0.0564'],
            ['hasta' => 181, 'decimal' => '0.0568'],
            ['hasta' => 182, 'decimal' => '0.0573'],
            ['hasta' => 183, 'decimal' => '0.0578'],
            ['hasta' => 184, 'decimal' => '0.0583'],
            ['hasta' => 185, 'decimal' => '0.0588'],
            ['hasta' => 186, 'decimal' => '0.0593'],
            ['hasta' => 187, 'decimal' => '0.0597'],
            ['hasta' => 188, 'decimal' => '0.0602'],
            ['hasta' => 189, 'decimal' => '0.0607'],
            ['hasta' => 190, 'decimal' => '0.0612'],
            ['hasta' => 191, 'decimal' => '0.0617'],
            ['hasta' => 192, 'decimal' => '0.0621'],
            ['hasta' => 193, 'decimal' => '0.0626'],
            ['hasta' => 194, 'decimal' => '0.0631'],
            ['hasta' => 195, 'decimal' => '0.0636'],
            ['hasta' => 196, 'decimal' => '0.0641'],
            ['hasta' => 197, 'decimal' => '0.0646'],
            ['hasta' => 198, 'decimal' => '0.0650'],
            ['hasta' => 199, 'decimal' => '0.0655'],
            ['hasta' => 200, 'decimal' => '0.0660'],
            ['hasta' => 201, 'decimal' => '0.0664'],
            ['hasta' => 202, 'decimal' => '0.0667'],
            ['hasta' => 203, 'decimal' => '0.0671'],
            ['hasta' => 204, 'decimal' => '0.0675'],
            ['hasta' => 205, 'decimal' => '0.0678'],
            ['hasta' => 206, 'decimal' => '0.0682'],
            ['hasta' => 207, 'decimal' => '0.0686'],
            ['hasta' => 208, 'decimal' => '0.0689'],
            ['hasta' => 209, 'decimal' => '0.0693'],
            ['hasta' => 210, 'decimal' => '0.0697'],
            ['hasta' => 211, 'decimal' => '0.0700'],
            ['hasta' => 212, 'decimal' => '0.0704'],
            ['hasta' => 213, 'decimal' => '0.0708'],
            ['hasta' => 214, 'decimal' => '0.0712'],
            ['hasta' => 215, 'decimal' => '0.0715'],
            ['hasta' => 216, 'decimal' => '0.0719'],
            ['hasta' => 217, 'decimal' => '0.0723'],
            ['hasta' => 218, 'decimal' => '0.0726'],
            ['hasta' => 219, 'decimal' => '0.0730'],
            ['hasta' => 220, 'decimal' => '0.0734'],
            ['hasta' => 221, 'decimal' => '0.0737'],
            ['hasta' => 222, 'decimal' => '0.0741'],
            ['hasta' => 223, 'decimal' => '0.0745'],
            ['hasta' => 224, 'decimal' => '0.0748'],
            ['hasta' => 225, 'decimal' => '0.0752'],
            ['hasta' => 226, 'decimal' => '0.0756'],
            ['hasta' => 227, 'decimal' => '0.0759'],
            ['hasta' => 228, 'decimal' => '0.0763'],
            ['hasta' => 229, 'decimal' => '0.0767'],
            ['hasta' => 230, 'decimal' => '0.0770'],
            ['hasta' => 231, 'decimal' => '0.0774'],
            ['hasta' => 232, 'decimal' => '0.0778'],
            ['hasta' => 233, 'decimal' => '0.0781'],
            ['hasta' => 234, 'decimal' => '0.0785'],
            ['hasta' => 235, 'decimal' => '0.0789'],
            ['hasta' => 236, 'decimal' => '0.0792'],
            ['hasta' => 237, 'decimal' => '0.0796'],
            ['hasta' => 238, 'decimal' => '0.0800'],
            ['hasta' => 239, 'decimal' => '0.0804'],
            ['hasta' => 240, 'decimal' => '0.0807'],
            ['hasta' => 241, 'decimal' => '0.0811'],
            ['hasta' => 242, 'decimal' => '0.0815'],
            ['hasta' => 243, 'decimal' => '0.0818'],
            ['hasta' => 244, 'decimal' => '0.0822'],
            ['hasta' => 245, 'decimal' => '0.0826'],
            ['hasta' => 246, 'decimal' => '0.0829'],
            ['hasta' => 247, 'decimal' => '0.0833'],
            ['hasta' => 248, 'decimal' => '0.0837'],
            ['hasta' => 249, 'decimal' => '0.0840'],
            ['hasta' => 250, 'decimal' => '0.0844'],
            ['hasta' => 251, 'decimal' => '0.0847'],
            ['hasta' => 252, 'decimal' => '0.0850'],
            ['hasta' => 253, 'decimal' => '0.0853'],
            ['hasta' => 254, 'decimal' => '0.0856'],
            ['hasta' => 255, 'decimal' => '0.0859'],
            ['hasta' => 256, 'decimal' => '0.0862'],
            ['hasta' => 257, 'decimal' => '0.0865'],
            ['hasta' => 258, 'decimal' => '0.0868'],
            ['hasta' => 259, 'decimal' => '0.0871'],
            ['hasta' => 260, 'decimal' => '0.0874'],
            ['hasta' => 261, 'decimal' => '0.0877'],
            ['hasta' => 262, 'decimal' => '0.0880'],
            ['hasta' => 263, 'decimal' => '0.0884'],
            ['hasta' => 264, 'decimal' => '0.0887'],
            ['hasta' => 265, 'decimal' => '0.0890'],
            ['hasta' => 266, 'decimal' => '0.0893'],
            ['hasta' => 267, 'decimal' => '0.0896'],
            ['hasta' => 268, 'decimal' => '0.0899'],
            ['hasta' => 269, 'decimal' => '0.0902'],
            ['hasta' => 270, 'decimal' => '0.0905'],
            ['hasta' => 271, 'decimal' => '0.0908'],
            ['hasta' => 272, 'decimal' => '0.0911'],
            ['hasta' => 273, 'decimal' => '0.0914'],
            ['hasta' => 274, 'decimal' => '0.0917'],
            ['hasta' => 275, 'decimal' => '0.0920'],
            ['hasta' => 276, 'decimal' => '0.0923'],
            ['hasta' => 277, 'decimal' => '0.0926'],
            ['hasta' => 278, 'decimal' => '0.0929'],
            ['hasta' => 279, 'decimal' => '0.0932'],
            ['hasta' => 280, 'decimal' => '0.0935'],
            ['hasta' => 281, 'decimal' => '0.0938'],
            ['hasta' => 282, 'decimal' => '0.0941'],
            ['hasta' => 283, 'decimal' => '0.0944'],
            ['hasta' => 284, 'decimal' => '0.0947'],
            ['hasta' => 285, 'decimal' => '0.0950'],
            ['hasta' => 286, 'decimal' => '0.0953'],
            ['hasta' => 287, 'decimal' => '0.0956'],
            ['hasta' => 288, 'decimal' => '0.0960'],
            ['hasta' => 289, 'decimal' => '0.0963'],
            ['hasta' => 290, 'decimal' => '0.0966'],
            ['hasta' => 291, 'decimal' => '0.0969'],
            ['hasta' => 292, 'decimal' => '0.0972'],
            ['hasta' => 293, 'decimal' => '0.0975'],
            ['hasta' => 294, 'decimal' => '0.0978'],
            ['hasta' => 295, 'decimal' => '0.0981'],
            ['hasta' => 296, 'decimal' => '0.0984'],
            ['hasta' => 297, 'decimal' => '0.0987'],
            ['hasta' => 298, 'decimal' => '0.0990'],
            ['hasta' => 299, 'decimal' => '0.0993'],
            ['hasta' => 400, 'decimal' => '0.0996'],
        ], "{$i8962}, p. 12, Tabla 2 \"Applicable Figure\" (la tabla termina en 400 %: con más no hay crédito, p. 2 \"What's New\")", $dft('i8962')),
        // Para 2026 el IRS eliminó los topes de devolución del adelanto (instrucciones, p. 2, "Repayment limitation for excess advance premium tax credit (APTC) eliminated"; ya no existe la Tabla 5): una sola fila "sin límite".
        'credito_prima_salud.tabla_tope_devolucion' => $p([
            ['hasta' => null, 'soltero' => null, 'otros' => null],
        ], "{$i8962}, p. 2, \"What's New\": \"if the advance payments of the PTC ... exceed the PTC allowed for the year, the taxpayer must repay the full excess amount\" (sin Tabla 5)", $dft('i8962')),

        // Schedule C (instrucciones de 2026, borrador) y gastos de educador (Rev. Proc. 2025-32, sección 4.12, p. 17).
        'schedule_c.comidas_porcentaje_deducible' => $p('0.50', 'Instrucciones del Schedule C de 2026 (borrador), p. 11, línea 24b ("you can deduct only 50% of your business meal expenses")', $dft('i1040sc')),
        'schedule_c.oficina_casa_tarifa_pie_cuadrado' => $p(5, 'Instrucciones del Schedule C de 2026 (borrador), p. 11, línea 30 ("by $5")', $dft('i1040sc')),
        'schedule_c.oficina_casa_tope_pies_cuadrados' => $p(300, 'Instrucciones del Schedule C de 2026 (borrador), p. 11, línea 30 ("cannot exceed 300 square feet")', $dft('i1040sc')),
        // Pendiente: este parámetro sí está en parametros.php de 2025 y la carga heredaría su valor de 2025 (10.918,20, que es 6,2 % de USD 176.100). Se deja vacío a propósito.
        'self_employment_tax.maximo_seguro_social_retenido_por_persona' => ['valor' => null, 'fuente_texto' => null, 'fuente_url' => null],
        'schedule_1.tope_gastos_educador' => $p(350, 'Rev. Proc. 2025-32, sección 4.12, p. 17 (§ 62(a)(2)(D): "is $350")', $rp),
    ],
    'casillas' => [
        '1099-nec' => ['1' => ['casilla' => '1a']],
    ],
    'lineas' => [
        'sch-c:1' => ['origen_clave' => '1099-nec:1a;1099-k:1a;1099-misc:5;1099-misc:6'],
    ],
    'preguntas' => [
        'A1' => ['fuente_texto' => 'Encabezado y firma del 1040 (la firma pide la ocupación). 1040 línea 12d: "nació antes del 2 de enero de 1962"'],
        // El límite del pariente calificado de 2026 (USD 5.300, Rev. Proc. 2025-32, sección 4.23).
        'C13' => ['texto_es' => '¿Esta persona tuvo ingresos de menos de USD 5.300 en {anio}?'],
        // F3.9: los créditos por vehículo limpio y de energía de la vivienda
        // terminan en 2025 (Ley 119-21).
        'F10' => ['activo' => false],
        'F11' => ['activo' => false],
    ],
];
