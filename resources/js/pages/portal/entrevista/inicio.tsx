import { Head, Link } from '@inertiajs/react';
import {
    ChevronRight,
    CircleCheck,
    CircleDashed,
    CircleDot,
    FileText,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    documentos as rutaDocumentos,
    paso as rutaPaso,
    personas as rutaPersonas,
    resumen as rutaResumen,
} from '@/routes/portal/entrevista';
import type { AvancePantalla, EstadoPantalla } from '@/types/entrevista';

const ICONO: Record<EstadoPantalla, typeof CircleCheck> = {
    completa: CircleCheck,
    en_curso: CircleDot,
    sin_empezar: CircleDashed,
};

/**
 * Inicio de la entrevista (F2.5): las 8 pantallas con su estado, sin
 * importar si se respondieron por WhatsApp o aquí, y una sola acción
 * principal para seguir donde quedó.
 */
export default function InicioEntrevista({
    taxYear,
    pantallas,
    continuarEn,
    conPersonas,
    documentos,
    estado,
}: {
    taxYear: number;
    pantallas: AvancePantalla[];
    continuarEn: number | null;
    personas: number;
    conPersonas: boolean;
    documentos: { total: number; faltan: number };
    estado: 'en_curso' | 'confirmada' | 'reabierta';
}) {
    const { t } = useTranslation();
    const empezada = pantallas.some((p) => p.respondidas > 0);
    const enlace = (numero: number) =>
        numero === 4 && conPersonas
            ? rutaPersonas()
            : rutaPaso({ pantalla: numero });

    return (
        <>
            <Head title={t('entrevista.inicio.titulo')} />

            <div className="mx-auto w-full max-w-xl space-y-6 p-4 sm:p-6">
                <header className="space-y-1">
                    <h1 className="font-display text-display text-foreground">
                        {t('entrevista.inicio.titulo')}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {t('entrevista.inicio.descripcion', { anio: taxYear })}
                    </p>
                </header>

                {continuarEn !== null ? (
                    <Button asChild size="lg" className="w-full sm:w-auto">
                        <Link href={enlace(continuarEn)} prefetch>
                            {empezada
                                ? t('entrevista.inicio.seguir')
                                : t('entrevista.inicio.empezar')}
                            <ChevronRight aria-hidden="true" />
                        </Link>
                    </Button>
                ) : (
                    <div className="space-y-3">
                        <p
                            role="status"
                            className="rounded-lg border border-border bg-card p-4 text-sm"
                        >
                            {estado === 'confirmada'
                                ? t('entrevista.estadoEntrevista.confirmada')
                                : t('entrevista.inicio.todoRespondido')}
                        </p>
                        <Button
                            asChild
                            size="lg"
                            variant={
                                estado === 'confirmada' ? 'outline' : 'default'
                            }
                            className="w-full sm:w-auto"
                        >
                            <Link href={rutaResumen()} prefetch>
                                {t('entrevista.resumen.revisar')}
                                <ChevronRight aria-hidden="true" />
                            </Link>
                        </Button>
                    </div>
                )}

                {estado === 'reabierta' ? (
                    <p
                        role="note"
                        className="rounded-lg border border-amber-500/40 bg-amber-500/10 p-3 text-sm"
                    >
                        {t('entrevista.estadoEntrevista.reabierta')}
                    </p>
                ) : null}

                {documentos.total > 0 ? (
                    <Link
                        href={rutaDocumentos()}
                        className="flex min-h-14 items-center gap-3 rounded-lg border border-border bg-card px-4 py-3 hover:bg-accent focus-visible:bg-accent focus-visible:outline-none"
                    >
                        <FileText
                            className="size-5 shrink-0 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <span className="min-w-0 flex-1 text-sm font-medium text-foreground">
                            {t('entrevista.documentos.verTodos')}
                        </span>
                        <Badge
                            variant={
                                documentos.faltan === 0
                                    ? 'secondary'
                                    : 'outline'
                            }
                            className="shrink-0"
                        >
                            {documentos.faltan === 0
                                ? t('entrevista.documentos.resumenCompleto')
                                : t('entrevista.documentos.resumen', {
                                      count: documentos.faltan,
                                      total: documentos.total,
                                  })}
                        </Badge>
                    </Link>
                ) : null}

                <ol className="divide-y divide-border overflow-hidden rounded-lg border border-border bg-card">
                    {pantallas.map((p) => {
                        const Icono = ICONO[p.estado];

                        return (
                            <li key={p.numero}>
                                <Link
                                    href={enlace(p.numero)}
                                    className="flex min-h-14 items-center gap-3 px-4 py-3 hover:bg-accent focus-visible:bg-accent focus-visible:outline-none"
                                >
                                    <span className="w-5 shrink-0 text-sm text-muted-foreground tabular-nums">
                                        {p.numero}
                                    </span>
                                    <span className="min-w-0 flex-1 text-sm font-medium text-foreground">
                                        {t(`entrevista.pantallas.${p.numero}`, {
                                            anio: taxYear,
                                        })}
                                    </span>
                                    <Badge
                                        variant={
                                            p.estado === 'completa'
                                                ? 'secondary'
                                                : 'outline'
                                        }
                                        className="shrink-0"
                                    >
                                        <Icono aria-hidden="true" />
                                        {t(`entrevista.estado.${p.estado}`)}
                                    </Badge>
                                </Link>
                            </li>
                        );
                    })}
                </ol>
            </div>
        </>
    );
}
