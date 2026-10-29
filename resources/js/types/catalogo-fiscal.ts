/**
 * Catálogo fiscal del año (F1.4). Cada pestaña recibe su lista como prop
 * diferida (ver App\Http\Controllers\CatalogoFiscal\*).
 */
export type FuenteIrs = {
    fuente_texto: string | null;
    fuente_url: string | null;
    validado_en: string | null;
    validado_por: string | null;
};

export type CatalogoFiscalPageProps = {
    anio: number;
    estadoAnio: 'borrador' | 'vigente';
    anios: number[];
    puedeEditar: boolean;
};

export type ActivacionCatalogo = {
    tipo: 'pregunta' | 'documento' | 'formulario';
    codigo: string;
    respuesta: unknown;
};

export type PreguntaCatalogo = FuenteIrs & {
    codigo: string;
    bloque: string;
    pantalla: number | null;
    texto: string;
    ayuda: string | null;
    tipo_respuesta: string;
    opciones: string[];
    nivel: 'entrada' | 'seguimiento' | 'no_se_pregunta';
    por_persona: boolean;
    sensible: boolean;
    condicion: Record<string, unknown> | null;
    efecto: string | null;
    activa: ActivacionCatalogo[];
};

export type DocumentoCatalogo = FuenteIrs & {
    codigo: string;
    grupo: string;
    nombre: string;
    nombre_oficial: string | null;
    emisor: string | null;
    fecha_llegada: string | null;
    si_no_llega: string | null;
    formatos: string[];
    uno_por: string;
    casillas: { casilla: string; descripcion: string; clave: boolean }[];
    activado_por: string[];
};

export type LineaCatalogo = {
    linea: string;
    descripcion: string;
    origen_tipo: 'casilla' | 'linea' | 'respuesta' | 'calculo';
    origen: string[];
};

export type FormularioCatalogo = FuenteIrs & {
    codigo: string;
    nombre: string;
    para_que: string | null;
    siempre: boolean;
    ficha: 'completa' | 'conector' | 'pendiente';
    lineas: LineaCatalogo[];
    activado_por: string[];
};

export type ParametroCatalogo = FuenteIrs & {
    codigo: string;
    grupo: string | null;
    nombre: string;
    valor: unknown;
    tipo: 'monto' | 'porcentaje' | 'numero' | 'tabla' | 'fecha' | null;
    condicion: string | null;
    formulario_linea: string | null;
};

export type ReglaCatalogo = FuenteIrs & {
    codigo: string;
    descripcion: string;
    severidad: 'bloqueante' | 'revision' | 'informativa';
    implementada: boolean;
};
