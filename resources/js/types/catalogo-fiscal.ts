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
    /** Administrador y preparador (F2.1). */
    puedeValidar: boolean;
};

export type ActivacionCatalogo = {
    tipo: 'pregunta' | 'documento' | 'formulario';
    codigo: string;
    respuesta: unknown;
};

/** Lo que tiene toda pieza que se puede editar (F1.5). */
export type PiezaEditable = {
    id: number;
    activo: boolean;
};

export type PreguntaCatalogo = FuenteIrs &
    PiezaEditable & {
        codigo: string;
        orden: number;
        texto_es: string;
        texto_en: string | null;
        ayuda_es: string | null;
        bloque: string;
        pantalla: number | null;
        texto: string;
        ayuda: string | null;
        tipo_respuesta: string;
        opciones: { valor: string; texto_es: string }[];
        nivel: 'entrada' | 'seguimiento' | 'no_se_pregunta';
        por_persona: boolean;
        sensible: boolean;
        condicion: Record<string, unknown> | null;
        efecto: string | null;
        activa: ActivacionCatalogo[];
    };

export type DocumentoCatalogo = FuenteIrs &
    PiezaEditable & {
        codigo: string;
        grupo: string;
        nombre: string;
        nombre_oficial: string | null;
        emisor: string | null;
        fecha_llegada: string | null;
        si_no_llega: string | null;
        formatos: string[];
        uno_por: string;
        casillas: {
            casilla: string;
            descripcion: string;
            tipo_dato: string | null;
            clave: boolean;
        }[];
        activado_por: string[];
    };

export type LineaCatalogo = {
    linea: string;
    descripcion: string;
    origen_tipo: 'casilla' | 'linea' | 'respuesta' | 'calculo';
    origen_clave: string | null;
    origen: string[];
};

export type FormularioCatalogo = FuenteIrs &
    PiezaEditable & {
        codigo: string;
        nombre: string;
        para_que: string | null;
        siempre: boolean;
        ficha: 'completa' | 'conector' | 'pendiente';
        lineas: LineaCatalogo[];
        activado_por: string[];
    };

export type ParametroCatalogo = FuenteIrs &
    PiezaEditable & {
        codigo: string;
        grupo: string | null;
        nombre: string;
        valor: unknown;
        tipo: 'monto' | 'porcentaje' | 'numero' | 'tabla' | 'fecha' | null;
        condicion: string | null;
        formulario_linea: string | null;
    };

export type ReglaCatalogo = FuenteIrs &
    PiezaEditable & {
        codigo: string;
        descripcion: string;
        severidad: 'bloqueante' | 'revision' | 'informativa';
        implementada: boolean;
    };
