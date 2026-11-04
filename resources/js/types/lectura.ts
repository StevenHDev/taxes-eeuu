/** Lo que el visor (F4.6) recibe del servidor: ver VisorDocumentoController. */

export type Recuadro = {
    x: number;
    y: number;
    ancho: number;
    alto: number;
};

export type ExcepcionDato = {
    id: number;
    regla_codigo: string;
    severidad: string;
    estado: string;
    detalle: Record<string, unknown> | null;
    /** Por qué saltó en este caso, en palabras. */
    caso?: string;
};

/** Una excepción abierta de todo el documento (también las que no son de una casilla: año, tipo, repetido). */
export type ExcepcionDocumento = {
    id: number;
    regla_codigo: string;
    regla_descripcion: string | null;
    severidad: 'bloqueante' | 'revision' | 'informativa';
    estado: string;
    casilla: string | null;
    caso: string;
    que_hacer: string | null;
};

export type MotivoConfianza = {
    codigo: string;
    titulo: string;
    explicacion: string;
};

export type DatoLeido = {
    id: number;
    lectura_id: number;
    casilla: string;
    descripcion: string;
    tipo_dato: string | null;
    clave: boolean;
    sensible: boolean;
    /** Nulo si es sensible: se pide con `revelar`. */
    valor: string | null;
    tiene_valor: boolean;
    confianza: number | null;
    motivo_confianza: MotivoConfianza | null;
    legibilidad: string | null;
    pagina: number | null;
    recuadro: Recuadro | null;
    origen: string;
    corregido_por: string | null;
    corregido_en: string | null;
    motivo: string | null;
    /** Nula si es sensible. */
    evidencia: string | null;
    excepciones: ExcepcionDato[];
};

export type LecturaVisor = {
    id: number;
    documento_id: number;
    paginas: number;
    tipo_detectado: string | null;
    anio_detectado: number | null;
    terminada_en: string | null;
    /** Una URL por hoja, en orden. */
    imagenes: string[];
};

export type ArchivoVisor = {
    id: number;
    nombre: string;
    formato: string | null;
    url: string;
};

export type FilaConfianza = {
    motivo: string;
    confianza: number;
    titulo: string;
    explicacion: string;
};

export type ItemVisor = {
    id: number;
    codigo: string;
    nombre: string;
    nombre_oficial: string | null;
    numero: number | null;
    estado: string;
    observacion: string | null;
    tax_year: number;
};

/** Una hoja de un archivo: lo que el selector de página recorre. */
export type Hoja = {
    lecturaId: number;
    pagina: number;
    url: string;
    archivo: string;
};

/** Lo que `revelar` devuelve; vive solo en memoria. */
export type ValorRevelado = { valor: string | null; evidencia: string | null };
