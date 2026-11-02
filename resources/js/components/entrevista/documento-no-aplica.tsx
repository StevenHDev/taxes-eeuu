import { useForm } from '@inertiajs/react';
import { useId, useState } from 'react';
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
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { noAplica } from '@/routes/portal/entrevista/documentos';

/**
 * "No aplica" en un documento: el diálogo pide el motivo (obligatorio, hasta
 * 500 caracteres) y lo muestra el error junto al campo (F3.3).
 */
export function DocumentoNoAplica({
    id,
    nombre,
}: {
    id: number;
    nombre: string;
}) {
    const { t } = useTranslation();
    const [abierto, setAbierto] = useState(false);
    const campoId = useId();
    const errorId = useId();
    const form = useForm({ motivo: '' });

    const enviar = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(noAplica(id).url, {
            preserveScroll: true,
            onSuccess: () => {
                setAbierto(false);
                form.reset();
            },
        });
    };

    return (
        <Dialog
            open={abierto}
            onOpenChange={(valor) => {
                setAbierto(valor);

                if (!valor) {
                    form.reset();
                    form.clearErrors();
                }
            }}
        >
            <DialogTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    aria-label={`${t('entrevista.documentos.noAplica')}: ${nombre}`}
                >
                    {t('entrevista.documentos.noAplica')}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <form onSubmit={enviar} className="grid gap-4">
                    <DialogHeader>
                        <DialogTitle>
                            {t('entrevista.documentos.noAplicaTitulo')}
                        </DialogTitle>
                        <DialogDescription>
                            {t('entrevista.documentos.noAplicaDescripcion', {
                                nombre,
                            })}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor={campoId}>
                            {t('entrevista.documentos.motivo')}
                        </Label>
                        <Textarea
                            id={campoId}
                            value={form.data.motivo}
                            onChange={(e) =>
                                form.setData('motivo', e.target.value)
                            }
                            maxLength={500}
                            rows={4}
                            required
                            aria-invalid={form.errors.motivo ? true : undefined}
                            aria-describedby={
                                form.errors.motivo ? errorId : undefined
                            }
                            placeholder={t(
                                'entrevista.documentos.motivoEjemplo',
                            )}
                        />
                        <InputError id={errorId} message={form.errors.motivo} />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setAbierto(false)}
                            disabled={form.processing}
                        >
                            {t('entrevista.documentos.cancelar')}
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? (
                                <Spinner aria-hidden="true" />
                            ) : null}
                            {t('entrevista.documentos.marcarNoAplica')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
