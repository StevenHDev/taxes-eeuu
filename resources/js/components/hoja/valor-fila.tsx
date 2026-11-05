import { Eye } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { show as confirmPasswordShow } from '@/actions/Laravel/Fortify/Http/Controllers/ConfirmablePasswordController';
import { useRevelar } from '@/components/ficha/use-revelar';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import type { FilaHoja } from '@/types/hoja-taxwise';

function formatearMonto(valor: string, idioma: string): string {
    const numero = Number(valor);

    if (!Number.isFinite(numero)) {
        return valor;
    }

    return new Intl.NumberFormat(idioma, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(numero);
}

function textoDe(fila: FilaHoja, valor: string, idioma: string): string {
    return fila.tipo === 'monto' ? formatearMonto(valor, idioma) : valor;
}

/** El valor de la fila: el dato, o su máscara con el botón "Revelar" si es sensible. */
export function ValorFila({ fila }: { fila: FilaHoja }) {
    const { t, i18n } = useTranslation();
    const r = useRevelar(fila.revelar_url ?? '');

    if (!fila.sensible) {
        return fila.valor === null ? (
            <span
                className="text-muted-foreground"
                aria-label={t('hoja.sinValor')}
            >
                —
            </span>
        ) : (
            <span className="font-medium tabular-nums">
                {textoDe(fila, fila.valor, i18n.language)}
            </span>
        );
    }

    if (r.valor !== undefined) {
        return (
            <span className="font-medium tabular-nums">
                {r.valor === null ? '—' : r.valor}
            </span>
        );
    }

    if (fila.mascara === null || fila.revelar_url === null) {
        return (
            <span
                className="text-muted-foreground"
                aria-label={t('hoja.sinValor')}
            >
                —
            </span>
        );
    }

    return (
        <span className="flex flex-wrap items-center gap-2">
            <span className="font-medium tabular-nums">{fila.mascara}</span>
            <Button
                type="button"
                variant="outline"
                size="sm"
                className="min-h-11 md:min-h-8 print:hidden"
                onClick={r.revelar}
                disabled={r.revelando}
                aria-label={t('hoja.revelarAria', {
                    descripcion: fila.descripcion,
                })}
            >
                {r.revelando ? (
                    <Spinner aria-hidden="true" />
                ) : (
                    <Eye aria-hidden="true" />
                )}
                {t('ficha.revelar')}
            </Button>
            {r.pideContrasena ? (
                <a
                    href={confirmPasswordShow().url}
                    className="text-xs text-amber-700 underline dark:text-amber-400 print:hidden"
                >
                    {t('ficha.confirmarContrasena')}
                </a>
            ) : null}
            {r.fallo ? (
                <span
                    role="alert"
                    className="text-xs text-destructive print:hidden"
                >
                    {t('ficha.revelarError')}
                </span>
            ) : null}
        </span>
    );
}
