import { useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { corregir as rutaCorregir } from '@/routes/clientes/documentos/visor';
import type { DatoLeido } from '@/types/lectura';

/**
 * Corregir un dato: el valor nuevo y el motivo, cada uno con su error. El
 * valor inicial solo viene si ya se veía en pantalla (un sensible sin
 * revelar empieza vacío). El aviso de éxito lo manda el servidor.
 */
export function CorregirDatoDialog({
    clienteId,
    itemId,
    dato,
    valorInicial,
    onCerrar,
}: {
    clienteId: number;
    itemId: number;
    dato: DatoLeido;
    valorInicial: string;
    onCerrar: () => void;
}) {
    const { t } = useTranslation();
    const form = useForm({ valor: valorInicial, motivo: '' });
    const idValor = `corregir-${dato.id}-valor`;
    const idMotivo = `corregir-${dato.id}-motivo`;

    const guardar = () =>
        form.patch(
            rutaCorregir({ cliente: clienteId, item: itemId, dato: dato.id })
                .url,
            { preserveScroll: true, onSuccess: onCerrar },
        );

    return (
        <Dialog open onOpenChange={(abierto) => !abierto && onCerrar()}>
            <DialogContent className="sm:max-w-lg">
                <DialogTitle>
                    {t('visor.corregir.titulo', { casilla: dato.casilla })}
                </DialogTitle>
                <DialogDescription>{dato.descripcion}</DialogDescription>
                <form
                    id={`corregir-${dato.id}`}
                    className="space-y-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        guardar();
                    }}
                >
                    <div className="space-y-1.5">
                        <Label htmlFor={idValor}>
                            {t('visor.corregir.valor')}
                        </Label>
                        <Input
                            id={idValor}
                            value={form.data.valor}
                            onChange={(e) =>
                                form.setData('valor', e.target.value)
                            }
                            autoComplete="off"
                            aria-invalid={form.errors.valor ? true : undefined}
                            aria-describedby={
                                form.errors.valor
                                    ? `${idValor}-error`
                                    : undefined
                            }
                        />
                        {form.errors.valor ? (
                            <p
                                id={`${idValor}-error`}
                                role="alert"
                                className="text-xs text-destructive"
                            >
                                {form.errors.valor}
                            </p>
                        ) : null}
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor={idMotivo}>
                            {t('visor.corregir.motivo')}
                        </Label>
                        <Textarea
                            id={idMotivo}
                            value={form.data.motivo}
                            onChange={(e) =>
                                form.setData('motivo', e.target.value)
                            }
                            rows={3}
                            aria-invalid={form.errors.motivo ? true : undefined}
                            aria-describedby={
                                form.errors.motivo
                                    ? `${idMotivo}-error`
                                    : undefined
                            }
                        />
                        {form.errors.motivo ? (
                            <p
                                id={`${idMotivo}-error`}
                                role="alert"
                                className="text-xs text-destructive"
                            >
                                {form.errors.motivo}
                            </p>
                        ) : null}
                    </div>
                </form>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="ghost"
                        onClick={onCerrar}
                        disabled={form.processing}
                    >
                        {t('visor.corregir.cancelar')}
                    </Button>
                    <Button
                        type="submit"
                        form={`corregir-${dato.id}`}
                        disabled={form.processing}
                    >
                        {form.processing ? (
                            <Spinner aria-hidden="true" />
                        ) : null}
                        {t('visor.corregir.guardar')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
