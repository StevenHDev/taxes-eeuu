import { useTranslation } from 'react-i18next';
import { Skeleton } from '@/components/ui/skeleton';

/** Tarjetas de estado y cuatro bloques: lo que se ve mientras llega el panel. */
export function PanelSkeleton() {
    const { t } = useTranslation();

    return (
        <div
            className="space-y-6"
            role="status"
            aria-busy="true"
            aria-label={t('panel.cargando')}
        >
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
                {Array.from({ length: 6 }, (_, i) => (
                    <Skeleton key={i} className="h-20 rounded-xl" />
                ))}
            </div>
            <div className="grid gap-4 lg:grid-cols-2">
                {Array.from({ length: 4 }, (_, i) => (
                    <Skeleton key={i} className="h-56 rounded-xl" />
                ))}
            </div>
        </div>
    );
}
