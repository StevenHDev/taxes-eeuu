export type EstadoExpediente =
    | 'invitado'
    | 'en_entrevista'
    | 'en_consulta'
    | 'en_recoleccion'
    | 'por_validar'
    | 'listo_para_preparar'
    | 'en_revision'
    | 'preparado_en_taxwise'
    | 'esperando_firma'
    | 'presentado'
    | 'aceptado'
    | 'rechazado';

export type Turno = 'cliente' | 'preparador' | 'irs' | 'nadie';

export type ClavePestana =
    | 'resumen'
    | 'entrevista'
    | 'documentos'
    | 'excepciones'
    | 'revision'
    | 'hoja'
    | 'debida_diligencia'
    | 'historial';

export type PestanaFicha = {
    clave: ClavePestana;
    url: string;
};

export type FichaEncabezado = {
    cliente: {
        id: number;
        name: string;
        email: string;
        phone: string | null;
        role: string;
        es_prueba: boolean;
        archivado: boolean;
    };
    tax_year: number;
    anios: number[];
    expediente: {
        existe: boolean;
        estado: EstadoExpediente | null;
        label: string | null;
        desde: string | null;
        turno: Turno | null;
        vence_en: string | null;
        cambios_tras_presentar: boolean;
    };
    conteos: {
        excepciones: number;
        bloqueantes: number;
        documentos_por_validar: number;
        documentos_faltan: number;
    };
    pestanas: PestanaFicha[];
    puede_administrar: boolean;
};

export type DocumentoFaltante = {
    id: number;
    codigo: string;
    nombre: string;
    numero: number;
    persona_orden: number | null;
    estado: string;
};

export type Diagnostico = {
    estado: EstadoExpediente;
    estado_label: string;
    consentimiento: boolean;
    entrevista_confirmada: boolean;
    r02: {
        activa: boolean;
        cumple: boolean;
        faltan: DocumentoFaltante[];
        no_aplica: { id: number; nombre: string; motivo: string }[];
    };
    excepciones_que_frenan: number;
    informativas: number;
    consultas: number;
    items_cliente: number;
    items_preparador: number;
    motivos: string[];
};

export type EventoExpediente = {
    id: number;
    tipo: string;
    tipo_label: string;
    estado_anterior: EstadoExpediente | null;
    estado_nuevo: EstadoExpediente | null;
    estado_nuevo_label: string | null;
    actor: string | null;
    motivo: string | null;
    creado_en: string;
};
