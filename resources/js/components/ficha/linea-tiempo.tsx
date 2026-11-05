import { ArrowRight, RotateCcw } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { EstadoExpedienteBadge } from '@/components/ficha/estado-expediente';
import { cn } from '@/lib/utils';
import type { EventoExpediente } from '@/types/expediente';

/** Fecha corta (o con hora) en el idioma activo; las de solo día se leen locales. */
export function useFormatoFecha() {
    const { i18n } = useTranslation();

    return (iso: string, conHora = false) => {
        const fecha = /^\d{4}-\d{2}-\d{2}$/.test(iso)
            ? new Date(`${iso}T00:00:00`)
            : new Date(iso);

        if (Number.isNaN(fecha.getTime())) {
            return iso;
        }

        return new Intl.DateTimeFormat(i18n.language, {
            dateStyle: 'medium',
            ...(conHora ? { timeStyle: 'short' as const } : {}),
        }).format(fecha);
    };
}

/**
 * Los eventos del expediente, del más nuevo al más viejo (el servidor ya los
 * ordena). Los `reabierto` se resaltan con ícono y texto, no solo con color.
 */
export function LineaTiempo({ eventos }: { eventos: EventoExpediente[] }) {
    const { t } = useTranslation();
    const fecha = useFormatoFecha();

    return (
        <ol className="space-y-2">
            {eventos.map((evento) => {
                const reabierto = evento.tipo === 'reabierto';

                return (
                    <li
                        key={evento.id}
                        className={cn(
                            'flex gap-3 rounded-lg border p-3',
                            reabierto && 'border-amber-500/60 bg-amber-500/10',
                        )}
                    >
                        {reabierto ? (
                            <RotateCcw
                                className="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400"
                                aria-hidden="true"
                            />
                        ) : (
                            <ArrowRight
                                className="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                aria-hidden="true"
                            />
                        )}
                        <div className="min-w-0 flex-1 space-y-1.5">
                            <p className="flex flex-wrap items-center gap-x-2 gap-y-1.5 text-sm">
                                <time
                                    dateTime={evento.creado_en}
                                    className="font-mono text-xs text-muted-foreground"
                                >
                                    {fecha(evento.creado_en, true)}
                                </time>
                                {reabierto || !evento.estado_nuevo ? (
                                    <span className="font-medium">
                                        {evento.tipo_label}
                                    </span>
                                ) : null}
                                {evento.estado_nuevo &&
                                evento.estado_nuevo_label ? (
                                    <EstadoExpedienteBadge
                                        estado={evento.estado_nuevo}
                                        label={evento.estado_nuevo_label}
                                    />
                                ) : null}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {evento.actor
                                    ? t('ficha.linea.por', {
                                          quien: evento.actor,
                                      })
                                    : t('ficha.linea.sistema')}
                            </p>
                            {evento.motivo ? (
                                <p className="text-sm wrap-break-word">
                                    {evento.motivo}
                                </p>
                            ) : null}
                        </div>
                    </li>
                );
            })}
        </ol>
    );
}
