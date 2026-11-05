import { Link } from '@inertiajs/react';
import { CheckCircle2, CircleDashed, EyeOff, ExternalLink } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { formatearFechaHora } from '@/components/debida-diligencia/formato-fecha';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type {
    DocumentoDebida,
    PreguntaDebida,
    SeccionDebida,
} from '@/types/debida-diligencia';

type Props = { seccion: SeccionDebida; urlEntrevista: string };

function Pregunta({
    pregunta,
    urlEntrevista,
}: {
    pregunta: PreguntaDebida;
    urlEntrevista: string;
}) {
    const { t, i18n } = useTranslation();
    const persona = pregunta.persona
        ? (pregunta.persona.etiqueta ??
          t('debidaDiligencia.persona', { orden: pregunta.persona.orden }))
        : null;

    let respuesta: React.ReactNode;

    if (!pregunta.respondida) {
        respuesta = (
            <span className="inline-flex items-center gap-1.5 text-muted-foreground">
                <CircleDashed className="size-4" aria-hidden="true" />
                {t('debidaDiligencia.sinRespuesta')}
            </span>
        );
    } else if (pregunta.sensible) {
        respuesta = (
            <span className="inline-flex flex-wrap items-center gap-x-2 gap-y-1">
                <span className="inline-flex items-center gap-1.5 text-muted-foreground">
                    <EyeOff className="size-4" aria-hidden="true" />
                    {t('debidaDiligencia.sensible')}
                </span>
                <Link
                    href={urlEntrevista}
                    className="inline-flex min-h-11 items-center text-sm underline underline-offset-4 print:hidden"
                >
                    {t('debidaDiligencia.verEnEntrevista')}
                </Link>
            </span>
        );
    } else {
        respuesta = (
            <span className="font-medium">{pregunta.valor_texto ?? '—'}</span>
        );
    }

    const detalle = pregunta.respondida
        ? [
              pregunta.canal
                  ? t(`debidaDiligencia.canal.${pregunta.canal}`)
                  : null,
              pregunta.respondida_en
                  ? formatearFechaHora(pregunta.respondida_en, i18n.language)
                  : null,
              pregunta.actor
                  ? t('debidaDiligencia.porPreparador', {
                        nombre: pregunta.actor,
                    })
                  : t('debidaDiligencia.porCliente'),
              pregunta.cambios > 0
                  ? t('debidaDiligencia.cambios', { count: pregunta.cambios })
                  : null,
          ].filter((x): x is string => x !== null)
        : [];

    return (
        <div className="break-inside-avoid space-y-1 py-3">
            <dt className="text-sm">
                <span className="font-mono text-xs text-muted-foreground">
                    {pregunta.codigo}
                </span>{' '}
                {pregunta.texto}
                {persona && (
                    <span className="text-muted-foreground"> ({persona})</span>
                )}
            </dt>
            <dd className="text-sm">{respuesta}</dd>
            {detalle.length > 0 && (
                <dd className="text-xs text-muted-foreground">
                    {detalle.join(' · ')}
                </dd>
            )}
        </div>
    );
}

function Documento({ doc }: { doc: DocumentoDebida }) {
    const { t, i18n } = useTranslation();
    const validado = doc.estado === 'validado';

    return (
        <li className="flex break-inside-avoid flex-col gap-1 py-2 sm:flex-row sm:items-center sm:justify-between">
            <span className="text-sm">
                {doc.nombre}
                {doc.persona_orden !== null && (
                    <span className="text-muted-foreground">
                        {' '}
                        (
                        {t('debidaDiligencia.persona', {
                            orden: doc.persona_orden,
                        })}
                        )
                    </span>
                )}
            </span>
            <span className="flex flex-wrap items-center gap-x-3 text-sm text-muted-foreground">
                <span className="inline-flex items-center gap-1.5">
                    {validado ? (
                        <CheckCircle2 className="size-4" aria-hidden="true" />
                    ) : (
                        <CircleDashed className="size-4" aria-hidden="true" />
                    )}
                    {validado && doc.validado_por && doc.validado_en
                        ? t('debidaDiligencia.validadoPor', {
                              nombre: doc.validado_por,
                              fecha: formatearFechaHora(
                                  doc.validado_en,
                                  i18n.language,
                              ),
                          })
                        : t(`debidaDiligencia.estadoDocumento.${doc.estado}`)}
                </span>
                <Link
                    href={doc.enlace}
                    className="inline-flex min-h-11 items-center gap-1 underline underline-offset-4 print:hidden"
                >
                    {t('debidaDiligencia.verDocumento')}
                    <ExternalLink className="size-3.5" aria-hidden="true" />
                </Link>
            </span>
        </li>
    );
}

export function ParteCard({ seccion, urlEntrevista }: Props) {
    const { t } = useTranslation();

    return (
        <Card className="print:break-inside-avoid-page print:shadow-none">
            <CardHeader>
                <CardTitle className="text-base">{seccion.titulo}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                {seccion.preguntas.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t('debidaDiligencia.sinPreguntas')}
                    </p>
                ) : (
                    <dl className="divide-y">
                        {seccion.preguntas.map((p) => (
                            <Pregunta
                                key={`${p.codigo}-${p.persona?.orden ?? 0}`}
                                pregunta={p}
                                urlEntrevista={urlEntrevista}
                            />
                        ))}
                    </dl>
                )}
                {seccion.documentos.length > 0 && (
                    <div className="border-t pt-3">
                        <h3 className="text-sm font-semibold">
                            {t('debidaDiligencia.documentos')}
                        </h3>
                        <ul className="divide-y">
                            {seccion.documentos.map((d) => (
                                <Documento key={d.item_id} doc={d} />
                            ))}
                        </ul>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
