import { CircleAlert, CircleCheck } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';
import type { FuenteIrs } from '@/types';

/**
 * "Validado" o "Sin validar" contra el IRS. No depende solo del color:
 * lleva ícono y texto.
 */
export function EstadoValidacion({ pieza }: { pieza: FuenteIrs }) {
    const { t } = useTranslation();

    return pieza.validado_en ? (
        <Badge
            variant="outline"
            className="gap-1 border-emerald-600/40 text-emerald-800 dark:text-emerald-300"
        >
            <CircleCheck aria-hidden="true" />
            {t('catalogoFiscal.validacion.validado')}
        </Badge>
    ) : (
        <Badge
            variant="outline"
            className="gap-1 border-amber-600/40 text-amber-800 dark:text-amber-300"
        >
            <CircleAlert aria-hidden="true" />
            {t('catalogoFiscal.validacion.sinValidar')}
        </Badge>
    );
}

/** Valor para el filtro por estado de validación de DataTable. */
export function estadoValidacion(pieza: FuenteIrs): 'validado' | 'sin_validar' {
    return pieza.validado_en ? 'validado' : 'sin_validar';
}
