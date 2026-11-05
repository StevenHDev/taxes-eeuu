import {
    AlertTriangle,
    CheckCircle2,
    CircleDashed,
    FileText,
    MessageSquareText,
    PenLine,
    ShieldAlert,
    UserCheck,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { EstadoFilaHoja } from '@/types/hoja-taxwise';

const ESTADOS: Record<EstadoFilaHoja, { icono: LucideIcon; tono: string }> = {
    con_excepcion: {
        icono: ShieldAlert,
        tono: 'text-destructive',
    },
    pendiente: {
        icono: CircleDashed,
        tono: 'text-amber-700 dark:text-amber-400',
    },
    baja_confianza: {
        icono: AlertTriangle,
        tono: 'text-amber-700 dark:text-amber-400',
    },
    corregido: {
        icono: PenLine,
        tono: 'text-emerald-700 dark:text-emerald-400',
    },
    validado: {
        icono: CheckCircle2,
        tono: 'text-emerald-700 dark:text-emerald-400',
    },
    sin_validar: {
        icono: CircleDashed,
        tono: 'text-muted-foreground',
    },
    respondido: {
        icono: MessageSquareText,
        tono: 'text-emerald-700 dark:text-emerald-400',
    },
    decidido: {
        icono: UserCheck,
        tono: 'text-emerald-700 dark:text-emerald-400',
    },
    manual: {
        icono: FileText,
        tono: 'text-muted-foreground',
    },
};

/** Ícono y texto del estado: nunca solo color. */
export function EstadoFila({
    estado,
    texto,
}: {
    estado: EstadoFilaHoja;
    texto: string;
}) {
    const { icono: Icono, tono } = ESTADOS[estado];

    return (
        <p className={cn('flex items-start gap-1.5 text-sm', tono)}>
            <Icono className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            <span>{texto}</span>
        </p>
    );
}
