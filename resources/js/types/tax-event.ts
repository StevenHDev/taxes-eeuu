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
