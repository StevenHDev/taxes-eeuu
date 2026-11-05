import { Link } from '@inertiajs/react';
import { CalendarClock, TriangleAlert } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { fechaCorta } from '@/components/panel/formato';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { VencimientoPanel } from '@/types/panel';

export function VencenPronto({ filas }: { filas: VencimientoPanel[] }) {
    const { t, i18n } = useTranslation();

    return (
        <Card id="panel-vencen" className="scroll-mt-4">
            <CardHeader>
                <CardTitle className="flex items-center gap-2 text-sm">
                    <CalendarClock aria-hidden="true" className="size-4" />
                    {t('panel.vencen.titulo')}
                </CardTitle>
            </CardHeader>
            <CardContent>
                {filas.length === 0 ? (
                    <p className="py-4 text-sm text-muted-foreground">
                        {t('panel.vencen.vacio')}
                    </p>
                ) : (
                    <ul className="-mx-2 divide-y">
                        {filas.map((f) => (
                            <li key={f.cliente_id}>
                                <Link
                                    href={f.url}
                                    className="flex min-h-11 items-center gap-3 rounded-lg px-2 py-2 transition-colors hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none active:bg-accent/70"
                                >
                                    <span className="min-w-0 flex-1">
                                        <span className="block truncate text-sm font-medium">
                                            {f.nombre}
                                        </span>
                                        <span className="block truncate text-xs text-muted-foreground">
                                            {f.estado_label} ·{' '}
                                            {fechaCorta(
                                                f.vence_en,
                                                i18n.language,
                                            )}
                                        </span>
                                    </span>
                                    <span
                                        className={
                                            f.dias < 0
                                                ? 'flex items-center gap-1 text-sm font-semibold text-destructive'
                                                : 'text-sm font-semibold tabular-nums'
                                        }
                                    >
                                        {f.dias < 0 ? (
                                            <TriangleAlert
                                                aria-hidden="true"
                                                className="size-4"
                                            />
                                        ) : null}
                                        {f.dias === 0
                                            ? t('panel.vencen.hoy')
                                            : f.dias < 0
                                              ? t('panel.vencen.vencido', {
                                                    count: Math.abs(f.dias),
                                                })
                                              : t('panel.vencen.enDias', {
                                                    count: f.dias,
                                                })}
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}
