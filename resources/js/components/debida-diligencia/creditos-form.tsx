import { useForm } from '@inertiajs/react';
import { Check, Lightbulb } from 'lucide-react';
import { useId } from 'react';
import { useTranslation } from 'react-i18next';
import { creditos as rutaCreditos } from '@/actions/App/Http/Controllers/DebidaDiligenciaController';
import { formatearFechaHora } from '@/components/debida-diligencia/formato-fecha';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type { DebidaDiligenciaVista } from '@/types/debida-diligencia';

type Props = {
    clienteId: number;
    taxYear: number;
    debida: DebidaDiligenciaVista;
};

/** Se monta con `key`: al guardar y recargar, el formulario se reinicia. */
export function CreditosForm({ clienteId, taxYear, debida }: Props) {
    const { t, i18n } = useTranslation();
    const ayudaId = useId();
    const form = useForm<{ creditos: string[]; tax_year: number }>({
        creditos: debida.creditos
            .filter((c) => c.reclamado)
            .map((c) => c.credito),
        tax_year: taxYear,
    });
    const error = form.errors.creditos ?? form.errors.tax_year;

    const alternar = (credito: string, marcado: boolean) => {
        form.setData(
            'creditos',
            marcado
                ? [...form.data.creditos, credito]
                : form.data.creditos.filter((c) => c !== credito),
        );
    };

    const guardar = (e: React.FormEvent) => {
        e.preventDefault();
        form.put(rutaCreditos(clienteId).url, { preserveScroll: true });
    };

    const reclamados = debida.creditos.filter((c) => c.reclamado);

    return (
        <form onSubmit={guardar} className="space-y-4" noValidate>
            <fieldset className="space-y-3" aria-describedby={ayudaId}>
                <legend className="text-base font-semibold">
                    {t('debidaDiligencia.creditos.titulo')}
                </legend>
                <p id={ayudaId} className="text-sm text-muted-foreground">
                    {t('debidaDiligencia.creditos.ayuda')}
                </p>

                <ul className="space-y-1 print:hidden">
                    {debida.creditos.map((c) => {
                        const id = `credito-${c.credito}`;

                        return (
                            <li key={c.credito} className="rounded-lg">
                                <div className="flex min-h-11 items-center gap-3 rounded-lg px-2 hover:bg-muted/50">
                                    <Checkbox
                                        id={id}
                                        checked={form.data.creditos.includes(
                                            c.credito,
                                        )}
                                        onCheckedChange={(v) =>
                                            alternar(c.credito, v === true)
                                        }
                                        disabled={form.processing}
                                    />
                                    <Label
                                        htmlFor={id}
                                        className="flex-1 cursor-pointer py-2 leading-snug"
                                    >
                                        {c.etiqueta}
                                    </Label>
                                </div>
                                {c.sugerido && c.motivo_sugerencia && (
                                    <p className="flex items-start gap-1.5 pb-1 pl-10 text-sm text-muted-foreground">
                                        <Lightbulb
                                            className="mt-0.5 size-4 shrink-0"
                                            aria-hidden="true"
                                        />
                                        <span>
                                            {t(
                                                'debidaDiligencia.creditos.sugerido',
                                                {
                                                    motivo: c.motivo_sugerencia,
                                                },
                                            )}
                                        </span>
                                    </p>
                                )}
                            </li>
                        );
                    })}
                </ul>

                {/* Al imprimir no hay formulario: solo los créditos que se reclaman. */}
                <ul className="hidden list-disc pl-5 text-sm print:block">
                    {reclamados.map((c) => (
                        <li key={c.credito}>{c.etiqueta}</li>
                    ))}
                </ul>
            </fieldset>

            {error && (
                <p role="alert" className="text-sm text-destructive">
                    {error}
                </p>
            )}

            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between print:hidden">
                <p className="text-sm text-muted-foreground">
                    {debida.creditos_por && debida.creditos_en
                        ? t('debidaDiligencia.creditos.marcadoPor', {
                              nombre: debida.creditos_por,
                              fecha: formatearFechaHora(
                                  debida.creditos_en,
                                  i18n.language,
                              ),
                          })
                        : t('debidaDiligencia.creditos.sinConfirmar')}
                </p>
                <Button
                    type="submit"
                    className="min-h-11 w-full sm:w-auto"
                    disabled={form.processing}
                >
                    {form.processing ? (
                        <Spinner />
                    ) : (
                        <Check aria-hidden="true" />
                    )}
                    {t('debidaDiligencia.creditos.guardar')}
                </Button>
            </div>
        </form>
    );
}
