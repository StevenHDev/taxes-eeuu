/** Forma de la prop diferida `debida` (DebidaDiligencia::vista, F5.4a). */
export type CreditoClave = 'eic' | 'ctc' | 'aotc' | 'hoh';

export type CanalRespuesta = 'whatsapp' | 'portal' | 'preparador' | 'migracion';

export type EstadoDocumentoDebida =
    | 'pendiente'
    | 'subido'
    | 'leido'
    | 'no_se_pudo_leer'
    | 'con_observacion'
    | 'validado'
    | 'no_aplica'
    | 'no_aplica_rechazado';

export type CreditoDebida = {
    credito: CreditoClave;
    etiqueta: string;
    sugerido: boolean;
    motivo_sugerencia: string | null;
    reclamado: boolean;
};

export type PreguntaDebida = {
    codigo: string;
    persona: { orden: number; etiqueta: string | null } | null;
    texto: string;
    respondida: boolean;
    sensible: boolean;
    valor_texto: string | null;
    canal: CanalRespuesta | null;
    respondida_en: string | null;
    /** Nombre del preparador que la escribió; nulo si fue el cliente. */
    actor: string | null;
    cambios: number;
};

export type DocumentoDebida = {
    item_id: number;
    codigo: string;
    nombre: string;
    persona_orden: number | null;
    estado: EstadoDocumentoDebida;
    validado_por: string | null;
    validado_en: string | null;
    enlace: string;
};

export type SeccionDebida = {
    parte: string;
    titulo: string;
    credito: CreditoClave | null;
    preguntas: PreguntaDebida[];
    documentos: DocumentoDebida[];
};

export type EntrevistaDebida = {
    iniciada_en: string;
    canal_inicio: CanalRespuesta;
    confirmada_en: string | null;
    consentimiento: { version: number; aceptado_en: string } | null;
};

export type DebidaDiligenciaVista = {
    aplica: boolean;
    creditos: CreditoDebida[];
    creditos_por: string | null;
    creditos_en: string | null;
    entrevista: EntrevistaDebida | null;
    secciones: SeccionDebida[];
    generado_en: string;
};
