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
