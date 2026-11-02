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

export type DocumentoEntrevista = {
    codigo: string;
    persona_id: number | null;
    persona_orden: number | null;
    grupo: string;
    nombre: string;
    siempre: boolean;
    /** Textos de las preguntas que lo pidieron ("por qué te lo pedimos"). */
    por_que: string[];
    formatos: string[];
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
    documentos: {
        codigo: string;
        persona_orden: number | null;
        nombre: string;
        archivos: ArchivoDocumento[];
    }[];
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
