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
    canal: 'whatsapp' | 'portal' | 'preparador' | 'migracion' | null;
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
