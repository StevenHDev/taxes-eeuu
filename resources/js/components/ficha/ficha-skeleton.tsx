import { useTranslation } from 'react-i18next';
import { Skeleton } from '@/components/ui/skeleton';

/** Una tarjeta y dos tablas: lo que se ve mientras llega lo diferido. */
export function FichaSkeleton() {
    const { t } = useTranslation();

    return (
        <div
            className="space-y-6"
            role="status"
            aria-busy="true"
            aria-label={t('ficha.cargando')}
        >
            <Skeleton className="h-40 w-full rounded-xl" />
            {[0, 1].map((i) => (
                <div key={i} className="space-y-2">
                    <Skeleton className="h-5 w-40" />
                    <Skeleton className="h-11 w-full" />
                    <Skeleton className="h-11 w-full" />
                    <Skeleton className="h-11 w-full" />
                </div>
            ))}
        </div>
    );
}
