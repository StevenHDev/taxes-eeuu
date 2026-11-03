/** Valor de una respuesta, con la forma de App\Services\Entrevista\ValorRespuesta. */
export type ValorEntrevista = string | number | string[] | null;

export type TipoRespuesta =
    | 'si_no'
    | 'usted_conyuge_no'
    | 'opciones'
    | 'casillas'
    | 'dato'
    | 'fecha'
    | 'numero';

export type PreguntaPaso = {
    codigo: string;
    texto: string;
    ayuda: string | null;
    tipo: TipoRespuesta;
    opciones: { valor: string; texto: string }[];
    sensible: boolean;
    /** Va en la lista de casillas con "Ninguna": sin marcar se guarda "no". */
    casilla: boolean;
    valor: ValorEntrevista;
    canal: CanalRespuesta | null;
    respondida_en: string | null;
};

export type EstadoPantalla = 'completa' | 'en_curso' | 'sin_empezar';

export type AvancePantalla = {
    numero: number;
    total: number;
    respondidas: number;
    estado: EstadoPantalla;
};

export type ArchivoDocumento = {
    id: number;
    nombre: string;
    tamano: number;
    subido_en: string | null;
    /** URL firmada y temporal para verlo. */
    url: string;
};

export type EstadoDocumento =
    | 'pendiente'
    | 'subido'
    | 'leido'
    | 'no_se_pudo_leer'
    | 'con_observacion'
    | 'validado'
    | 'no_aplica'
    | 'no_aplica_rechazado';

/** Un ítem del checklist de documentos del cliente (F3.3). */
export type DocumentoEntrevista = {
    id: number;
    codigo: string;
    /** Solo si hay más de uno del mismo documento y persona ("W-2 #2"). */
    numero: number | null;
    persona_id: number | null;
    persona_orden: number | null;
    descripcion: string | null;
    grupo: string;
    /** Nombre sencillo, en el idioma del cliente. */
    nombre: string;
    nombre_oficial: string | null;
    siempre: boolean;
    /** Textos de las preguntas que lo pidieron ("por qué te lo pedimos"). */
    por_que: string[];
    emisor: string | null;
    /** Fecha ISO (AAAA-MM-DD) en que debe llegar, si el catálogo la tiene. */
    fecha_llegada: string | null;
    si_no_llega: string | null;
    /** Qué subir cuando no hay formulario (registros propios); null en los demás. */
    que_subir: string | null;
    /** URL de la miniatura del formulario oficial vacío; null si el documento no tiene. */
    imagen_ejemplo: string | null;
    /** Plantilla opcional (Excel y PDF) en el idioma del cliente. */
    plantillas: { xlsx: string; pdf: string } | null;
    /** Lo que el cliente escribió que los papeles no dicen. */
    nota_cliente: string | null;
    formatos: string[];
    estado: EstadoDocumento;
    /** Al cliente ya no le falta nada por hacer. */
    resuelto: boolean;
    motivo_no_aplica: string | null;
    /** Lo que escribió el preparador al devolverlo. */
    observacion: string | null;
    puede_agregar_otro: boolean;
    archivos: ArchivoDocumento[];
};

export type CanalRespuesta = 'whatsapp' | 'portal' | 'preparador' | 'migracion';

/** Una respuesta como la ve el preparador (2.9). */
export type RespuestaPreparador = {
    /** null si todavía no se respondió. */
    respuesta_id: number | null;
    codigo: string;
    persona_id: number | null;
    persona_orden: number | null;
    texto: string;
    ayuda: string | null;
    tipo: TipoRespuesta;
    opciones: { valor: string; texto: string }[];
    sensible: boolean;
    respondida: boolean;
    /** Siempre null en las sensibles: se revelan con contraseña. */
    valor: ValorEntrevista;
    canal: CanalRespuesta | null;
    respondida_en: string | null;
    cambios: number;
};

/** Un documento del checklist, como lo ve el preparador (F3.4). */
export type DocumentoRevision = {
    id: number;
    codigo: string;
    numero: number | null;
    persona_orden: number | null;
    nombre: string;
    nombre_oficial: string | null;
    descripcion: string | null;
    estado: EstadoDocumento;
    motivo_no_aplica: string | null;
    observacion: string | null;
    nota_cliente: string | null;
    archivos: ArchivoDocumento[];
    revisado_por: string | null;
    revisado_en: string | null;
};

export type ResumenDocumentos = {
    faltan: number;
    por_revisar: number;
    validados: number;
    no_aplica: number;
    total: number;
    completo: boolean;
};

/** La entrevista en la ficha del cliente (App\Services\Entrevista\VistaEntrevistaPreparador). */
export type VistaEntrevista = {
    tax_year: number;
    estado: 'en_curso' | 'confirmada' | 'reabierta';
    canal_inicio: CanalRespuesta;
    iniciada_en: string;
    confirmada_en: string | null;
    consentimiento: {
        version: number;
        provisional: boolean;
        aceptado_en: string;
    } | null;
    con_conyuge: boolean;
    personas: { id: number; orden: number; etiqueta: string }[];
    formularios: string[];
    pantallas: {
        numero: number;
        total: number;
        respondidas: number;
        preguntas: RespuestaPreparador[];
    }[];
    /** Lo que el preparador debe saber antes de empezar (F3.8). */
    avisos: { tipo: 'anios_sin_declarar'; detalle: string | null }[];
    documentos: DocumentoRevision[];
    documentos_resumen: ResumenDocumentos;
    /** Excepciones abiertas del cliente en la cola (F4.7). */
    excepciones_abiertas: { total: number; bloqueantes: number };
};

export type CambioRespuesta = {
    id: number;
    valor_anterior: ValorEntrevista;
    valor_nuevo: ValorEntrevista;
    sensible: boolean;
    canal: CanalRespuesta;
    actor: string | null;
    creado_en: string;
};
