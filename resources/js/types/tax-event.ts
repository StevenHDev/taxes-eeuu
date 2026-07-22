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

export type FieldState = 'recibido' | 'pendiente' | 'invalido';
export type FormState = 'en_progreso' | 'completo';
export type EstadoGeneral = 'sin_iniciar' | 'en_progreso' | 'completo';

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
    created_at: string;
};

export type ClienteForma = {
    forma: TaxForm;
    forma_label: string;
    estado: FormState;
    revisado_en: string | null;
};

export type CampoDocumento = {
    id: number;
    file_original_name: string;
    formato: string;
    estado_validacion: FieldState;
    download_url?: string;
};

export type CampoCliente = {
    forma: TaxForm;
    campo: string;
    tipo_campo: 'documento' | 'dato' | 'mixto';
    modo: 'archivo' | 'texto';
    estado: FieldState;
    valor: unknown;
    es_sensible: boolean;
    documento: CampoDocumento | null;
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
    forma: TaxForm;
    campo: string;
    tipo_campo: 'documento' | 'dato' | 'mixto';
};

export type CampoCatalogo = {
    id: number;
    forma: string;
    clave: string;
    tipo_campo: 'documento' | 'dato' | 'mixto';
    tipo_dato: 'string' | 'number' | 'object' | 'array_string' | 'array_object' | null;
    formatos_aceptados: string[] | null;
    subcampos: string[] | null;
    obligatorio: boolean;
    sensible: boolean;
};

export type Usuario = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    role: 'client' | 'preparer' | 'administrator';
    preparer?: { id: number; name: string } | null;
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
    actividad_reciente: {
        campo: string;
        forma_label: string;
        cliente_nombre: string;
        source: 'agente_ia' | 'preparador' | 'administrador';
        created_at: string | null;
    }[];
    ultimos_clientes: { id: number; name: string }[];
};
