import { useForm } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { useId } from 'react';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { nota as rutaNota } from '@/routes/portal/entrevista/documentos';

const MAXIMO = 1000;

/**
 * La nota del cliente en un documento de registros propios (F3.7): lo que los
 * papeles no dicen (meses vacíos, cobros o gastos en efectivo sin recibo).
 * Guardarla no cambia el estado del documento; vacía, la borra.
 */
export function DocumentoNota({
    id,
    nombre,
    nota,
}: {
    id: number;
    nombre: string;
    nota: string | null;
}) {
    const { t } = useTranslation();
    const campoId = useId();
    const ayudaId = useId();
    const errorId = useId();
    const form = useForm({ nota: nota ?? '' });

    const guardar = (e: React.FormEvent) => {
        e.preventDefault();
        form.patch(rutaNota(id).url, {
            preserveScroll: true,
            onSuccess: () => form.setDefaults({ nota: form.data.nota.trim() }),
        });
    };

    return (
        <form onSubmit={guardar} className="space-y-2">
            <Label htmlFor={campoId}>
                {t('entrevista.documentos.notaTitulo')}
            </Label>
            <p id={ayudaId} className="text-xs text-muted-foreground">
                {t('entrevista.documentos.notaAyuda')}
            </p>
            <Textarea
                id={campoId}
                value={form.data.nota}
                onChange={(e) => form.setData('nota', e.target.value)}
                maxLength={MAXIMO}
                rows={3}
                aria-invalid={form.errors.nota ? true : undefined}
                aria-describedby={`${ayudaId}${form.errors.nota ? ` ${errorId}` : ''}`}
                placeholder={t('entrevista.documentos.notaEjemplo')}
            />
            <InputError id={errorId} message={form.errors.nota} />
            <div className="flex flex-wrap items-center gap-3">
                <Button
                    type="submit"
                    variant="outline"
                    size="sm"
                    disabled={form.processing || !form.isDirty}
                    aria-label={`${t('entrevista.documentos.notaGuardar')}: ${nombre}`}
                >
                    {form.processing ? <Spinner aria-hidden="true" /> : null}
                    {form.processing
                        ? t('entrevista.documentos.notaGuardando')
                        : t('entrevista.documentos.notaGuardar')}
                </Button>
                <span
                    role="status"
                    aria-live="polite"
                    className="flex items-center gap-1 text-xs text-muted-foreground"
                >
                    {form.recentlySuccessful ? (
                        <>
                            <Check
                                className="size-3.5 text-emerald-600 dark:text-emerald-400"
                                aria-hidden="true"
                            />
                            {t('entrevista.documentos.notaGuardada')}
                        </>
                    ) : null}
                </span>
                <span className="ml-auto text-xs text-muted-foreground">
                    {form.data.nota.length}/{MAXIMO}
                </span>
            </div>
        </form>
    );
}
