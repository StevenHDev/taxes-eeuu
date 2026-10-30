import { Head, Link, useForm } from '@inertiajs/react';
import {
    CircleAlert,
    CircleCheck,
    Eye,
    EyeOff,
    FileText,
    Pencil,
} from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import {
    confirmar,
    documentos as rutaDocumentos,
    inicio,
} from '@/routes/portal/entrevista';
import type { TipoRespuesta, ValorEntrevista } from '@/types/entrevista';

type PreguntaResumen = {
    codigo: string;
    persona_id: number | null;
    persona_orden: number | null;
    texto: string;
    tipo: TipoRespuesta;
    opciones: { valor: string; texto: string }[];
    sensible: boolean;
    valor: ValorEntrevista;
    url: string;
};

type Props = {
    taxYear: number;
    estado: 'en_curso' | 'confirmada' | 'reabierta';
    confirmadaEn: string | null;
    pantallas: { numero: number; preguntas: PreguntaResumen[] }[];
    documentos: {
        codigo: string;
        persona_orden: number | null;
        nombre: string;
        archivos: number;
    }[];
};

/** Un valor guardado, en palabras del idioma del cliente. */
function useFormatearValor() {
    const { t, i18n } = useTranslation();

    return (p: PreguntaResumen): string => {
        const v = p.valor;
        const opcion = (valor: string) =>
            p.opciones.find((o) => o.valor === valor)?.texto ?? valor;

        if (v === null || v === '') {
            return t('entrevista.resumen.sinResponder');
        }

        switch (p.tipo) {
            case 'si_no':
                return v === 'si' ? t('entrevista.si') : t('entrevista.no');
            case 'usted_conyuge_no':
                return Array.isArray(v)
                    ? v.map((q) => t(`entrevista.quien.${q}`)).join(' · ')
                    : t('entrevista.no');
            case 'opciones':
                return opcion(String(v));
            case 'casillas':
                return Array.isArray(v) ? v.map(opcion).join(' · ') : String(v);
            case 'fecha':
                return new Intl.DateTimeFormat(i18n.language, {
                    dateStyle: 'long',
                    timeZone: 'UTC',
                }).format(new Date(`${v}T00:00:00Z`));
            default:
                return String(v);
        }
    };
}

function Respuesta({ pregunta }: { pregunta: PreguntaResumen }) {
    const { t } = useTranslation();
    const formatear = useFormatearValor();
    const [visible, setVisible] = useState(false);
    const respondida = pregunta.valor !== null && pregunta.valor !== '';
    const oculta = pregunta.sensible && respondida && !visible;

    return (
        <div className="flex flex-col gap-1 py-3 sm:flex-row sm:items-start sm:gap-4">
            <dt className="text-sm text-muted-foreground sm:w-1/2">
                {pregunta.persona_orden !== null
                    ? `${t('entrevista.personas.nombre', { orden: pregunta.persona_orden })} · `
                    : null}
                {pregunta.texto}
            </dt>
            <dd className="flex flex-1 items-start justify-between gap-2">
                <span
                    className={
                        respondida
                            ? 'text-sm font-medium break-words text-foreground'
                            : 'flex items-center gap-1 text-sm font-medium text-amber-700 dark:text-amber-400'
                    }
                >
                    {respondida ? null : (
                        <CircleAlert className="size-4" aria-hidden="true" />
                    )}
                    {oculta ? '••••••' : formatear(pregunta)}
                </span>
                <span className="flex shrink-0 gap-1">
                    {pregunta.sensible && respondida ? (
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            aria-label={`${visible ? t('entrevista.resumen.ocultar') : t('entrevista.resumen.mostrar')}: ${pregunta.texto}`}
                            onClick={() => setVisible((v) => !v)}
                        >
                            {visible ? (
                                <EyeOff aria-hidden="true" />
                            ) : (
                                <Eye aria-hidden="true" />
                            )}
                        </Button>
                    ) : null}
                    <Button variant="ghost" size="sm" asChild>
                        <Link
                            href={pregunta.url}
                            aria-label={`${t('entrevista.resumen.cambiar')}: ${pregunta.texto}`}
                        >
                            <Pencil aria-hidden="true" />
                            {respondida
                                ? t('entrevista.resumen.cambiar')
                                : t('entrevista.resumen.responder')}
                        </Link>
                    </Button>
                </span>
            </dd>
        </div>
    );
}

