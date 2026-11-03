export type SeveridadExcepcion = 'bloqueante' | 'revision' | 'informativa';

export type EstadoExcepcion = 'abierta' | 'consultada';

/** El motivo de la confianza en palabras; solo viene en las excepciones de R-03. */
export type MotivoConfianza = {
    valor: string;
    titulo: string;
    explicacion: string;
};

/** Una excepción de la cola del preparador (F4.7). */
export type Excepcion = {
    id: number;
    regla: { codigo: string; descripcion: string };
    severidad: SeveridadExcepcion;
    estado: EstadoExcepcion;
    cliente: { id: number; nombre: string };
    item: { id: number; documento_codigo: string; nombre: string } | null;
    casilla: string | null;
    motivo_confianza: MotivoConfianza | null;
    creada_en: string;
    enlaces: {
        corregir: string | null;
        aceptar: string;
        consultar: string | null;
    };
};

export type FiltrosExcepciones = {
    severidad: SeveridadExcepcion | null;
    estado: EstadoExcepcion | null;
    cliente: number | null;
};

/** El conteo de la ficha del cliente. */
export type ExcepcionesAbiertas = { total: number; bloqueantes: number };
