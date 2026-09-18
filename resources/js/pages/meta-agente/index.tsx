import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { index as metaAgenteIndex, store as metaAgenteStore } from '@/routes/meta-agente';
import type {
    ConversacionAgente,
    HallazgoMetaAgente,
    ReporteMetaAgente,
    SeveridadHallazgo,
} from '@/types/meta-agente';

const SEVERIDAD_BADGE_VARIANT: Record<
    SeveridadHallazgo,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    alta: 'destructive',
    media: 'default',
    baja: 'secondary',
};

function useFormatDate() {
    const { i18n } = useTranslation();

    return (iso: string) =>
        new Intl.DateTimeFormat(i18n.language, {
            dateStyle: 'medium',
            timeStyle: 'short',
        }).format(new Date(iso));
}

function HallazgoItem({ hallazgo }: { hallazgo: HallazgoMetaAgente }) {
    const { t } = useTranslation();

    return (
        <div className="flex flex-col gap-1 rounded-md border p-3">
            <div className="flex items-center gap-2">
                <Badge variant={SEVERIDAD_BADGE_VARIANT[hallazgo.severidad]}>
                    {t(`metaAgente.severidad.${hallazgo.severidad}`)}
                </Badge>
                <span className="text-xs text-muted-foreground">
                    {hallazgo.categoria}
                </span>
            </div>
            <p className="text-sm text-foreground">{hallazgo.resumen}</p>
            <p className="text-xs text-muted-foreground italic">
                {hallazgo.evidencia}
            </p>
        </div>
    );
}

function ReporteCard({ reporte }: { reporte: ReporteMetaAgente }) {
    const { t } = useTranslation();
    const formatDate = useFormatDate();
    const [abierto, setAbierto] = useState(false);

    return (
        <Collapsible open={abierto} onOpenChange={setAbierto}>
            <div className="rounded-md border">
                <CollapsibleTrigger className="flex w-full flex-col gap-1 p-4 text-left">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <span className="font-medium text-foreground">
                            {reporte.cliente_nombre ?? reporte.telefono}
                        </span>
                        <span className="text-xs text-muted-foreground">
                            {formatDate(reporte.created_at)}
                        </span>
                    </div>
                    <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                        <Badge variant="outline">
                            {t(`metaAgente.origen.${reporte.origen}`)}
                        </Badge>
                        <span>{reporte.modelo}</span>
                        <span>
                            {t('metaAgente.reportes.hallazgos', {
                                count: reporte.hallazgos.length,
                            })}
                        </span>
                    </div>
                </CollapsibleTrigger>

                <CollapsibleContent className="flex flex-col gap-2 border-t p-4">
                    {reporte.hallazgos.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            {t('metaAgente.reportes.sinHallazgos')}
                        </p>
                    ) : (
                        reporte.hallazgos.map((h, indice) => (
                            <HallazgoItem key={indice} hallazgo={h} />
                        ))
                    )}
                </CollapsibleContent>
            </div>
        </Collapsible>
    );
}

export default function MetaAgenteIndex({
    conversaciones,
    reportes,
}: {
    conversaciones: ConversacionAgente[];
    reportes: ReporteMetaAgente[];
}) {
    const { t } = useTranslation();
    const formatDate = useFormatDate();
    const [seleccionadas, setSeleccionadas] = useState<Set<string>>(new Set());
    const [enviando, setEnviando] = useState(false);

    function alternar(telefono: string) {
        setSeleccionadas((actual) => {
            const siguiente = new Set(actual);

            if (siguiente.has(telefono)) {
                siguiente.delete(telefono);
            } else {
                siguiente.add(telefono);
            }

            return siguiente;
        });
    }

    function analizarSeleccionadas() {
        setEnviando(true);
        router.post(
            metaAgenteStore().url,
            { telefonos: Array.from(seleccionadas) },
            {
                preserveScroll: true,
                onFinish: () => {
                    setEnviando(false);
                    setSeleccionadas(new Set());
                },
            },
        );
    }

    return (
        <>
            <Head title={t('metaAgente.title')} />

            <div className="flex flex-col gap-6 px-4 py-6">
                <Heading
                    title={t('metaAgente.title')}
                    description={t('metaAgente.description')}
                />

                <Card>
                    <CardHeader>
                        <CardTitle>{t('metaAgente.conversaciones.title')}</CardTitle>
                        <CardDescription>
                            {t('metaAgente.conversaciones.description')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-3">
                        <div className="flex items-center justify-between">
                            <span className="text-sm text-muted-foreground">
                                {t('metaAgente.conversaciones.seleccionadas', {
                                    count: seleccionadas.size,
                                })}
                            </span>
                            <Button
                                disabled={seleccionadas.size === 0 || enviando}
                                onClick={analizarSeleccionadas}
                            >
                                {t('metaAgente.conversaciones.analizar')}
                            </Button>
                        </div>

                        <div className="overflow-x-auto rounded-md border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-10" />
                                        <TableHead>
                                            {t(
                                                'metaAgente.conversaciones.columns.telefono',
                                            )}
                                        </TableHead>
                                        <TableHead>
                                            {t(
                                                'metaAgente.conversaciones.columns.cliente',
                                            )}
                                        </TableHead>
                                        <TableHead>
                                            {t(
                                                'metaAgente.conversaciones.columns.ultimoMensaje',
                                            )}
                                        </TableHead>
                                        <TableHead className="text-right">
                                            {t(
                                                'metaAgente.conversaciones.columns.total',
                                            )}
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {conversaciones.length === 0 && (
                                        <TableRow>
                                            <TableCell
                                                colSpan={5}
                                                className="text-center text-muted-foreground"
                                            >
                                                {t('metaAgente.conversaciones.empty')}
                                            </TableCell>
                                        </TableRow>
                                    )}
                                    {conversaciones.map((c) => (
                                        <TableRow key={c.telefono}>
                                            <TableCell>
                                                <Checkbox
                                                    checked={seleccionadas.has(
                                                        c.telefono,
                                                    )}
                                                    onCheckedChange={() =>
                                                        alternar(c.telefono)
                                                    }
                                                    aria-label={c.telefono}
                                                />
                                            </TableCell>
                                            <TableCell className="whitespace-nowrap">
                                                {c.telefono}
                                            </TableCell>
                                            <TableCell>
                                                {c.cliente_nombre ?? (
                                                    <span className="text-muted-foreground">
                                                        {t(
                                                            'metaAgente.conversaciones.sinCuenta',
                                                        )}
                                                    </span>
                                                )}
                                            </TableCell>
                                            <TableCell className="whitespace-nowrap">
                                                {formatDate(c.ultimo_mensaje)}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {c.total_mensajes}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('metaAgente.reportes.title')}</CardTitle>
                        <CardDescription>
                            {t('metaAgente.reportes.description')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-3">
                        {reportes.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                {t('metaAgente.reportes.empty')}
                            </p>
                        ) : (
                            reportes.map((r) => (
                                <ReporteCard key={r.id} reporte={r} />
                            ))
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

MetaAgenteIndex.layout = {
    breadcrumbs: [
        {
            title: 'nav.metaAgente',
            href: metaAgenteIndex,
        },
    ],
};
