export type TaxForm =
    | 'form_1040'
    | 'schedule_c'
    | 'schedule_e'
    | 'form_1065'
    | 'form_1120'
    | 'form_1120_s'
    | 'schedule_f'
    | 'form_1041'
    | 'form_990'
    | 'form_1040_nr';

export type FormaOption = {
    value: TaxForm;
    label: string;
};

// Forma bajo la que se guarda un valor: una de las 10 formas, o una
// pseudo-forma para los campos únicos por cliente — 'transversal' (identidad:
// SSN, cónyuge, dependientes, estado civil, w2, 1099-NEC, 1095-A) o
// 'documentos_extra' (el resto de documentos opcionales que también se piden
// siempre, agrupados aparte).
export type FormaAlmacen = TaxForm | 'transversal' | 'documentos_extra';

export type FieldState = 'recibido' | 'pendiente' | 'invalido' | 'no_aplica';
export type FormState = 'en_progreso' | 'completo';
export type EstadoGeneral = 'sin_iniciar' | 'en_progreso' | 'completo';
export type NivelRiesgo = 'bajo' | 'medio' | 'alto';
export type NivelRiesgoFuente = 'manual' | 'automatico';

export type NivelRiesgoEfectivo = {
    nivel: NivelRiesgo;
    fuente: NivelRiesgoFuente;
};

export type ClienteFormaResumen = {
    forma: TaxForm;
    forma_label: string;
    estado: FormState;
};

export type Cliente = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    estado_general: EstadoGeneral;
    formas: ClienteFormaResumen[];
    nivel_riesgo: NivelRiesgo;
    nivel_riesgo_label: string;
    nivel_riesgo_fuente: NivelRiesgoFuente;
    created_at: string;
};

export type ClienteForma = {
    forma: TaxForm;
    forma_label: string;
    estado: FormState;
    revisado_en: string | null;
};

export type DocumentoDuplicado = {
    posible_duplicado: boolean;
    mismo_cliente: { forma: string; campo: string }[] | null;
    otro_cliente: boolean;
    otro_cliente_detalle: {
        cliente_id: number;
        cliente_nombre: string;
        forma: string;
        campo: string;
    } | null;
};

export type CampoDocumento = {
    id: number;
    file_original_name: string;
    file_mime_type: string;
    formato: string;
    estado_validacion: FieldState;
    download_url?: string;
    preview_url?: string;
    duplicado?: DocumentoDuplicado;
};

export type CampoCliente = {
    forma: FormaAlmacen;
    campo: string;
    tipo_campo: 'documento' | 'dato' | 'mixto';
    tipo_dato:
        'string' | 'number' | 'object' | 'array_string' | 'array_object' | null;
    subcampos: string[] | null;
    modo: 'archivo' | 'texto' | 'no_aplica';
    estado: FieldState;
    valor: unknown;
    es_sensible: boolean;
    documento: CampoDocumento | null;
    formatos_aceptados: string[] | null;
    obligatorio: boolean;
    updated_at: string;
};

export type HistorialCambio = {
    valor_anterior: unknown;
    valor_nuevo: unknown;
    source: 'agente_ia' | 'preparador' | 'administrador';
    modificado_por: string | null;
    created_at: string;
};

export type CatalogoDisponibleItem = {
    forma: FormaAlmacen;
    campo: string;
    tipo_campo: 'documento' | 'dato' | 'mixto';
    tipo_dato:
        'string' | 'number' | 'object' | 'array_string' | 'array_object' | null;
    subcampos: string[] | null;
    formatos_aceptados: string[] | null;
    obligatorio: boolean;
};

export type CampoCatalogo = {
    id: number;
    forma: string;
    tax_year: number;
    clave: string;
    tipo_campo: 'documento' | 'dato' | 'mixto';
    tipo_dato:
        'string' | 'number' | 'object' | 'array_string' | 'array_object' | null;
    formatos_aceptados: string[] | null;
    subcampos: string[] | null;
    obligatorio: boolean;
    sensible: boolean;
};

export type TipoDeterminacion =
    | 'filing_status'
    | 'dependientes'
    | 'agi'
    | 'creditos'
    | 'deduccion_aplicable'
    | 'qbi'
    | 'impuesto_ingreso'
    | 'impuesto_autoempleo'
    | 'impuesto_medicare_adicional'
    | 'niit'
    | 'liquidacion';

export type FilingStatusValue = 'mfj' | 'single' | 'hoh' | 'qss';

export type FilingStatusResultado =
    | { disponible: false; motivo_no_disponible: string }
    | {
          disponible: true;
          motivo_no_disponible: null;
          estado: FilingStatusValue;
      };

export type DependienteResultado = {
    nombre_completo: string | null;
    calificacion: 'qualifying_child' | 'qualifying_relative' | 'ninguna';
    edad_fin_anio: number | null;
    elegible_ctc: boolean;
    elegible_odc: boolean;
    elegible_cuidado: boolean;
};

