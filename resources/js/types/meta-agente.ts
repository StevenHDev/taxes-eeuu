export type ConversacionAgente = {
    telefono: string;
    cliente_id: number | null;
    cliente_nombre: string | null;
    ultimo_mensaje: string;
    total_mensajes: number;
};

export type SeveridadHallazgo = 'alta' | 'media' | 'baja';

export type HallazgoMetaAgente = {
    severidad: SeveridadHallazgo;
    categoria: string;
    resumen: string;
    evidencia: string;
};

export type OrigenAnalisisMetaAgente = 'manual' | 'programado';

export type ReporteMetaAgente = {
    id: number;
    telefono: string;
    cliente_nombre: string | null;
    rango_desde: string;
    rango_hasta: string;
    mensajes_analizados: number;
    modelo: string;
    origen: OrigenAnalisisMetaAgente;
    disparado_por_nombre: string | null;
    hallazgos: HallazgoMetaAgente[];
    created_at: string;
};
