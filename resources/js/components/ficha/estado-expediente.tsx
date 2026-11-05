import {
    BadgeCheck,
    CircleCheck,
    CircleDashed,
    CircleHelp,
    CircleX,
    ClipboardCheck,
    FileCheck2,
    FileSearch,
    FileUp,
    MessagesSquare,
    PenLine,
    Send,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { EstadoExpediente, Turno } from '@/types/expediente';

const ICONOS: Record<EstadoExpediente, LucideIcon> = {
    invitado: CircleDashed,
    en_entrevista: MessagesSquare,
    en_consulta: CircleHelp,
    en_recoleccion: FileUp,
    por_validar: ClipboardCheck,
    listo_para_preparar: CircleCheck,
    en_revision: FileSearch,
    preparado_en_taxwise: FileCheck2,
    esperando_firma: PenLine,
    presentado: Send,
    aceptado: BadgeCheck,
    rechazado: CircleX,
};

// Los estados "rechazado" y "aceptado" pesan más que el turno.
function variante(
    estado: EstadoExpediente,
    turno: Turno | null,
): 'default' | 'secondary' | 'outline' | 'destructive' {
    if (estado === 'rechazado') {
        return 'destructive';
    }

    if (estado === 'aceptado') {
        return 'default';
    }

    if (turno === 'preparador') {
        return 'default';
    }

    if (turno === 'cliente') {
        return 'secondary';
    }

    return 'outline';
}

export function EstadoExpedienteBadge({
    estado,
    label,
    turno = null,
    className,
}: {
    estado: EstadoExpediente;
    label: string;
    turno?: Turno | null;
    className?: string;
}) {
    const Icono = ICONOS[estado];

    return (
        <Badge variant={variante(estado, turno)} className={cn(className)}>
            <Icono aria-hidden="true" />
            {label}
        </Badge>
    );
}
