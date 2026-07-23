import type { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';

/**
 * Tarjeta de métrica reutilizable del dashboard: ícono + etiqueta arriba,
 * valor grande (en fuente display) y una línea de contexto (hint) abajo.
 * Se usa tanto en la fila de KPIs como en el "rail" de estados junto al
 * gráfico de actividad.
 */
export function StatCard({
    icon: Icon,
    label,
    value,
    hint,
    tag,
    accent = 'default',
    className,
}: {
    icon: LucideIcon;
    label: string;
    value: string | number;
    hint?: string;
    tag?: string;
    accent?: 'default' | 'muted' | 'strong';
    className?: string;
}) {
    const iconClasses = {
        default: 'bg-secondary text-primary',
        muted: 'bg-muted text-muted-foreground',
        strong: 'bg-primary text-primary-foreground',
    }[accent];

    return (
        <div
            className={cn(
                'group rounded-xl border border-border/60 bg-card p-5 shadow-sm transition-all duration-300',
                'hover:-translate-y-0.5 hover:border-border hover:shadow-md',
                className,
            )}
        >
            <div className="flex items-start justify-between gap-2">
                <div className="flex items-center gap-2.5">
                    <span
                        className={cn(
                            'flex size-9 items-center justify-center rounded-lg transition-transform duration-300 group-hover:scale-105',
                            iconClasses,
                        )}
                    >
                        <Icon className="size-4" />
                    </span>
                    <span className="text-sm font-medium text-muted-foreground">
                        {label}
                    </span>
                </div>
                {tag && (
                    <span className="shrink-0 rounded-full bg-secondary/60 px-2 py-0.5 text-[11px] font-medium text-muted-foreground">
                        {tag}
                    </span>
                )}
            </div>
            <p className="mt-4 text-4xl font-semibold leading-none tracking-tight tabular-nums text-foreground">
                {value}
            </p>
            {hint && (
                <p className="mt-2 text-xs leading-relaxed text-muted-foreground">
                    {hint}
                </p>
            )}
        </div>
    );
}
