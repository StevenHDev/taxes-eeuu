import {
    BadgeCheck,
    CircleAlert,
    CircleCheck,
    CircleSlash,
    CircleX,
    Clock,
    MessageSquareWarning,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';
import type { EstadoDocumento } from '@/types/entrevista';

type Insignia = {
    icono: LucideIcon;
    variante: 'default' | 'secondary' | 'destructive' | 'outline';
};

const INSIGNIAS: Record<EstadoDocumento, Insignia> = {
    pendiente: { icono: CircleAlert, variante: 'outline' },
    subido: { icono: CircleCheck, variante: 'secondary' },
    leido: { icono: Clock, variante: 'secondary' },
    no_se_pudo_leer: { icono: Clock, variante: 'secondary' },
    con_observacion: { icono: MessageSquareWarning, variante: 'destructive' },
    validado: { icono: BadgeCheck, variante: 'default' },
    no_aplica: { icono: CircleSlash, variante: 'outline' },
    no_aplica_rechazado: { icono: CircleX, variante: 'destructive' },
};

/**
 * La insignia de estado de un documento (F3.3): siempre ícono y texto, nunca
 * solo color.
 */
export function DocumentoEstado({ estado }: { estado: EstadoDocumento }) {
    const { t } = useTranslation();
    const { icono: Icono, variante } = INSIGNIAS[estado];

    return (
        <Badge variant={variante} className="shrink-0">
            <Icono aria-hidden="true" />
            {t(`entrevista.documentos.estado.${estado}`)}
        </Badge>
    );
}
