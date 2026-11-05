import { Link, useForm } from '@inertiajs/react';
import { CircleCheck, CircleX, MinusCircle } from 'lucide-react';
import { useId } from 'react';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
import { useFecha } from '@/components/revision/use-fecha';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { punto as puntoRoute } from '@/routes/clientes/revision';
import type { PuntoRevision, Revision } from '@/types/revision';

function EstadoPunto({ punto }: { punto: PuntoRevision }) {
    const { t } = useTranslation();

    if (!punto.requerido) {
        return (
            <span className="inline-flex items-center gap-1.5 text-sm text-muted-foreground">
                <MinusCircle className="size-4" aria-hidden="true" />
                {t('revision.checklist.noAplica')}
            </span>
        );
    }

    return punto.cumplido ? (
        <span className="inline-flex items-center gap-1.5 text-sm font-medium text-emerald-700 dark:text-emerald-400">
            <CircleCheck className="size-4" aria-hidden="true" />
            {t('revision.checklist.cumplido')}
        </span>
    ) : (
        <span className="inline-flex items-center gap-1.5 text-sm font-medium text-destructive">
            <CircleX className="size-4" aria-hidden="true" />
            {t('revision.checklist.falta')}
        </span>
    );
}

/** Una fila; la lista la reinicia con `key` cuando cambia lo confirmado. */
function FilaPunto({
    clienteId,
    taxYear,
    punto,
    noAplica,
}: {
    clienteId: number;
    taxYear: number;
    punto: PuntoRevision;
    noAplica: Revision['no_aplica'];
}) {
    const { t } = useTranslation();
    const fecha = useFecha();
    const id = useId();
    const form = useForm({
        tax_year: taxYear,
        confirmado: punto.cumplido,
        nota: punto.nota ?? '',
    });
    const errores = form.errors as Record<string, string>;
    const error = errores.accion ?? errores.confirmado ?? errores.nota;

    const guardar = (confirmado: boolean) => {
        form.transform((d) => ({ ...d, confirmado }));
        form.setData('confirmado', confirmado);
        form.put(puntoRoute.url({ cliente: clienteId, punto: punto.punto }), {
            preserveScroll: true,
            onError: () => form.setData('confirmado', !confirmado),
        });
    };

    return (
        <li className="space-y-2 py-4">
            <div className="flex flex-wrap items-start justify-between gap-x-4 gap-y-1">
                <div className="flex min-h-11 min-w-0 flex-1 basis-60 items-start gap-3">
                    {punto.editable ? (
                        <>
                            <Checkbox
                                id={`${id}-check`}
                                checked={form.data.confirmado}
                                disabled={form.processing}
                                onCheckedChange={(v) => guardar(v === true)}
                                className="mt-0.5 size-5"
                            />
                            <Label
                                htmlFor={`${id}-check`}
                                className="min-w-0 flex-1 cursor-pointer text-base leading-snug font-medium break-words"
                            >
                                {punto.titulo}
                            </Label>
                        </>
                    ) : (
                        <p className="min-w-0 flex-1 text-base leading-snug font-medium break-words">
                            {punto.titulo}
                        </p>
                    )}
                </div>
                <div className="flex min-h-6 items-center gap-2">
                    {form.processing && <Spinner />}
                    <EstadoPunto punto={punto} />
                </div>
            </div>

            <div className="space-y-1 text-sm text-muted-foreground sm:pl-8">
                <p>{punto.ayuda}</p>
                {punto.detalle && (
                    <p className="font-medium text-foreground">
                        {punto.detalle}
                    </p>
                )}
                {!punto.manual && punto.requerido && (
                    <p>{t('revision.checklist.loVerifica')}</p>
                )}
                {punto.manual && punto.requerido && !punto.editable && (
                    <p>{t('revision.checklist.soloEnRevision')}</p>
                )}
                {punto.confirmado_por && punto.confirmado_en && (
                    <p>
                        {t('revision.checklist.confirmadoPor', {
                            quien: punto.confirmado_por,
                            fecha: fecha(punto.confirmado_en),
                        })}
                    </p>
                )}
                {punto.punto === 'documentos' && noAplica.length > 0 && (
                    <div>
                        <p className="font-medium text-foreground">
                            {t('revision.checklist.marcadosNoAplica')}
                        </p>
                        <ul className="list-disc space-y-0.5 pl-5">
                            {noAplica.map((n) => (
                                <li key={n.id} className="break-words">
                                    {n.nombre}: {n.motivo}
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
                {!punto.editable && punto.nota && (
                    <p>{t('revision.checklist.nota', { nota: punto.nota })}</p>
                )}
            </div>

            {punto.editable && (
                <div className="space-y-1.5 sm:pl-8">
                    <Label htmlFor={`${id}-nota`} className="text-sm">
                        {t('revision.checklist.notaOpcional')}
                    </Label>
                    <Input
                        id={`${id}-nota`}
                        value={form.data.nota}
                        maxLength={1000}
                        disabled={form.processing}
                        onChange={(e) => form.setData('nota', e.target.value)}
                        onBlur={() => {
                            if (
                                form.data.confirmado &&
                                form.data.nota !== (punto.nota ?? '')
                            ) {
                                guardar(true);
                            }
                        }}
                        className="h-11"
                        aria-invalid={error ? true : undefined}
                        aria-describedby={error ? `${id}-error` : undefined}
                    />
                    <InputError
                        id={`${id}-error`}
                        message={error}
                        role="alert"
                    />
                </div>
            )}

            {punto.enlace && (
                <div className="sm:pl-8">
                    <Button
                        asChild
                        variant="outline"
                        size="sm"
                        className="h-11 w-full sm:h-9 sm:w-auto"
                    >
                        <Link href={punto.enlace}>
                            {t(`revision.checklist.ir.${punto.punto}`)}
                        </Link>
                    </Button>
                </div>
            )}
        </li>
    );
}

export function ChecklistRevision({
    clienteId,
    taxYear,
    revision,
}: {
    clienteId: number;
    taxYear: number;
    revision: Revision;
}) {
    return (
        <ul className="divide-y">
            {revision.puntos.map((p) => (
                <FilaPunto
                    key={`${p.punto}-${p.cumplido}-${p.confirmado_en ?? ''}-${p.editable}`}
                    clienteId={clienteId}
                    taxYear={taxYear}
                    punto={p}
                    noAplica={revision.no_aplica}
                />
            ))}
        </ul>
    );
}
