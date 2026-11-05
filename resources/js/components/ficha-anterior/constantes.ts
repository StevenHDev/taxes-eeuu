import { AlertTriangle, Check, Circle, MinusCircle } from 'lucide-react';
import type { CampoCliente } from '@/types';

export const ESTADO_RIEL: Record<CampoCliente['estado'], string> = {
    pendiente: 'border-l-state-pendiente',
    recibido: 'border-l-state-recibido',
    invalido: 'border-l-state-invalido',
    no_aplica: 'border-l-state-no-aplica',
};

export const ESTADO_FONDO: Record<CampoCliente['estado'], string> = {
    pendiente: 'bg-state-pendiente',
    recibido: 'bg-state-recibido',
    invalido: 'bg-state-invalido',
    no_aplica: 'bg-state-no-aplica',
};

export const ESTADO_TINTA: Record<CampoCliente['estado'], string> = {
    pendiente: 'text-muted-foreground',
    recibido: 'text-foreground',
    invalido: 'text-destructive',
    no_aplica: 'text-muted-foreground',
};

export const ESTADO_ICONO: Record<CampoCliente['estado'], typeof Circle> = {
    pendiente: Circle,
    recibido: Check,
    invalido: AlertTriangle,
    no_aplica: MinusCircle,
};

export const TRANSVERSAL = 'transversal';
export const DOCUMENTOS_EXTRA = 'documentos_extra';

export const CATEGORIA_TRANSVERSAL_POR_CAMPO: Record<string, string> = {
    // Identidad y datos personales
    identificacion_ssn_itin: 'identidad',
    fecha_nacimiento_contribuyente: 'identidad',
    direccion_contribuyente: 'identidad',
    ocupacion: 'identidad',
    ip_pin: 'identidad',
    puede_ser_reclamado_como_dependiente: 'identidad',
    vivio_trabajo_fuera_eeuu: 'identidad',
    activos_digitales: 'identidad',

    // Estado civil y familia
    estado_civil: 'familia',
    info_conyuge: 'familia',
    info_dependientes: 'familia',
    form_8332: 'familia',

    // Empleo
    w2: 'empleo',
    form_1099_nec: 'empleo',
    mas_w2: 'empleo',
    salarios_empleado_domestico: 'empleo',

    // Retiro y jubilación
    form_1099_r: 'retiro',
    ssa_1099: 'retiro',
    retiro_rollover_o_conversion_roth: 'retiro',
    retiro_distribucion_anticipada: 'retiro',
    railroad_retirement: 'retiro',

    // Inversiones y cuentas financieras
    form_1099_int: 'inversiones',
    form_1099_div: 'inversiones',
    form_1099_b: 'inversiones',
    form_1099_g: 'inversiones',
    form_1098_e: 'inversiones',
    form_1099_misc: 'inversiones',
    form_1099_k: 'inversiones',
    form_w2g: 'inversiones',
    form_1099_c: 'inversiones',
    form_1099_sa: 'inversiones',
    form_5498_sa: 'inversiones',
    intereses_exentos_impuestos: 'inversiones',
    perdida_capital_arrastrada: 'inversiones',
    compensacion_acciones: 'inversiones',
    declaracion_anio_anterior: 'inversiones',

    // Vivienda y propiedad
    venta_residencia_principal: 'vivienda',
    form_1098: 'vivienda',
    mejoras_propiedad_vendida: 'vivienda',
    venta_a_plazos: 'vivienda',
    form_1099_s: 'vivienda',

    // Schedule K-1
    k1_recibido: 'k1',
    k1_distribuciones_recibidas: 'k1',
    k1_perdidas_pasivas_o_basis_pendiente: 'k1',

    // Salud y seguro
    form_1095_a: 'salud',

    // Cumplimiento y situaciones internacionales/legales
    cuentas_extranjero: 'compliance',
    cuentas_extranjero_detalle: 'compliance',
    regalos_herencia_extranjero: 'compliance',
    foreign_trust: 'compliance',
    cartas_irs: 'compliance',
    declaracion_enmendada: 'compliance',
    bankruptcy: 'compliance',

    // Otros ingresos y ajustes poco frecuentes
    foreclosure_abandono_propiedad: 'otrosIngresosAjustes',
    premios_hobby: 'otrosIngresosAjustes',
    ingresos_demanda_legal: 'otrosIngresosAjustes',
    alimony_recibido: 'otrosIngresosAjustes',
    contribuciones_ira_sep_simple: 'otrosIngresosAjustes',
    seguro_medico_self_employed: 'otrosIngresosAjustes',
    gastos_educador: 'otrosIngresosAjustes',
    mejoras_eficiencia_energetica: 'otrosIngresosAjustes',
    gastos_adopcion: 'otrosIngresosAjustes',
};

export const ORDEN_CATEGORIAS_TRANSVERSAL = [
    'identidad',
    'familia',
    'empleo',
    'inversiones',
    'retiro',
    'vivienda',
    'k1',
    'salud',
    'compliance',
    'otrosIngresosAjustes',
    'otros',
];

export function categoriaDe(campo: string): string {
    return CATEGORIA_TRANSVERSAL_POR_CAMPO[campo] ?? 'otros';
}

// Un campo "necesita atención" si todavía no tiene un valor válido cargado, o
// si sí lo tiene pero con una advertencia — en ambos casos el preparador
// necesita mirarlo. Decide qué subgrupos se abren por default: nada que
// resolver se queda plegado, lo demás se muestra de entrada.
export function necesitaAtencion(campo: CampoCliente): boolean {
    return (
        campo.estado === 'pendiente' ||
        campo.estado === 'invalido' ||
        !!campo.advertencia
    );
}

// El código es como los preparadores nombran el trabajo — «el 1040», «la
// Schedule C» — así que encabeza la sección en vez de quedar sepultado en una
// columna. Sale del valor del enum, que es el identificador estable.
export function codigoForma(forma: string): string {
    if (forma === TRANSVERSAL) {
        return 'IDENT';
    }

    if (forma === DOCUMENTOS_EXTRA) {
        return 'EXTRA';
    }

    return forma
        .replace(/^form_/, '')
        .replace(/^schedule_/, 'sch ')
        .replace(/_/g, '-')
        .toUpperCase();
}

// La etiqueta del backend ya trae el código («Form 1040 (Individual)»), que
// arriba se muestra aparte. Acá queda la parte en lenguaje llano.
export function descriptorForma(label: string): string {
    return label.match(/\(([^)]+)\)\s*$/)?.[1] ?? label;
}

// Renderiza cualquier valor recolectado de forma amigable para un usuario no
// técnico: sin corchetes ni llaves. Strings/números tal cual, listas como
export function esComplejo(value: unknown): boolean {
    if (value === null || typeof value !== 'object') {
        return false;
    }

    if (Array.isArray(value)) {
        return value.some((v) => v !== null && typeof v === 'object');
    }

    return true;
}
