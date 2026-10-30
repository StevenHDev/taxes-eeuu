<?php

/*
 * Equivalencias del formulario actual (`catalogo_campos` 2025, lo que hay
 * en `campos_cliente`) con el catálogo fiscal nuevo (F2.3, antes F1.6).
 * Lo lee App\Services\Entrevista\EquivalenciasCamposCliente; lo usa el
 * reporte `catalogo-fiscal:equivalencias` y, en 2.10, la migración de los
 * datos a la entrevista.
 *
 * Una entrada por `forma.campo` en alcance (1040, Schedules C, E y F,
 * `transversal` y `documentos_extra`). Cada entrada lleva una o varias de:
 *
 *   'pregunta' => 'B1', 'transforma' => '…'   el valor pasa a esa pregunta
 *   'documento' => 'w2'                       el archivo pasa a ese documento
 *   'responde' => ['D1' => 'si']              respuestas que se deducen del dato
 *   'subcampos' => [subcampo => entrada]      para los campos con subcampos
 *   'por_persona' => true                     lista del bloque C: una persona por ítem
 *   'sin_equivalente' => 'motivo'             no pasa a la entrevista
 *   'nota' => '…'                             aclaración para quien migra
 *
 * Transformaciones (las implementa la migración de 2.10):
 *   tal_cual, si_no, numero, fecha        mismo dato con la forma de ValorRespuesta
 *   monto_a_si                            un monto mayor que 0 → "si"
 *   texto_a_si                            un texto no vacío (que no sea "no") → "si"
 *   si_no_a_usted / texto_a_usted         sí o texto no vacío → ["usted"], no → "no"
 *   casado_si_verdadero                   verdadero → "casado"
 *   viudo_si_verdadero                    verdadero → "viudo"
 *   relacion_a_c3                         texto de parentesco → opción de C3
 *   menor_al_limite_c13                   ingreso menor que el límite del año → "si"
 *   parte_de_dato                         se suma al texto de una pregunta `dato` compuesta
 *
 * Los montos no pasan: en el flujo nuevo se leen de los documentos (9.3.2).
 * `campos_cliente` no se borra; queda como histórico (L12).
 */

$seLee = fn (string $de) => "El monto se lee de {$de} (9.3.2).";
$fueraDel13614 = fn (string $que) => "{$que} no está en el 13614-C ni en el piloto; lo revisa el preparador.";
$scheduleF = 'El Schedule F no está en el catálogo nuevo: espera la respuesta de GTS (P3).';

