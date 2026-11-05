import type { Diagnostico, EstadoExpediente } from '@/types/expediente';

export type PuntoClave =
    | 'identidad'
    | 'estado_civil'
    | 'dependientes'
    | 'documentos'
    | 'excepciones'
    | 'debida_diligencia'
    | 'anio_anterior';

export type AccionClave =
    | 'empezar'
    | 'preparado'
    | 'a_firma'
    | 'presentado'
    | 'aceptado'
    | 'rechazado'
    | 'volver_a_revision';

export type AccionRevision = {
    accion: AccionClave;
    etiqueta: string;
    requiere: 'fecha' | 'motivo' | null;
    habilitada: boolean;
    motivo_deshabilitada: string | null;
    principal: boolean;
};

export type RespuestaCliente = {
    codigo: string;
    texto: string;
    valor_texto: string;
};

export type Opcion = { valor: string; etiqueta: string };

export type EstadoCivilDecision = {
    valor: string | null;
    motivo: string | null;
    por: string | null;
    en: string | null;
    opciones: Opcion[];
    respuestas_cliente: RespuestaCliente[];
};

export type PersonaRevision = {
    id: number;
    orden: number;
    etiqueta: string;
    clasificacion: string | null;
    motivo: string | null;
    por: string | null;
    en: string | null;
    respuestas: RespuestaCliente[];
};

export type PuntoRevision = {
    punto: PuntoClave;
    titulo: string;
    ayuda: string;
    manual: boolean;
    requerido: boolean;
    cumplido: boolean;
    detalle: string | null;
    confirmado_por: string | null;
    confirmado_en: string | null;
    nota: string | null;
    enlace: string | null;
    editable: boolean;
};

export type Revision = {
    estado: EstadoExpediente;
    acciones: AccionRevision[];
    decisiones_abiertas: boolean;
    estado_civil: EstadoCivilDecision;
    personas: PersonaRevision[];
    clasificaciones: Opcion[];
    puntos: PuntoRevision[];
    no_aplica: { id: number; nombre: string; motivo: string }[];
    puede_aprobar: boolean;
    faltan: number;
    diagnostico: Diagnostico;
    enlaces: {
        documentos: string | null;
        entrevista: string | null;
        excepciones: string | null;
        debida_diligencia: string | null;
        hoja: string | null;
    };
};
