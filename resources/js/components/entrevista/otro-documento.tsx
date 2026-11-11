import { useForm } from '@inertiajs/react';
import { Upload } from 'lucide-react';
import { useId, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import {
    aceptar,
    errorDeArchivos,
    listaFormatos,
} from '@/components/entrevista/documento-subir';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/portal/entrevista/documentos/otro';

const FORMATOS = ['pdf', 'jpg', 'png', 'heic'];

/**
 * "Otro documento" al final de la lista (F3.3): una descripción y sus
 * archivos, para lo que el cliente quiere entregar y no está en la lista.
 */
export function OtroDocumento() {
    const { t, i18n } = useTranslation();
    const inputRef = useRef<HTMLInputElement>(null);
    const descripcionId = useId();
    const descripcionErrorId = useId();
    const archivosErrorId = useId();
    const form = useForm<{ descripcion: string; archivos: File[] }>({
        descripcion: '',
        archivos: [],
    });
    const errorArchivos = errorDeArchivos(form.errors);

    const elegir = (archivos: FileList | null) => {
        if (!archivos || archivos.length === 0) {
            return;
        }

        form.setData('archivos', Array.from(archivos));
    };

    const enviar = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(store().url, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => form.reset(),
            onFinish: () => {
                if (inputRef.current) {
                    inputRef.current.value = '';
                }
            },
        });
    };

    return (
        <section
            aria-labelledby="otro-documento"
            className="space-y-3 rounded-lg border border-dashed border-border bg-card p-4"
        >
            <div className="space-y-0.5">
                <h2
                    id="otro-documento"
                    className="text-base font-medium text-foreground"
                >
                    {t('entrevista.documentos.otroTitulo')}
                </h2>
                <p className="text-sm text-muted-foreground">
                    {t('entrevista.documentos.otroDescripcion')}
                </p>
            </div>
            <form onSubmit={enviar} className="space-y-3">
                <div className="grid gap-2">
                    <Label htmlFor={descripcionId}>
                        {t('entrevista.documentos.otroQueEs')}
                    </Label>
                    <Input
                        id={descripcionId}
                        value={form.data.descripcion}
                        onChange={(e) =>
                            form.setData('descripcion', e.target.value)
                        }
                        maxLength={120}
                        required
                        aria-invalid={
                            form.errors.descripcion ? true : undefined
                        }
                        aria-describedby={
                            form.errors.descripcion
                                ? descripcionErrorId
                                : undefined
                        }
                        placeholder={t('entrevista.documentos.otroEjemplo')}
                    />
                    <InputError
                        id={descripcionErrorId}
                        message={form.errors.descripcion}
                    />
                </div>
                <div className="space-y-2">
                    <input
                        ref={inputRef}
                        type="file"
                        multiple
                        className="sr-only"
                        tabIndex={-1}
                        aria-hidden="true"
                        accept={aceptar(FORMATOS)}
                        onChange={(e) => elegir(e.target.files)}
                    />
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={form.processing}
                            aria-describedby={
                                errorArchivos ? archivosErrorId : undefined
                            }
                            onClick={() => inputRef.current?.click()}
                        >
                            <Upload aria-hidden="true" />
                            {t('entrevista.documentos.elegirArchivos')}
                        </Button>
                        <p
                            className="text-sm text-muted-foreground"
                            aria-live="polite"
                        >
                            {form.data.archivos.length > 0
                                ? form.data.archivos
                                      .map((a) => a.name)
                                      .join(', ')
                                : t('entrevista.documentos.sinArchivos')}
                        </p>
                    </div>
                    <InputError id={archivosErrorId} message={errorArchivos} />
                </div>
                <Button
                    type="submit"
                    variant="outline"
                    size="sm"
                    disabled={form.processing}
                >
                    {form.processing ? <Spinner aria-hidden="true" /> : null}
                    {form.processing
                        ? t('entrevista.documentos.subiendo')
                        : t('entrevista.documentos.otroSubir')}
                </Button>
                <p className="text-xs text-muted-foreground">
                    {t('entrevista.documentos.formatos', {
                        lista: listaFormatos(FORMATOS, i18n.language),
                    })}
                </p>
            </form>
        </section>
    );
}