/**
 * Revisión y confirmación de la entrevista (F2.7): todo lo respondido por
 * pantalla con "Cambiar", lo que falta arriba y una sola acción principal.
 * Los documentos pendientes no impiden confirmar.
 */
export default function ResumenEntrevista({
    taxYear,
    estado,
    confirmadaEn,
    pantallas,
    documentos,
}: Props) {
    const { t, i18n } = useTranslation();
    const form = useForm({});

    // Lo que falta, agrupado por pantalla: con muchas pendientes, una
    // línea por pantalla en lugar de repetir toda la lista de abajo.
    const pendientesPorPantalla = pantallas
        .map((p) => ({
            numero: p.numero,
            preguntas: p.preguntas.filter(
                (q) => q.valor === null || q.valor === '',
            ),
        }))
        .filter((p) => p.preguntas.length > 0);
    const pendientes = pantallas.flatMap((p) =>
        p.preguntas.filter((q) => q.valor === null || q.valor === ''),
    );
    const docsFaltan = documentos.filter((d) => d.archivos === 0);
    const confirmada = estado === 'confirmada';
    const fecha = confirmadaEn
        ? new Intl.DateTimeFormat(i18n.language, {
              day: 'numeric',
              month: 'long',
          }).format(new Date(confirmadaEn))
        : null;

    return (
        <>
            <Head title={t('entrevista.resumen.titulo')} />

            <div className="mx-auto w-full max-w-2xl space-y-6 px-4 pt-4 sm:px-6 sm:pt-6">
                <header className="space-y-1">
                    <h1 className="font-display text-display text-foreground">
                        {t('entrevista.resumen.titulo')}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {t('entrevista.resumen.descripcion', { anio: taxYear })}
                    </p>
                </header>

                {confirmada ? (
                    <div
                        role="status"
                        className="flex items-start gap-3 rounded-lg border border-emerald-600/30 bg-emerald-600/10 p-4 text-sm"
                    >
                        <CircleCheck
                            className="mt-0.5 size-5 shrink-0 text-emerald-700 dark:text-emerald-400"
                            aria-hidden="true"
                        />
                        <p>{t('entrevista.resumen.confirmada', { fecha })}</p>
                    </div>
                ) : null}

                {estado === 'reabierta' ? (
                    <div
                        role="note"
                        className="flex items-start gap-3 rounded-lg border border-amber-500/40 bg-amber-500/10 p-4 text-sm"
                    >
                        <CircleAlert
                            className="mt-0.5 size-5 shrink-0 text-amber-600 dark:text-amber-400"
                            aria-hidden="true"
                        />
                        <p>{t('entrevista.resumen.reabierta')}</p>
                    </div>
                ) : null}

                {pendientes.length > 0 ? (
                    <section
                        aria-labelledby="pendientes"
                        className="space-y-2 rounded-lg border border-amber-500/40 p-4"
                    >
                        <h2
                            id="pendientes"
                            className="flex items-center gap-2 text-sm font-medium"
                        >
                            <CircleAlert
                                className="size-4 text-amber-600 dark:text-amber-400"
                                aria-hidden="true"
                            />
                            {t('entrevista.resumen.faltan', {
                                count: pendientes.length,
                            })}
                        </h2>
                        <ul className="space-y-1 text-sm">
                            {pendientesPorPantalla.map(
                                ({ numero, preguntas }) => (
                                    <li key={numero}>
                                        <Link
                                            href={preguntas[0].url}
                                            className="underline underline-offset-4 hover:text-foreground"
                                        >
                                            {t(
                                                'entrevista.resumen.faltanEnPantalla',
                                                {
                                                    pantalla: t(
                                                        `entrevista.pantallas.${numero}`,
                                                        { anio: taxYear },
                                                    ),
                                                    count: preguntas.length,
                                                },
                                            )}
                                        </Link>
                                    </li>
                                ),
                            )}
                        </ul>
                    </section>
                ) : null}

                {pantallas.map((p) => (
                    <section
                        key={p.numero}
                        aria-labelledby={`pantalla-${p.numero}`}
                        className="rounded-lg border border-border bg-card px-4"
                    >
                        <h2
                            id={`pantalla-${p.numero}`}
                            className="border-b border-border py-3 text-base font-medium"
                        >
                            {t(`entrevista.pantallas.${p.numero}`, {
                                anio: taxYear,
                            })}
                        </h2>
                        <dl className="divide-y divide-border">
                            {p.preguntas.map((q) => (
                                <Respuesta
                                    key={`${q.codigo}-${q.persona_id ?? ''}`}
                                    pregunta={q}
                                />
                            ))}
                        </dl>
                    </section>
                ))}

                <section
                    aria-labelledby="documentos-resumen"
                    className="rounded-lg border border-border bg-card px-4"
                >
                    <div className="flex items-center justify-between gap-2 border-b border-border py-3">
                        <h2
                            id="documentos-resumen"
                            className="text-base font-medium"
                        >
                            {t('entrevista.documentos.titulo')}
                        </h2>
                        <Button variant="ghost" size="sm" asChild>
                            <Link href={rutaDocumentos()}>
                                <FileText aria-hidden="true" />
                                {t('entrevista.resumen.verDocumentos')}
                            </Link>
                        </Button>
                    </div>
                    <ul className="divide-y divide-border">
                        {documentos.map((d) => (
                            <li
                                key={`${d.codigo}-${d.persona_orden ?? ''}`}
                                className="flex items-center justify-between gap-2 py-3 text-sm"
                            >
                                <span className="min-w-0">
                                    {d.nombre}
                                    {d.persona_orden !== null
                                        ? ` · ${t('entrevista.personas.nombre', { orden: d.persona_orden })}`
                                        : ''}
                                </span>
                                <Badge
                                    variant={
                                        d.archivos > 0 ? 'secondary' : 'outline'
                                    }
                                    className="shrink-0"
                                >
                                    {d.archivos > 0 ? (
                                        <CircleCheck aria-hidden="true" />
                                    ) : (
                                        <CircleAlert aria-hidden="true" />
                                    )}
                                    {d.archivos > 0
                                        ? t('entrevista.documentos.recibido', {
                                              count: d.archivos,
                                          })
                                        : t('entrevista.documentos.falta')}
                                </Badge>
                            </li>
                        ))}
                    </ul>
                    {docsFaltan.length > 0 ? (
                        <p className="border-t border-border py-3 text-xs text-muted-foreground">
                            {t('entrevista.resumen.documentosDespues')}
                        </p>
                    ) : null}
                </section>

                <div className="sticky bottom-0 z-10 -mx-4 border-t border-border bg-background/95 px-4 py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] backdrop-blur supports-[backdrop-filter]:bg-background/80 sm:mx-0 sm:px-0">
                    <div className="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <Button variant="ghost" asChild>
                            <Link href={inicio()}>
                                {t('entrevista.resumen.volver')}
                            </Link>
                        </Button>
                        {confirmada ? null : pendientes.length > 0 ? (
                            <Button asChild className="w-full sm:w-auto">
                                <Link href={pendientes[0].url}>
                                    {t('entrevista.resumen.completar')}
                                </Link>
                            </Button>
                        ) : (
                            <div className="flex flex-col gap-1 sm:items-end">
                                <Button
                                    type="button"
                                    className="w-full sm:w-auto"
                                    disabled={form.processing}
                                    onClick={() =>
                                        form.post(confirmar().url, {
                                            preserveScroll: true,
                                        })
                                    }
                                >
                                    {form.processing ? (
                                        <Spinner aria-hidden="true" />
                                    ) : (
                                        <CircleCheck aria-hidden="true" />
                                    )}
                                    {t('entrevista.resumen.confirmar')}
                                </Button>
                                <InputError
                                    message={
                                        (form.errors as Record<string, string>)
                                            .confirmar
                                    }
                                />
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}
