export type RolMensajeAgente = 'cliente' | 'agente' | 'preparador' | 'sistema';

export type CanalMensajeAgente = 'whatsapp' | 'portal';

export type MensajeAgenteLog = {
    /** "whatsapp-12" o "portal-7": los ids de las dos tablas se repiten. */
    id: string;
    canal: CanalMensajeAgente;
    created_at: string;
    /** En el portal es el teléfono de la cuenta, que puede no tener. */
    telefono: string | null;
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

export type PortalMensaje = {
    id: number;
    rol: RolMensajeAgente;
    contenido: string;
    created_at: string;
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
