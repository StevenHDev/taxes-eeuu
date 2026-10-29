<?php

/**
 * Metadatos de los parámetros fiscales de 2025 (F1.2, grupos de 7.7 del
 * documento madre): categoria.clave => [grupo, tipo, nombre, formulario y
 * línea, condición]. Los valores siguen en ParametrosFiscalesSeeder.
 *
 * La migración 2026_10_29_150000 tiene su propia copia fija para producción;
 * este archivo es el que usan el seeder y, desde F1.3, la carga del catálogo.
 */
return [
    'deduccion_estandar.monto_soltero' => ['deduccion_estandar', 'monto', 'Deducción estándar: soltero o casado por separado', 'Form 1040, línea 12', null],
    'deduccion_estandar.monto_mfj' => ['deduccion_estandar', 'monto', 'Deducción estándar: casados declarando juntos', 'Form 1040, línea 12', null],
    'deduccion_estandar.monto_hoh' => ['deduccion_estandar', 'monto', 'Deducción estándar: cabeza de familia', 'Form 1040, línea 12', null],

    'credito_ctc.monto_por_dependiente' => ['creditos', 'monto', 'Crédito por hijos: monto por hijo calificado', 'Schedule 8812', 'Hijo menor de 17 con SSN válido'],
    'credito_ctc.phase_out_umbral_soltero' => ['creditos', 'monto', 'Crédito por hijos: AGI desde el que se reduce (no conjunta)', 'Schedule 8812', null],
    'credito_ctc.phase_out_umbral_mfj' => ['creditos', 'monto', 'Crédito por hijos: AGI desde el que se reduce (conjunta)', 'Schedule 8812', null],
    'credito_ctc.reduccion_por_1000_exceso' => ['creditos', 'monto', 'Crédito por hijos: reducción por cada USD 1.000 de exceso', 'Schedule 8812', null],
    'credito_odc.monto_por_dependiente' => ['creditos', 'monto', 'Crédito por otros dependientes: monto por dependiente', 'Schedule 8812', 'Dependiente que no califica para el crédito por hijos'],
    'credito_odc.phase_out_umbral_soltero' => ['creditos', 'monto', 'Crédito por otros dependientes: AGI desde el que se reduce (no conjunta)', 'Schedule 8812', null],
    'credito_odc.phase_out_umbral_mfj' => ['creditos', 'monto', 'Crédito por otros dependientes: AGI desde el que se reduce (conjunta)', 'Schedule 8812', null],
    'credito_odc.reduccion_por_1000_exceso' => ['creditos', 'monto', 'Crédito por otros dependientes: reducción por cada USD 1.000 de exceso', 'Schedule 8812', null],
    'credito_cuidado_dependientes.tope_gastos_1_persona' => ['creditos', 'monto', 'Crédito por cuidado: tope de gastos (1 persona)', 'Form 2441', null],
    'credito_cuidado_dependientes.tope_gastos_2_mas_personas' => ['creditos', 'monto', 'Crédito por cuidado: tope de gastos (2 o más personas)', 'Form 2441', null],
    'credito_cuidado_dependientes.porcentaje_maximo' => ['creditos', 'porcentaje', 'Crédito por cuidado: porcentaje máximo', 'Form 2441', null],
    'credito_cuidado_dependientes.porcentaje_minimo' => ['creditos', 'porcentaje', 'Crédito por cuidado: porcentaje mínimo', 'Form 2441', null],
    'credito_cuidado_dependientes.agi_umbral_porcentaje_maximo' => ['creditos', 'monto', 'Crédito por cuidado: AGI hasta el que aplica el porcentaje máximo', 'Form 2441', null],
    'credito_cuidado_dependientes.agi_umbral_porcentaje_minimo' => ['creditos', 'monto', 'Crédito por cuidado: AGI desde el que aplica el porcentaje mínimo', 'Form 2441', null],
    'credito_cuidado_dependientes.edad_limite_dependiente' => ['creditos', 'numero', 'Crédito por cuidado: edad límite del hijo', 'Form 2441', 'Menor de esta edad'],
    'credito_ftc.umbral_de_minimis_soltero' => ['creditos', 'monto', 'Crédito por impuesto extranjero sin Form 1116 (no conjunta)', 'Schedule 3, línea 1', 'Impuesto extranjero hasta este monto'],
    'credito_ftc.umbral_de_minimis_mfj' => ['creditos', 'monto', 'Crédito por impuesto extranjero sin Form 1116 (conjunta)', 'Schedule 3, línea 1', 'Impuesto extranjero hasta este monto'],

    'dependiente_calificado.limite_ingreso_bruto_pariente_calificado' => ['topes_umbrales', 'monto', 'Pariente calificado: ingreso bruto menor a', 'Form 1040, dependientes', null],
    'self_employment_tax.tope_salario_social_security' => ['topes_umbrales', 'monto', 'Tope del Seguro Social', 'Schedule SE', null],
    'additional_medicare_tax.umbral_soltero' => ['topes_umbrales', 'monto', 'Additional Medicare Tax: umbral (no conjunta)', 'Form 8959', null],
    'additional_medicare_tax.umbral_mfj' => ['topes_umbrales', 'monto', 'Additional Medicare Tax: umbral (conjunta)', 'Form 8959', null],
    'niit.umbral_soltero' => ['topes_umbrales', 'monto', 'Impuesto sobre ingreso neto de inversión: umbral (no conjunta)', 'Form 8960', null],
    'niit.umbral_mfj' => ['topes_umbrales', 'monto', 'Impuesto sobre ingreso neto de inversión: umbral (conjunta)', 'Form 8960', null],
    'qbi.umbral_soltero' => ['topes_umbrales', 'monto', 'Deducción QBI: umbral de ingreso (no conjunta)', 'Form 8995, Form 1040 línea 13', null],
    'qbi.umbral_mfj' => ['topes_umbrales', 'monto', 'Deducción QBI: umbral de ingreso (conjunta)', 'Form 8995, Form 1040 línea 13', null],

    'tax_brackets.single' => ['tablas', 'tabla', 'Tramos del impuesto: soltero', 'Form 1040, línea 16', null],
    'tax_brackets.mfj' => ['tablas', 'tabla', 'Tramos del impuesto: casados declarando juntos', 'Form 1040, línea 16', null],
    'tax_brackets.hoh' => ['tablas', 'tabla', 'Tramos del impuesto: cabeza de familia', 'Form 1040, línea 16', null],
];
