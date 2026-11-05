import type { FilaConfianza } from '@/types/lectura';

export type EstadoFilaHoja =
    | 'con_excepcion'
    | 'pendiente'
    | 'baja_confianza'
    | 'corregido'
    | 'validado'
    | 'sin_validar'
    | 'respondido'
    | 'decidido'
    | 'manual';

export type ConfianzaFila = {
    valor: number;
    titulo: string | null;
    explicacion: string | null;
};

export type FilaHoja = {
    id: string;
    destino: string | null;
    descripcion: string;
    valor: string | null;
    tipo: string;
    sensible: boolean;
    mascara: string | null;
    revelar_url: string | null;
    origen: string;
    enlace: string | null;
    estado: EstadoFilaHoja;
    estado_texto: string;
    confianza: ConfianzaFila | null;
};

export type GrupoHoja = {
    titulo: string;
    item_id: number | null;
    enlace: string | null;
    filas: FilaHoja[];
};

export type SeccionHoja = {
    codigo: string;
    titulo: string;
    grupos: GrupoHoja[];
};

export type TotalHoja = {
    destino: string;
    descripcion: string;
    valor: string | null;
    documentos: number;
    completo: boolean;
    nota: string;
};

export type Hoja = {
    tax_year: number;
    generado_en: string;
    conteos: {
        filas: number;
        pendientes: number;
        baja_confianza: number;
        con_excepcion: number;
        sin_validar: number;
    };
    secciones: SeccionHoja[];
    totales: TotalHoja[];
    confianza_tabla: FilaConfianza[];
    umbral: number;
};

/** Estados que no requieren trabajo del preparador (el filtro "solo lo pendiente" los oculta). */
export const ESTADOS_RESUELTOS: ReadonlySet<EstadoFilaHoja> = new Set([
    'validado',
    'corregido',
    'respondido',
    'decidido',
]);
