import { useTranslation } from 'react-i18next';
import { Skeleton } from '@/components/ui/skeleton';

/**
 * Mientras llega la tabla de la pestaña (prop diferida): la barra de
 * búsqueda y filas del mismo alto que la tabla real, para que no salte.
 */
export function TablaSkeleton({ filas = 8 }: { filas?: number }) {
    const { t } = useTranslation();

    return (
        <div
            className="space-y-4"
            role="status"
            aria-live="polite"
            aria-label={t('catalogoFiscal.cargando')}
        >
            <div className="flex flex-col gap-2 sm:flex-row">
                <Skeleton className="h-9 w-full rounded-full sm:max-w-xs" />
                <Skeleton className="h-9 w-28" />
                <Skeleton className="h-9 w-28" />
            </div>
            <div className="overflow-hidden rounded-xl border border-border/60 bg-card">
                <div className="border-b border-border/60 p-3">
                    <Skeleton className="h-4 w-1/3" />
                </div>
                {Array.from({ length: filas }, (_, i) => (
                    <div
                        key={i}
                        className="flex items-center gap-4 border-b border-border/40 p-3 last:border-0"
                    >
                        <Skeleton className="h-4 w-12" />
                        <Skeleton className="h-4 flex-1" />
                        <Skeleton className="hidden h-5 w-24 md:block" />
                        <Skeleton className="h-8 w-16" />
                    </div>
                ))}
            </div>
            <span className="sr-only">{t('catalogoFiscal.cargando')}</span>
        </div>
    );
}
