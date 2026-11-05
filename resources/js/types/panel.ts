import type { EstadoExpediente, Turno } from './expediente';

export type PerfilClave =
    | 'asalariado'
    | 'independiente'
    | 'jubilacion'
    | 'inversiones'
    | 'alquiler'
    | 'marketplace'
    | 'dependientes'
    | 'k1';

export type CanalInicio = 'whatsapp' | 'portal' | 'preparador' | 'migracion';

export type OpcionLista = { valor: string; label: string };

export type ResumenDocumentos = {
    faltan: number;
    por_revisar: number;
    validados: number;
    no_aplica: number;
    total: number;
    completo: boolean;
};

export type ClienteLista = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    es_prueba: boolean;
    expediente: {
        estado: EstadoExpediente;
        label: string;
        desde: string | null;
        turno: Turno;
        vence_en: string | null;
    };
    perfil: PerfilClave[];
    canal_inicio: CanalInicio | null;
    excepciones: { total: number; bloqueantes: number };
    documentos: ResumenDocumentos | null;
    created_at: string;
};

export type EstadoPanel = {
    estado: EstadoExpediente;
    label: string;
    cantidad: number;
    url: string;
};

export type TipoAviso =
    | 'listo_para_preparar'
    | 'termino_entrevista'
    | 'subio_documentos'
    | 'respondio_consulta';

export type AvisoPanel = {
    tipo: TipoAviso;
    texto: string;
    cliente_id: number;
    nombre: string;
    creado_en: string;
    url: string;
};

export type VencimientoPanel = {
    cliente_id: number;
    nombre: string;
    estado: EstadoExpediente;
    estado_label: string;
    vence_en: string;
    dias: number;
    url: string;
};

export type TiemposPanel = {
    dias_hasta_listo: number | null;
    horas_preparacion: number | null;
    consultas_por_expediente: number;
    correcciones_por_expediente: number;
    muestra: number;
};

export type PanelResumen = {
    por_estado: EstadoPanel[];
    colas: {
        listos: number;
        por_validar: number;
        en_consulta: number;
        excepciones: number;
        bloqueantes: number;
        urls: {
            listos: string;
            por_validar: string;
            en_consulta: string;
            excepciones: string;
        };
    };
    vencen_pronto: VencimientoPanel[];
    avisos: AvisoPanel[];
    tiempos: TiemposPanel | null;
    ultimos_clientes: { id: number; name: string }[];
};
