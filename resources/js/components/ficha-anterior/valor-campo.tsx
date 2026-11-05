import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { show as confirmPasswordShow } from '@/actions/Laravel/Fortify/Http/Controllers/ConfirmablePasswordController';
import { esComplejo } from '@/components/ficha-anterior/constantes';
import { FieldValue } from '@/components/ficha-anterior/field-value';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { humanizarClave } from '@/lib/humanizar-clave';
import { reveal as campoReveal } from '@/routes/clientes/campos';
import type { CampoCliente } from '@/types';

// los complejos (objeto o lista de objetos) se resumen con un botón que abre un
// popup ordenado. Integra el "Revelar" de los campos sensibles.
export function ValorCampo({
    clienteId,
    taxYear,
    campo,
    formaLabel,
}: {
    clienteId: number;
    taxYear: number;
    campo: CampoCliente;
    formaLabel: string;
}) {
    const { t } = useTranslation();
    const [revelado, setRevelado] = useState<unknown>(undefined);
    const [needsPassword, setNeedsPassword] = useState(false);

    const reveal = async () => {
        const response = await fetch(
            campoReveal({ cliente: clienteId, campo: campo.campo }).url +
                `?forma=${campo.forma}&tax_year=${taxYear}`,
            {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN':
                        document
                            .querySelector('meta[name="csrf-token"]')
                            ?.getAttribute('content') ?? '',
                },
            },
        );

        if (response.status === 423) {
            setNeedsPassword(true);

            return;
        }

        const json = await response.json();
        setRevelado(json.valor);
    };

    // El valor enmascarado (ej. "***-**-6789") ya viene del backend en campo.valor
    // — siempre se muestra, para que se vea que hay algo cargado sin revelarlo.
    const valor = revelado !== undefined ? revelado : campo.valor;

    if (
        valor === null ||
        valor === undefined ||
        (typeof valor === 'string' && valor.trim() === '')
    ) {
        return (
            <span className="text-muted-foreground">{t('common.none')}</span>
        );
    }

    const sensibleOculto = campo.es_sensible && revelado === undefined;
    const revealControl = sensibleOculto ? (
        needsPassword ? (
            <a
                href={confirmPasswordShow().url}
                className="text-xs text-amber-600 underline"
            >
                {t('clienteShow.reveal.confirmPassword')}
            </a>
        ) : (
            <Button variant="ghost" size="sm" onClick={reveal}>
                {t('clienteShow.reveal.reveal')}
            </Button>
        )
    ) : null;

    // Simple → en línea.
    if (!esComplejo(valor)) {
        return (
            <div className="flex flex-wrap items-center gap-2">
                <FieldValue value={valor} />
                {revealControl}
            </div>
        );
    }

    // Complejo → resumen + popup ordenado.
    const resumen = Array.isArray(valor)
        ? t('clienteShow.value.recordCount', { count: valor.length })
        : t('clienteShow.value.fieldCount', {
              count: Object.keys(valor as object).length,
          });

    return (
        <div className="flex flex-wrap items-center gap-2">
            <Dialog>
                <DialogTrigger asChild>
                    <Button variant="outline" size="sm">
                        {t('clienteShow.value.view')}
                        <span className="ml-1 text-muted-foreground">
                            · {resumen}
                        </span>
                    </Button>
                </DialogTrigger>
                <DialogContent className="sm:max-w-lg">
                    <DialogTitle>{humanizarClave(campo.campo)}</DialogTitle>
                    <DialogDescription>{formaLabel}</DialogDescription>
                    <div className="max-h-[70vh] overflow-y-auto pr-1">
                        <FieldValue value={valor} />
                    </div>
                </DialogContent>
            </Dialog>
            {revealControl}
        </div>
    );
}

// Una fila de campo, extraída de FormaSection para poder reusarla tanto en
// la tabla plana (formas que no son el catálogo transversal) como dentro de