return [

    // --- Documentos extra -------------------------------------------------
    'documentos_extra.form_1098_t' => ['documento' => '1098-t', 'responde' => ['F1' => 'si']],

    // --- Form 1040 ---------------------------------------------------------
    'form_1040.gastos_cuidado_dependientes' => [
        'documento' => 'recibo-cuidado',
        'responde' => ['E6' => 'si'],
        'nota' => 'El proveedor y los montos se leen del recibo (9.3.2).',
    ],
    'form_1040.ingresos' => ['sin_equivalente' => $seLee('los W-2, 1099 y demás documentos')],
    'form_1040.deducciones' => ['subcampos' => [
        'intereses_hipotecarios' => ['pregunta' => 'E1', 'transforma' => 'monto_a_si'],
        'impuestos_propiedad' => ['pregunta' => 'E2', 'transforma' => 'monto_a_si'],
        'donaciones_efectivo' => ['pregunta' => 'E4', 'transforma' => 'monto_a_si'],
        'donaciones_bienes' => ['pregunta' => 'E4', 'transforma' => 'monto_a_si'],
        'gastos_medicos' => ['pregunta' => 'E3', 'transforma' => 'monto_a_si'],
        'intereses_inversion' => ['sin_equivalente' => $fueraDel13614('El interés por inversiones (Form 4952)')],
        'perdidas_desastre' => ['pregunta' => 'F7', 'transforma' => 'monto_a_si'],
    ]],
    'form_1040.impuestos_retenidos' => ['sin_equivalente' => $seLee('los W-2 y 1099')],
    'form_1040.info_bancaria' => [
        'responde' => ['G3' => 'deposito_directo'],
        'documento' => 'datos-bancarios',
        'nota' => 'Banco, tipo de cuenta, número y ruta se piden de nuevo en el portal con el documento datos-bancarios: no se copian.',
    ],
    'form_1040.gastos_educacion' => ['pregunta' => 'F1', 'transforma' => 'monto_a_si'],
    'form_1040.marketplace_seguro' => [
        'responde' => ['F4' => 'si'],
        'nota' => 'Prima, SLCSP y anticipo se leen del 1095-A (9.3.2).',
    ],
    'form_1040.impuesto_extranjero_pagado' => ['sin_equivalente' => $seLee('el 1099-DIV, casilla 7')],
    'form_1040.beneficios_2025' => ['subcampos' => [
        'propinas_reportadas' => ['pregunta' => 'D3', 'transforma' => 'monto_a_si'],
        'horas_extra_pagadas' => ['pregunta' => 'D2', 'transforma' => 'monto_a_si'],
        'interes_prestamo_auto' => ['pregunta' => 'F5', 'transforma' => 'monto_a_si'],
        'es_adulto_mayor' => ['sin_equivalente' => 'Sale de la fecha de nacimiento (A1).'],
    ]],
    'form_1040.salarios_medicare' => ['sin_equivalente' => $seLee('el W-2, casilla 5')],
    'form_1040.ingreso_extranjero' => ['sin_equivalente' => $fueraDel13614('El ingreso del extranjero (Form 2555)')],
    'form_1040.pagos_estimados' => ['pregunta' => 'G1', 'transforma' => 'monto_a_si'],
    'form_1040.pago_con_extension' => ['pregunta' => 'G2', 'transforma' => 'monto_a_si'],
    'form_1040.reembolso_anio_anterior_aplicado' => ['pregunta' => 'G1', 'transforma' => 'monto_a_si'],

    // --- Schedule C --------------------------------------------------------
    'schedule_c.estados_bancarios' => ['documento' => 'registros-ingresos-gastos', 'responde' => ['D15' => 'si']],
    'schedule_c.ingresos_negocio' => [
        'pregunta' => 'D15',
        'transforma' => 'monto_a_si',
        'nota' => 'El monto se lee de los 1099-NEC/1099-K y de los registros (9.3.2).',
    ],
    'schedule_c.gastos_deducibles_negocio' => ['sin_equivalente' => $seLee('los registros de ingresos y gastos')],
    'schedule_c.millaje' => ['sin_equivalente' => $seLee('los registros de ingresos y gastos')],
    'schedule_c.activos' => ['sin_equivalente' => 'La depreciación la arma el preparador con los registros.'],
    'schedule_c.costo_ventas' => ['sin_equivalente' => $seLee('los registros de ingresos y gastos')],
    'schedule_c.oficina_en_casa' => ['sin_equivalente' => 'El Form 8829 lo arma el preparador con los registros.'],

    // --- Schedule E --------------------------------------------------------
    'schedule_e.estados_bancarios' => ['documento' => 'registros-alquiler', 'responde' => ['D12' => 'si']],
    'schedule_e.ingresos_renta' => ['pregunta' => 'D12', 'transforma' => 'monto_a_si'],
    'schedule_e.gastos_propiedad' => ['sin_equivalente' => $seLee('los registros del alquiler')],
    'schedule_e.depreciacion' => ['sin_equivalente' => 'La depreciación la arma el preparador con los registros.'],
    'schedule_e.intereses_hipotecarios' => ['documento' => '1098', 'responde' => ['D12' => 'si']],
    'schedule_e.impuestos_propiedad' => ['documento' => 'recibo-predial', 'responde' => ['D12' => 'si']],
    'schedule_e.seguros_propiedad' => ['documento' => 'registros-alquiler', 'responde' => ['D12' => 'si']],

    // --- Schedule F --------------------------------------------------------
    'schedule_f.estados_bancarios' => ['sin_equivalente' => $scheduleF],
    'schedule_f.ventas_agricolas' => ['sin_equivalente' => $scheduleF],
    'schedule_f.subsidios' => ['sin_equivalente' => $scheduleF],
    'schedule_f.gastos_operacion' => ['sin_equivalente' => $scheduleF],
    'schedule_f.maquinaria' => ['sin_equivalente' => $scheduleF],
    'schedule_f.animales' => ['sin_equivalente' => $scheduleF],
    'schedule_f.inventario' => ['sin_equivalente' => $scheduleF],

    // --- Transversal: identidad y estado civil -----------------------------
    'transversal.estado_civil' => ['subcampos' => [
        'casado_al_31_dic' => ['pregunta' => 'B1', 'transforma' => 'casado_si_verdadero'],
        'costeo_mas_mitad_hogar' => ['pregunta' => 'B5', 'transforma' => 'si_no'],
        'conyuge_fallecio_en_anio' => ['pregunta' => 'B1', 'transforma' => 'viudo_si_verdadero'],
        'anio_fallecimiento_conyuge' => ['pregunta' => 'B6', 'transforma' => 'numero'],
        'se_caso_en_anio' => ['pregunta' => 'B2', 'transforma' => 'si_no'],
        'se_divorcio_o_separo_en_anio' => ['sin_equivalente' => 'No distingue divorcio de separación: B1 se confirma con el cliente.'],
    ]],
    'transversal.identificacion_ssn_itin' => [
        'sin_equivalente' => 'El número se lee de la tarjeta (documento tarjeta-ssn) y A3 se pregunta en el portal.',
    ],
    'transversal.info_conyuge' => ['subcampos' => [
        'nombre_completo' => ['pregunta' => 'B4', 'transforma' => 'parte_de_dato'],
        'fecha_nacimiento' => ['pregunta' => 'B4', 'transforma' => 'parte_de_dato'],
        'ssn' => ['pregunta' => 'B4', 'transforma' => 'parte_de_dato'],
    ]],
    'transversal.info_dependientes' => [
        'por_persona' => true,
        'responde' => ['C0' => 'si'],
        'subcampos' => [
            'nombre_completo' => ['pregunta' => 'C1', 'transforma' => 'tal_cual'],
            'fecha_nacimiento' => ['pregunta' => 'C2', 'transforma' => 'fecha'],
            'ssn' => ['sin_equivalente' => 'C9 pide el tipo de número; el número se lee de la tarjeta.'],
            'relacion' => ['pregunta' => 'C3', 'transforma' => 'relacion_a_c3'],
            'meses_en_hogar' => ['pregunta' => 'C4', 'transforma' => 'numero'],
            'estudiante_tiempo_completo' => ['pregunta' => 'C7', 'transforma' => 'si_no'],
            'discapacitado' => ['pregunta' => 'C8', 'transforma' => 'si_no'],
            'provee_mas_50_soporte_propio' => ['pregunta' => 'C12', 'transforma' => 'si_no'],
            'ingreso_bruto_anual' => ['pregunta' => 'C13', 'transforma' => 'menor_al_limite_c13'],
            'custodia_compartida_sin_conflicto' => ['sin_equivalente' => 'No dice quién reclama a la persona: C11 se pregunta de nuevo.'],
        ],
    ],
    'transversal.fecha_nacimiento_contribuyente' => ['pregunta' => 'A1', 'transforma' => 'parte_de_dato'],
    'transversal.direccion_contribuyente' => ['pregunta' => 'A2', 'transforma' => 'parte_de_dato'],
    'transversal.ocupacion' => ['pregunta' => 'A1', 'transforma' => 'parte_de_dato'],
    'transversal.puede_ser_reclamado_como_dependiente' => ['pregunta' => 'A5', 'transforma' => 'si_no'],
    'transversal.vivio_trabajo_fuera_eeuu' => ['sin_equivalente' => $fueraDel13614('Vivir o trabajar fuera de EE. UU. (Form 2555)')],
    'transversal.ip_pin' => [
        'pregunta' => 'A12',
        'transforma' => 'texto_a_usted',
        'nota' => 'El PIN se lee de la carta del IRS (documento ip-pin); no se copia.',
    ],
    'transversal.activos_digitales' => ['pregunta' => 'A13', 'transforma' => 'si_no_a_usted'],

    // --- Transversal: documentos -------------------------------------------
    'transversal.w2' => ['documento' => 'w2', 'responde' => ['D1' => 'si']],
    'transversal.form_1099_nec' => ['documento' => '1099-nec', 'responde' => ['D15' => 'si']],
    'transversal.form_1095_a' => ['documento' => '1095-a', 'responde' => ['F4' => 'si']],
    'transversal.form_1099_int' => ['documento' => '1099-int', 'responde' => ['D9' => 'si']],
    'transversal.form_1099_div' => ['documento' => '1099-div', 'responde' => ['D9' => 'si']],
    'transversal.form_1099_r' => ['documento' => '1099-r', 'responde' => ['D4' => 'si']],
    'transversal.form_1099_g' => ['documento' => '1099-g', 'nota' => 'Puede ser desempleo (D7) o reembolso estatal (D8): se pregunta.'],
    'transversal.form_1098' => ['documento' => '1098', 'responde' => ['E1' => 'si']],
    'transversal.form_1098_e' => ['documento' => '1098-e', 'responde' => ['E5' => 'si']],
    'transversal.ssa_1099' => ['documento' => 'ssa-1099', 'responde' => ['D6' => 'si']],
    'transversal.form_1099_b' => ['documento' => '1099-b', 'responde' => ['D10' => 'si']],
    'transversal.form_1099_misc' => ['documento' => '1099-misc', 'nota' => 'Puede ser trabajo por cuenta propia (D15) u otro ingreso (D17): se pregunta.'],
    'transversal.form_1099_k' => ['documento' => '1099-k', 'nota' => 'Puede ser negocio (D15) o apps (D16): se pregunta.'],
    'transversal.form_1099_s' => ['documento' => '1099-s', 'nota' => 'Puede ser la casa (F2) u otro inmueble (D10): se pregunta.'],
    'transversal.k1_recibido' => ['documento' => 'k-1', 'responde' => ['D18' => 'si']],
    'transversal.form_w2g' => ['documento' => 'w2g', 'responde' => ['D14' => 'si']],
    'transversal.form_1099_c' => ['documento' => '1099-c', 'responde' => ['F6' => 'si']],
    'transversal.form_1099_sa' => ['documento' => '1099-sa', 'responde' => ['F3' => 'si']],
    'transversal.form_5498_sa' => ['documento' => '5498-sa', 'responde' => ['F3' => 'si']],
    'transversal.declaracion_anio_anterior' => ['documento' => 'declaracion-anterior', 'responde' => ['G5' => 'si']],
    'transversal.form_8332' => ['documento' => 'form-8332', 'nota' => 'Es de una persona del bloque C (C11): el preparador la asigna.'],
    'transversal.railroad_retirement' => [
        'responde' => ['D6' => 'si'],
        'nota' => 'El RRB-1099 no está en el catálogo nuevo: el archivo queda en el histórico.',
    ],

    // --- Transversal: otras situaciones ------------------------------------
    'transversal.cuentas_extranjero' => ['sin_equivalente' => $fueraDel13614('Las cuentas en el extranjero (FBAR, Form 8938)')],
    'transversal.cuentas_extranjero_detalle' => ['sin_equivalente' => $fueraDel13614('Las cuentas en el extranjero (FBAR, Form 8938)')],
    'transversal.venta_residencia_principal' => [
        'pregunta' => 'F2',
        'transforma' => 'texto_a_si',
        'nota' => 'Fechas y montos se leen del 1099-S y de los papeles de la venta (9.3.2).',
    ],
    'transversal.mas_w2' => ['sin_equivalente' => 'D1.1 pide cuántos empleos: se pregunta de nuevo.'],
    'transversal.salarios_empleado_domestico' => ['pregunta' => 'D17', 'transforma' => 'monto_a_si'],
    'transversal.intereses_exentos_impuestos' => ['pregunta' => 'D9', 'transforma' => 'monto_a_si'],
    'transversal.mejoras_propiedad_vendida' => ['sin_equivalente' => 'Se ve con F2 y los papeles de la venta.'],
    'transversal.venta_a_plazos' => ['sin_equivalente' => $fueraDel13614('La venta a plazos (Form 6252)')],
    'transversal.retiro_rollover_o_conversion_roth' => ['pregunta' => 'D4', 'transforma' => 'si_no'],
    'transversal.retiro_distribucion_anticipada' => ['pregunta' => 'D4', 'transforma' => 'si_no'],
    'transversal.perdida_capital_arrastrada' => ['pregunta' => 'D10', 'transforma' => 'monto_a_si'],
    'transversal.compensacion_acciones' => ['pregunta' => 'D10', 'transforma' => 'monto_a_si'],
    'transversal.k1_distribuciones_recibidas' => ['pregunta' => 'D18', 'transforma' => 'monto_a_si'],
    'transversal.k1_perdidas_pasivas_o_basis_pendiente' => ['pregunta' => 'D18', 'transforma' => 'monto_a_si'],
    'transversal.foreclosure_abandono_propiedad' => ['pregunta' => 'F6', 'transforma' => 'monto_a_si'],
    'transversal.premios_hobby' => ['pregunta' => 'D17', 'transforma' => 'monto_a_si'],
    'transversal.ingresos_demanda_legal' => ['pregunta' => 'D17', 'transforma' => 'monto_a_si'],
    'transversal.alimony_recibido' => ['pregunta' => 'D11', 'transforma' => 'monto_a_si'],
    'transversal.contribuciones_ira_sep_simple' => ['pregunta' => 'E7', 'transforma' => 'monto_a_si'],
    'transversal.seguro_medico_self_employed' => ['sin_equivalente' => 'Lo calcula el preparador con D15 y los recibos del seguro.'],
    'transversal.gastos_educador' => ['pregunta' => 'E8', 'transforma' => 'monto_a_si'],
    'transversal.mejoras_eficiencia_energetica' => ['sin_equivalente' => $fueraDel13614('Las mejoras de eficiencia energética (Form 5695)')],
    'transversal.gastos_adopcion' => ['sin_equivalente' => $fueraDel13614('La adopción (Form 8839)')],
    'transversal.regalos_herencia_extranjero' => ['sin_equivalente' => $fueraDel13614('Los regalos o herencias del extranjero (Form 3520)')],
    'transversal.foreign_trust' => ['sin_equivalente' => $fueraDel13614('El fideicomiso extranjero (Form 3520)')],
    'transversal.cartas_irs' => ['pregunta' => 'F9', 'transforma' => 'texto_a_si'],
    'transversal.declaracion_enmendada' => ['sin_equivalente' => 'No es una pregunta de la entrevista; lo revisa el preparador.'],
    'transversal.bankruptcy' => ['sin_equivalente' => $fueraDel13614('La bancarrota')],
];
