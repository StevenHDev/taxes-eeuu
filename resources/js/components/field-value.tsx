import { Fragment } from 'react';
import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';
import { humanizarClave } from '@/lib/humanizar-clave';

/**
 * Renderiza cualquier valor guardado en CampoCliente.valor (string, number,
 * boolean, array u object anidado) de forma legible — recursivo para
 * array_object/object con subcampos. Compartido entre mi-informacion.tsx (la
 * vista de solo lectura) y portal/formulario.tsx (el resumen de un campo ya
 * respondido, antes de entrar a modo edición).
 */
export function FieldValue({ value }: { value: unknown }) {
    const { t } = useTranslation();

    if (
        value === null ||
        value === undefined ||
        (typeof value === 'string' && value.trim() === '')
    ) {
        return (
            <span className="text-muted-foreground">{t('common.none')}</span>
        );
    }

    if (typeof value === 'boolean') {
        return <span>{value ? t('common.yes') : t('common.no')}</span>;
    }

    if (typeof value === 'number' || typeof value === 'string') {
        const esDato = typeof value === 'number' || /\d/.test(value);

        return (
            <span
                className={`wrap-break-word whitespace-pre-wrap ${esDato ? 'font-mono tabular-nums' : ''}`}
            >
                {String(value)}
            </span>
        );
    }

    if (Array.isArray(value)) {
        if (value.length === 0) {
            return (
                <span className="text-muted-foreground">
                    {t('clienteShow.value.emptyList')}
                </span>
            );
        }

        const soloPrimitivos = value.every(
            (v) => v === null || typeof v !== 'object',
        );

        if (soloPrimitivos) {
            return (
                <div className="flex flex-wrap gap-1">
                    {value.map((v, i) => (
                        <Badge
                            key={i}
                            variant="secondary"
                            className="font-normal"
                        >
                            {String(v)}
                        </Badge>
                    ))}
                </div>
            );
        }

        return (
            <div className="space-y-2">
                {value.map((v, i) => (
                    <div key={i} className="rounded-md border bg-muted/30 p-2">
                        <div className="mb-1 text-xs font-medium text-muted-foreground">
                            {t('clienteShow.value.record', { n: i + 1 })}
                        </div>
                        <FieldValue value={v} />
                    </div>
                ))}
            </div>
        );
    }

    if (typeof value === 'object') {
        const entries = Object.entries(value as Record<string, unknown>);

        if (entries.length === 0) {
            return (
                <span className="text-muted-foreground">
                    {t('clienteShow.value.emptyList')}
                </span>
            );
        }

        return (
            <dl className="grid gap-x-3 gap-y-1 sm:grid-cols-[minmax(0,auto)_1fr]">
                {entries.map(([k, v]) => (
                    <Fragment key={k}>
                        <dt className="text-xs font-medium text-muted-foreground sm:text-right">
                            {humanizarClave(k)}
                        </dt>
                        <dd className="text-sm">
                            <FieldValue value={v} />
                        </dd>
                    </Fragment>
                ))}
            </dl>
        );
    }

    return <span>{String(value)}</span>;
}
