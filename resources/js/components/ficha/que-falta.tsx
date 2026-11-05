import { Link } from '@inertiajs/react';
import { ChevronRight, CircleCheck, CircleX, PartyPopper } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { useFormatoFecha } from '@/components/ficha/linea-tiempo';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import type { Diagnostico, Turno } from '@/types/expediente';

export type AccionPrincipal = { clave: string; url: string };

type Props = {
    diagnostico: Diagnostico;
    consentimiento: {
        version: string | number;
        provisional: boolean;
        aceptado_en: string;
    } | null;
    entrevistaConfirmadaEn: string | null;
    accionPrincipal: AccionPrincipal | null;
    turno: Turno | null;
    urlDocumentos: string | null;
    urlExcepciones: string | null;
};

function Fila({ ok, children }: { ok: boolean; children: React.ReactNode }) {
    const { t } = useTranslation();
    const Icono: LucideIcon = ok ? CircleCheck : CircleX;

    return (
        <li className="flex min-h-11 items-start gap-3 py-2">
            <Icono
                className={
                    ok
                        ? 'mt-0.5 size-5 shrink-0 text-emerald-600 dark:text-emerald-400'
                        : 'mt-0.5 size-5 shrink-0 text-destructive'
                }
                aria-hidden="true"
            />
            <div className="min-w-0 flex-1 space-y-1.5 text-sm">
                <span className="sr-only">
                    {ok
                        ? t('ficha.resumen.cumplido')
                        : t('ficha.resumen.pendiente')}
                    :{' '}
                </span>
                {children}
            </div>
        </li>
    );
}

/**
 * La tarjeta "Qué falta para Listo para preparar": cada condición del
 * diagnóstico como fila con ícono y palabras, y la acción principal.
 */
