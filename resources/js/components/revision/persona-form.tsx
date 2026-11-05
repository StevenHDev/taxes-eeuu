import { Link, useForm } from '@inertiajs/react';
import { useId } from 'react';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
import { RespuestasCliente } from '@/components/revision/respuestas-cliente';
import { useFecha } from '@/components/revision/use-fecha';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { persona as personaRoute } from '@/routes/clientes/revision';
import type { Opcion, PersonaRevision } from '@/types/revision';

/** Una tarjeta por persona; la página la reinicia con `key={id + en}`. */
export function PersonaForm({
    clienteId,
    taxYear,
    persona,
    clasificaciones,
    abierta,
    enlaceEntrevista,
}: {
    clienteId: number;
    taxYear: number;
    persona: PersonaRevision;
    clasificaciones: Opcion[];
    abierta: boolean;
    enlaceEntrevista: string | null;
}) {
    const { t } = useTranslation();
    const fecha = useFecha();
    const id = useId();
    const form = useForm({
        tax_year: taxYear,
        clasificacion: persona.clasificacion ?? '',
        motivo: persona.motivo ?? '',
    });
    const errorClasificacion = form.errors.clasificacion;
    const errorMotivo = form.errors.motivo;
    const errorAccion = (form.errors as Record<string, string>).accion;

    const guardar = (e: React.FormEvent) => {
        e.preventDefault();
        form.put(
            personaRoute.url({ cliente: clienteId, persona: persona.id }),
            {
                preserveScroll: true,
            },
        );
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">{persona.etiqueta}</CardTitle>
            </CardHeader>
            <CardContent>
                <form onSubmit={guardar} noValidate>
                    <fieldset
                        disabled={!abierta || form.processing}
                        className="min-w-0 space-y-4"
                    >
                        <legend className="sr-only">
                            {t('revision.personas.clasificacionDe', {
                                persona: persona.etiqueta,
                            })}
                        </legend>
                        <RespuestasCliente respuestas={persona.respuestas} />
                        <p className="text-sm text-muted-foreground">
                            {t('revision.personas.sensibles')}{' '}
                            {enlaceEntrevista && (
                                <Link
                                    href={enlaceEntrevista}
                                    className="font-medium text-foreground underline underline-offset-4"
                                >
                                    {t('revision.personas.verEntrevista')}
                                </Link>
                            )}
                        </p>

                        <div
                            role="radiogroup"
                            aria-label={t('revision.personas.clasificacion')}
                            aria-describedby={
                                errorClasificacion
                                    ? `${id}-clas-error`
                                    : undefined
                            }
                            className="space-y-1"
                        >
                            <p
                                className="text-sm font-medium"
                                aria-hidden="true"
                            >
                                {t('revision.personas.clasificacion')}
                            </p>
                            {clasificaciones.map((c) => (
                                <div
                                    key={c.valor}
                                    className="flex min-h-11 items-center gap-3"
                                >
                                    <input
                                        type="radio"
                                        id={`${id}-${c.valor}`}
                                        name={`${id}-clasificacion`}
                                        value={c.valor}
                                        checked={
                                            form.data.clasificacion === c.valor
                                        }
                                        onChange={() =>
                                            form.setData(
                                                'clasificacion',
                                                c.valor,
                                            )
                                        }
                                        className="size-5 accent-primary focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed"
                                    />
                                    <Label
                                        htmlFor={`${id}-${c.valor}`}
                                        className="min-h-11 flex-1 cursor-pointer"
                                    >
                                        {c.etiqueta}
                                    </Label>
                                </div>
                            ))}
                            <InputError
                                id={`${id}-clas-error`}
                                message={errorClasificacion}
                            />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor={`${id}-motivo`}>
                                {t('revision.motivo')}
                            </Label>
                            <Textarea
                                id={`${id}-motivo`}
                                value={form.data.motivo}
                                onChange={(e) =>
                                    form.setData('motivo', e.target.value)
                                }
                                maxLength={1000}
                                aria-invalid={errorMotivo ? true : undefined}
                                aria-describedby={
                                    errorMotivo
                                        ? `${id}-motivo-error`
                                        : undefined
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
                        {persona.por && persona.en && (
                            <p className="text-sm text-muted-foreground">
                                {t('revision.decididoPor', {
                                    quien: persona.por,
                                    fecha: fecha(persona.en),
                                })}
                            </p>
                        )}
                    </fieldset>
                </form>
            </CardContent>
        </Card>
    );
}
