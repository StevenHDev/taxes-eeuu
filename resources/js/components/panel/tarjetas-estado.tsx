import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { EstadoExpedienteBadge } from '@/components/ficha/estado-expediente';
import { cn } from '@/lib/utils';
import type { EstadoPanel } from '@/types/panel';

/** Una tarjeta por estado; las que tienen expedientes van primero. */
export function TarjetasEstado({ estados }: { estados: EstadoPanel[] }) {
    const { t } = useTranslation();
    const ordenadas = [
        ...estados.filter((e) => e.cantidad > 0),
        ...estados.filter((e) => e.cantidad === 0),
    ];

    return (
        <section aria-labelledby="panel-por-estado" className="space-y-3">
            <h2 id="panel-por-estado" className="text-sm font-semibold">
                {t('panel.porEstado.titulo')}
            </h2>
            <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
                {ordenadas.map((e) => (
                    <li key={e.estado}>
                        <Link
                            href={e.url}
                            aria-label={t('panel.porEstado.abrir', {
                                estado: e.label,
                                count: e.cantidad,
                            })}
                            className={cn(
                                'flex min-h-20 flex-col justify-between gap-2 rounded-xl border bg-card p-3 shadow-sm transition-colors',
                                'hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none active:bg-accent/70',
                                e.cantidad === 0 && 'opacity-60',
                            )}
                        >
                            <EstadoExpedienteBadge
                                estado={e.estado}
                                label={e.label}
                                className="max-w-full self-start"
                            />
                            <span className="text-2xl font-semibold tabular-nums">
                                {e.cantidad}
                            </span>
                        </Link>
                    </li>
                ))}
            </ul>
        </section>
    );
}