export type DependientesResultado =
    | { disponible: false; motivo_no_disponible: string }
    | {
          disponible: true;
          motivo_no_disponible: null;
          dependientes: DependienteResultado[];
          conteo_qualifying_child: number;
          conteo_qualifying_relative: number;
          conteo_ctc: number;
          conteo_odc: number;
          conteo_cuidado: number;
      };

export type AgiResultado =
    | { disponible: false; motivo_no_disponible: string }
    | {
          disponible: true;
          motivo_no_disponible: null;
          agi: number;
          ingreso_bruto_total: number;
          ajustes: number;
      };

export type CreditosResultado =
    | { disponible: false; motivo_no_disponible: string }
    | {
          disponible: true;
          motivo_no_disponible: null;
          ctc: number;
          odc: number;
          reduccion_por_agi: number;
          cuidado_dependientes: number;
          total: number;
      };

export type DeduccionAplicableResultado =
    | { disponible: false; motivo_no_disponible: string }
    | {
          disponible: true;
          motivo_no_disponible: null;
          deduccion_estandar: number;
          deduccion_itemizada: number;
          deduccion_aplicable: number;
          usa_itemizada: boolean;
      };

export type QbiResultado =
    | { disponible: false; motivo_no_disponible: string }
    | {
          disponible: true;
          motivo_no_disponible: null;
          qbi: number;
          deduccion: number;
          requiere_revision_manual: boolean;
      };

export type ImpuestoIngresoResultado =
    | { disponible: false; motivo_no_disponible: string }
    | {
          disponible: true;
          motivo_no_disponible: null;
          ingreso_gravable: number;
          impuesto: number;
      };

export type ImpuestoAutoempleoResultado =
    | { disponible: false; motivo_no_disponible: string }
    | {
          disponible: true;
          motivo_no_disponible: null;
          ingreso_neto_se: number;
          base_gravable: number;
          impuesto_se: number;
          mitad_deducible: number;
      };

export type ImpuestoMedicareAdicionalResultado =
    | { disponible: false; motivo_no_disponible: string }
    | {
          disponible: true;
          motivo_no_disponible: null;
          base_combinada: number;
          umbral: number;
          impuesto: number;
      };

export type NiitResultado =
    | { disponible: false; motivo_no_disponible: string }
    | {
          disponible: true;
          motivo_no_disponible: null;
          ingreso_neto_inversion: number;
          exceso_magi: number;
          impuesto: number;
      };

export type LiquidacionResultado =
    | { disponible: false; motivo_no_disponible: string }
    | {
          disponible: true;
          motivo_no_disponible: null;
          impuesto_antes_otros: number;
          otros_impuestos: number;
          total_impuesto: number;
          total_pagos: number;
          reembolso: number;
          saldo_a_pagar: number;
      };

export type Determinacion = {
    tipo: TipoDeterminacion;
    resultado:
        | FilingStatusResultado
        | DependientesResultado
        | AgiResultado
        | CreditosResultado
        | DeduccionAplicableResultado
        | QbiResultado
        | ImpuestoIngresoResultado
        | ImpuestoAutoempleoResultado
        | ImpuestoMedicareAdicionalResultado
        | NiitResultado
        | LiquidacionResultado;
    version_reglas: string;
    calculado_en: string;
};

export type Usuario = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    role: 'client' | 'preparer' | 'administrator';
    preparer?: { id: number; name: string } | null;
};

export type AccionAuditoria =
    'creado' | 'actualizado' | 'eliminado' | 'inicio_sesion' | 'cierre_sesion';

export type BitacoraEvento = {
    id: number;
    created_at: string;
    actor_nombre: string | null;
    actor_email: string | null;
    accion: AccionAuditoria;
    auditable_type: string | null;
    etiqueta: string | null;
    campos_afectados: string[] | null;
    ip_address: string | null;
};

export type DashboardResumen = {
    total: number;
    sin_iniciar: number;
    en_progreso: number;
    completo: number;
    actividad_por_dia: { fecha: string; cantidad: number }[];
    campos_recibidos_porcentaje: number;
    formas_completas_porcentaje: number;
    distribucion_por_forma: {
        forma: TaxForm;
        forma_label: string;
        cantidad: number;
    }[];
    pendientes_revisar: {
        cliente_id: number;
        cliente_nombre: string;
        forma: TaxForm;
        forma_label: string;
    }[];
    casos_alto_riesgo: number;
    actividad_reciente: {
        campo: string;
        forma_label: string;
        cliente_nombre: string;
        source: 'agente_ia' | 'preparador' | 'administrador';
        created_at: string | null;
    }[];
    ultimos_clientes: { id: number; name: string }[];
};
