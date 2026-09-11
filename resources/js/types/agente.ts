export type RolMensajeAgente = 'cliente' | 'agente' | 'preparador' | 'sistema';

export type MensajeAgenteLog = {
    id: number;
    created_at: string;
    telefono: string;
    cliente_id: number | null;
    cliente_nombre: string | null;
    rol: RolMensajeAgente;
    contenido: string;
    proveedor: string | null;
    mensaje_externo_id: string | null;
    prompt_version: number | null;
};

export type FasePrompt = {
    fase: string;
    label: string;
    contenido: string;
};

export type AgenteTool = {
    nombre: string;
    descripcion: string;
    activo: boolean;
};

export type FaseTools = {
    fase: string;
    label: string;
    tools: AgenteTool[];
};

export type BaseConocimientoDocumento = {
    id: number;
    nombre_original: string;
    tamano: number;
    estado: 'procesado' | 'error';
    error_mensaje: string | null;
    subido_por: string | null;
    created_at: string;
};
