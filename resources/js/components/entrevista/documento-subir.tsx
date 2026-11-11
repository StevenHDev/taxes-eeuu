import { useForm } from '@inertiajs/react';
import { Upload } from 'lucide-react';
import { useId, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/portal/entrevista/documentos/archivos';

export const FORMATOS_MIME: Record<string, string> = {
    pdf: '.pdf,application/pdf',
    jpg: '.jpg,.jpeg,image/jpeg',
    png: '.png,image/png',
    heic: '.heic,image/heic',
};

/**
 * Los formatos como los lee una persona, en el idioma de la pantalla:
 * "PDF, JPG, PNG, HEIC, XLSX o CSV". `jpeg` no se nombra aparte de `jpg`.
 */
export function listaFormatos(formatos: string[], idioma: string): string {
    const nombres = [
        ...new Set(
            formatos.filter((f) => f !== 'jpeg').map((f) => f.toUpperCase()),
        ),
    ];

    return new Intl.ListFormat(idioma, {
        style: 'long',
        type: 'disjunction',
    }).format(nombres);
}

export function aceptar(formatos: string[]): string {
    return formatos.map((f) => FORMATOS_MIME[f] ?? `.${f}`).join(',');
}

/**
 * El primer error de los archivos: el general o el de cada archivo.
 */
export function errorDeArchivos(
    errores: Partial<Record<string, string>>,
): string | undefined {
    return (
        errores.archivos ??
        Object.entries(errores).find(([clave]) =>
            clave.startsWith('archivos.'),
        )?.[1]
    );
}

/**
 * Botón para subir uno o varios archivos a un documento de la lista (F3.3).
 * Sube apenas se eligen, con el avance en el botón y el error debajo.
 */
export function DocumentoSubir({
    id,
    nombre,
    formatos,
    etiqueta,
    descripcionId,
}: {
    id: number;
    nombre: string;
    formatos: string[];
    etiqueta: string;
    descripcionId?: string;
}) {
    const { t } = useTranslation();
    const inputRef = useRef<HTMLInputElement>(null);
    const errorId = useId();
    const form = useForm<{ archivos: File[] }>({ archivos: [] });
    const error = errorDeArchivos(form.errors);

    const subir = (archivos: FileList | null) => {
        if (!archivos || archivos.length === 0) {
            return;
        }

        form.transform(() => ({ archivos: Array.from(archivos) }));
        form.post(store(id).url, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => {
                if (inputRef.current) {
                    inputRef.current.value = '';
                }
            },
        });
    };

    return (
        <div className="space-y-2">
            <input
                ref={inputRef}
                type="file"
                multiple
                className="sr-only"
                tabIndex={-1}
                aria-hidden="true"
                accept={aceptar(formatos)}
                onChange={(e) => subir(e.target.files)}
            />
            <Button
                type="button"
                variant="outline"
                size="sm"
                disabled={form.processing}
                aria-describedby={
                    [descripcionId, error ? errorId : undefined]
                        .filter(Boolean)
                        .join(' ') || undefined
                }
                aria-label={`${etiqueta}: ${nombre}`}
                onClick={() => inputRef.current?.click()}
            >
                {form.processing ? (
                    <Spinner aria-hidden="true" />
                ) : (
                    <Upload aria-hidden="true" />
                )}
                {form.processing
                    ? form.progress
                        ? t('entrevista.documentos.subiendoPorcentaje', {
                              porcentaje: form.progress.percentage ?? 0,
                          })
                        : t('entrevista.documentos.subiendo')
                    : etiqueta}
            </Button>
            <InputError id={errorId} message={error} />
        </div>
    );
}
