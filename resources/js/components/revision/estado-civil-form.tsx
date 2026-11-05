import { useForm } from '@inertiajs/react';
import { useId } from 'react';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
import { RespuestasCliente } from '@/components/revision/respuestas-cliente';
import { useFecha } from '@/components/revision/use-fecha';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { estadoCivil } from '@/routes/clientes/revision';
import type { EstadoCivilDecision } from '@/types/revision';

/** Se reinicia desde la página con `key` cuando cambia la decisión guardada. */
export function EstadoCivilForm({
    clienteId,
    taxYear,
    decision,
    abierta,
}: {
    clienteId: number;
    taxYear: number;
    decision: EstadoCivilDecision;
    abierta: boolean;
}) {
    const { t } = useTranslation();
    const fecha = useFecha();
    const id = useId();
    const form = useForm({
        tax_year: taxYear,
        valor: decision.valor ?? '',
        motivo: decision.motivo ?? '',
    });
    const errorValor = form.errors.valor;
    const errorMotivo = form.errors.motivo;
    const errorAccion = (form.errors as Record<string, string>).accion;

    const guardar = (e: React.FormEvent) => {
        e.preventDefault();
        form.put(estadoCivil.url(clienteId), { preserveScroll: true });
    };

    return (
        <form onSubmit={guardar} noValidate>
            <fieldset
                disabled={!abierta || form.processing}
                className="min-w-0 space-y-4"
            >
                <legend className="mb-3 text-base font-semibold">
                    {t('revision.estadoCivil.titulo')}
                </legend>
                <RespuestasCliente respuestas={decision.respuestas_cliente} />

                <div className="space-y-2">
                    <Label htmlFor={`${id}-valor`}>
                        {t('revision.estadoCivil.decision')}
                    </Label>
                    <Select
                        value={form.data.valor}
                        onValueChange={(v) => form.setData('valor', v)}
                    >
                        <SelectTrigger
                            id={`${id}-valor`}
                            className="h-11 w-full sm:max-w-sm"
                            aria-invalid={errorValor ? true : undefined}
                            aria-describedby={
                                errorValor ? `${id}-valor-error` : undefined
                            }
                        >
                            <SelectValue placeholder={t('revision.elegir')} />
                        </SelectTrigger>
                        <SelectContent>
                            {decision.opciones.map((o) => (
                                <SelectItem key={o.valor} value={o.valor}>
                                    {o.etiqueta}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError id={`${id}-valor-error`} message={errorValor} />
                </div>

                <div className="space-y-2">
                    <Label htmlFor={`${id}-motivo`}>
                        {t('revision.motivo')}
                    </Label>
                    <Textarea
                        id={`${id}-motivo`}
                        value={form.data.motivo}
                        onChange={(e) => form.setData('motivo', e.target.value)}
                        maxLength={1000}
                        aria-invalid={errorMotivo ? true : undefined}
                        aria-describedby={
                            errorMotivo ? `${id}-motivo-error` : undefined
                        }
                    />
                    <InputError
                        id={`${id}-motivo-error`}
                        message={errorMotivo}
                    />
                </div>

                <InputError message={errorAccion} role="alert" />

                {abierta ? (
                    <Button
                        type="submit"
                        className="h-11 w-full sm:w-auto"
                        disabled={form.processing}
                    >
                        {form.processing && <Spinner />}
                        {t('revision.guardar')}
                    </Button>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        {t('revision.cerrada')}
                    </p>
                )}
                {decision.por && decision.en && (
                    <p className="text-sm text-muted-foreground">
                        {t('revision.decididoPor', {
                            quien: decision.por,
                            fecha: fecha(decision.en),
                        })}
                    </p>
                )}
            </fieldset>
        </form>
    );
}
