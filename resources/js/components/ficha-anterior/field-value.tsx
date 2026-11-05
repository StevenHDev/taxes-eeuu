import { Fragment } from 'react';
import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';
import { humanizarClave } from '@/lib/humanizar-clave';

// Renderiza cualquier valor recolectado de forma amigable para un usuario no
// técnico: sin corchetes ni llaves. Strings/números tal cual, listas como
// etiquetas, objetos como pares "campo: valor", y listas de objetos como fichas.
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
        // Monoespaciada solo donde aporta: cifras alineadas y 0/O
        // inconfundibles en SSN, montos, fechas y valores enmascarados. Un
        // nombre o una dirección se leen peor en mono, así que el texto sin
        // dígitos se queda en la sans.
        const esDato = typeof value === 'number' || /\d/.test(value);

        return (
            <span
                className={`wrap-break-word whitespace-pre-wrap ${
                    esDato ? 'font-mono tabular-nums' : ''
                }`}
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

// Un valor es "complejo" (objeto o lista de objetos) si merece verse en un
// popup ordenado en vez de amontonado dentro de la celda. Los escalares y las