export function QueFalta({
    diagnostico,
    consentimiento,
    entrevistaConfirmadaEn,
    accionPrincipal,
    turno,
    urlDocumentos,
    urlExcepciones,
}: Props) {
    const { t } = useTranslation();
    const fecha = useFormatoFecha();
    const { r02 } = diagnostico;

    const listo =
        diagnostico.consentimiento &&
        diagnostico.entrevista_confirmada &&
        (!r02.activa || r02.cumple) &&
        diagnostico.excepciones_que_frenan === 0 &&
        diagnostico.consultas === 0;

    const nombreDoc = (d: Diagnostico['r02']['faltan'][number]) => {
        const numero = d.numero > 1 ? ` #${d.numero}` : '';
        const estado = t(`ficha.resumen.estadoDoc.${d.estado}`, {
            defaultValue: d.estado,
        });

        return `${d.nombre}${numero} (${estado})`;
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-title">
                    {t('ficha.resumen.queFalta')}
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                <ul className="divide-y">
                    <Fila ok={diagnostico.consentimiento}>
                        {diagnostico.consentimiento
                            ? consentimiento
                                ? t('ficha.resumen.consentimientoOk', {
                                      version: consentimiento.version,
                                      fecha: fecha(consentimiento.aceptado_en),
                                  })
                                : t('ficha.resumen.consentimientoOkSinDatos')
                            : t('ficha.resumen.consentimientoFalta')}
                    </Fila>

                    <Fila ok={diagnostico.entrevista_confirmada}>
                        {diagnostico.entrevista_confirmada
                            ? entrevistaConfirmadaEn
                                ? t('ficha.resumen.entrevistaOk', {
                                      fecha: fecha(entrevistaConfirmadaEn),
                                  })
                                : t('ficha.resumen.entrevistaOkSinFecha')
                            : t('ficha.resumen.entrevistaFalta')}
                    </Fila>

                    <Fila ok={!r02.activa || r02.cumple}>
                        {!r02.activa ? (
                            <p>{t('ficha.resumen.r02Inactiva')}</p>
                        ) : r02.cumple ? (
                            <p>{t('ficha.resumen.r02Ok')}</p>
                        ) : (
                            <>
                                <p>
                                    {t('ficha.resumen.r02Faltan', {
                                        count: r02.faltan.length,
                                    })}
                                </p>
                                <ul className="list-disc space-y-0.5 pl-5 text-muted-foreground">
                                    {r02.faltan.map((d) => (
                                        <li key={d.id}>{nombreDoc(d)}</li>
                                    ))}
                                </ul>
                                {urlDocumentos ? (
                                    <Link
                                        href={urlDocumentos}
                                        className="inline-flex min-h-11 items-center text-primary underline-offset-4 hover:underline focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                    >
                                        {t('ficha.resumen.verDocumentos')}
                                    </Link>
                                ) : null}
                            </>
                        )}
                        {r02.no_aplica.length > 0 ? (
                            <Collapsible>
                                <CollapsibleTrigger className="group flex min-h-11 items-center gap-1.5 rounded-md text-muted-foreground hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none">
                                    <ChevronRight
                                        className="size-4 transition-transform group-data-[state=open]:rotate-90"
                                        aria-hidden="true"
                                    />
                                    {t('ficha.resumen.noAplica', {
                                        count: r02.no_aplica.length,
                                    })}
                                </CollapsibleTrigger>
                                <CollapsibleContent>
                                    <ul className="space-y-1.5 pl-5 text-muted-foreground">
                                        {r02.no_aplica.map((d) => (
                                            <li key={d.id}>
                                                <span className="font-medium text-foreground">
                                                    {d.nombre}
                                                </span>
                                                {d.motivo
                                                    ? ` — ${d.motivo}`
                                                    : ''}
                                            </li>
                                        ))}
                                    </ul>
                                </CollapsibleContent>
                            </Collapsible>
                        ) : null}
                    </Fila>

                    <Fila ok={diagnostico.excepciones_que_frenan === 0}>
                        <p>
                            {diagnostico.excepciones_que_frenan === 0
                                ? t('ficha.resumen.excepcionesOk')
                                : t('ficha.resumen.excepcionesFrenan', {
                                      count: diagnostico.excepciones_que_frenan,
                                  })}
                            {diagnostico.informativas > 0
                                ? ` · ${t('ficha.resumen.informativas', { count: diagnostico.informativas })}`
                                : ''}
                        </p>
                        {urlExcepciones &&
                        (diagnostico.excepciones_que_frenan > 0 ||
                            diagnostico.informativas > 0) ? (
                            <Link
                                href={urlExcepciones}
                                className="inline-flex min-h-11 items-center text-primary underline-offset-4 hover:underline focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                            >
                                {t('ficha.resumen.verExcepciones')}
                            </Link>
                        ) : null}
                    </Fila>

                    <Fila ok={diagnostico.consultas === 0}>
                        {diagnostico.consultas === 0
                            ? t('ficha.resumen.consultasOk')
                            : t('ficha.resumen.consultasAbiertas', {
                                  count: diagnostico.consultas,
                              })}
                    </Fila>
                </ul>

                {listo ? (
                    <Alert>
                        <PartyPopper aria-hidden="true" />
                        <AlertTitle>
                            {t('ficha.resumen.listoTitulo')}
                        </AlertTitle>
                        <AlertDescription>
                            {t('ficha.resumen.listoTexto')}
                        </AlertDescription>
                    </Alert>
                ) : null}

                {accionPrincipal ? (
                    <Button asChild className="min-h-11 w-full sm:w-auto">
                        <Link href={accionPrincipal.url}>
                            {t(`ficha.resumen.accion.${accionPrincipal.clave}`)}
                        </Link>
                    </Button>
                ) : turno === 'cliente' ? (
                    <p className="text-sm text-muted-foreground">
                        {diagnostico.items_cliente > 0
                            ? t('ficha.resumen.leTocaAlClienteN', {
                                  count: diagnostico.items_cliente,
                              })
                            : t('ficha.resumen.leTocaAlCliente')}
                    </p>
                ) : null}
            </CardContent>
        </Card>
    );
}
