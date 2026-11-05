import { useForm } from '@inertiajs/react';
import { useId } from 'react';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { transicion } from '@/routes/clientes/revision';
import type { AccionRevision } from '@/types/revision';

// Fecha local de hoy (AAAA-MM-DD) para el `max` del campo.
function hoy(): string {
    return new Date().toLocaleDateString('en-CA');
}

/**
 * Confirma una transición del expediente. Vive fuera de `<Deferred>` y la
 * página lo reinicia con `key` por acción.
 */
export function TransicionDialog({
    clienteId,
    taxYear,
    accion,
    onClose,
}: {
    clienteId: number;
    taxYear: number;
    accion: AccionRevision | null;
    onClose: () => void;
}) {
    const { t } = useTranslation();
    const id = useId();
    const form = useForm({
        tax_year: taxYear,
        accion: accion?.accion ?? '',
        fecha: '',
        motivo: '',
    });
    const errores = form.errors as Record<string, string>;

    const confirmar = (e: React.FormEvent) => {
        e.preventDefault();

        if (accion === null) {
            return;
        }

        form.transform((d) => ({
            tax_year: d.tax_year,
            accion: accion.accion,
            ...(accion.requiere === 'fecha' ? { fecha: d.fecha } : {}),
            ...(accion.requiere === 'motivo' ? { motivo: d.motivo } : {}),
        }));
        form.post(transicion.url(clienteId), {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Dialog
            open={accion !== null}
            onOpenChange={(abierto) => {
                if (!abierto && !form.processing) {
                    onClose();
                }
            }}
        >
            <DialogContent>
                {accion && (
                    <form
                        onSubmit={confirmar}
                        noValidate
                        className="grid gap-4"
                    >
                        <DialogHeader>
                            <DialogTitle>{accion.etiqueta}</DialogTitle>
                            <DialogDescription>
                                {t(
                                    `revision.transicion.confirmar.${accion.accion}`,
                                )}
                            </DialogDescription>
                        </DialogHeader>

                        {accion.requiere === 'fecha' && (
                            <div className="space-y-2">
                                <Label htmlFor={`${id}-fecha`}>
                                    {t('revision.transicion.fecha')}
                                </Label>
                                <Input
                                    id={`${id}-fecha`}
                                    type="date"
                                    max={hoy()}
                                    value={form.data.fecha}
                                    onChange={(e) =>
                                        form.setData('fecha', e.target.value)
                                    }
                                    required
                                    className="h-11"
                                    aria-invalid={
                                        errores.fecha ? true : undefined
                                    }
                                    aria-describedby={
                                        errores.fecha
                                            ? `${id}-fecha-error`
                                            : undefined
                                    }
                                />
                                <InputError
                                    id={`${id}-fecha-error`}
                                    message={errores.fecha}
                                />
                            </div>
                        )}

                        {accion.requiere === 'motivo' && (
                            <div className="space-y-2">
                                <Label htmlFor={`${id}-motivo`}>
                                    {t('revision.transicion.motivo')}
                                </Label>
                                <Textarea
                                    id={`${id}-motivo`}
                                    value={form.data.motivo}
                                    onChange={(e) =>
                                        form.setData('motivo', e.target.value)
                                    }
                                    maxLength={1000}
                                    required
                                    aria-invalid={
                                        errores.motivo ? true : undefined
                                    }
                                    aria-describedby={
                                        errores.motivo
                                            ? `${id}-motivo-error`
                                            : undefined
                                    }
                                />
                                <InputError
                                    id={`${id}-motivo-error`}
                                    message={errores.motivo}
                                />
                            </div>
                        )}

                        <InputError message={errores.accion} role="alert" />

                        <DialogFooter className="gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                className="h-11 sm:h-9"
                                disabled={form.processing}
                                onClick={onClose}
                            >
                                {t('revision.transicion.cancelar')}
                            </Button>
                            <Button
                                type="submit"
                                className="h-11 sm:h-9"
                                disabled={form.processing}
                            >
                                {form.processing && <Spinner />}
                                {t('revision.transicion.confirmarBoton')}
                            </Button>
                        </DialogFooter>
                    </form>
                )}
            </DialogContent>
        </Dialog>
    );
}
