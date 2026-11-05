import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { PanelResumen } from '@/types/panel';

export function ColaTeToca({
    colas,
    vencen,
}: {
    colas: PanelResumen['colas'];
    vencen: number;
}) {
    const { t } = useTranslation();

    const filas = [
        {
            clave: 'listos',
            texto: t('panel.teToca.listos'),
            cantidad: colas.listos,
            href: colas.urls.listos,
        },
        {
            clave: 'porValidar',
            texto: t('panel.teToca.porValidar'),
            cantidad: colas.por_validar,
            href: colas.urls.por_validar,
        },
        {
            clave: 'enConsulta',
            texto: t('panel.teToca.enConsulta'),
            cantidad: colas.en_consulta,
            href: colas.urls.en_consulta,
        },
        {
            clave: 'excepciones',
            texto: t('panel.teToca.excepciones'),
            detalle:
                colas.bloqueantes > 0
                    ? t('panel.teToca.bloqueantes', {
                          count: colas.bloqueantes,
                      })
                    : null,
            cantidad: colas.excepciones,
            href: colas.urls.excepciones,
        },
        {
            clave: 'vencen',
            texto: t('panel.teToca.vencen'),
            cantidad: vencen,
            href: '#panel-vencen',
        },
    ];

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-sm">
                    {t('panel.teToca.titulo')}
                </CardTitle>
            </CardHeader>
            <CardContent>
                <ul className="-mx-2 divide-y">
                    {filas.map((f) => (
                        <li key={f.clave}>
                            <Link
                                href={f.href}
                                className="flex min-h-11 items-center gap-3 rounded-lg px-2 py-2 transition-colors hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none active:bg-accent/70"
                            >
                                <span className="flex-1 text-sm">
                                    {f.texto}
                                    {'detalle' in f && f.detalle ? (
                                        <span className="ml-1 text-xs text-destructive">
                                            ({f.detalle})
                                        </span>
                                    ) : null}
                                </span>
                                <span className="text-sm font-semibold tabular-nums">
                                    {f.cantidad}
                                </span>
                                <ArrowRight
                                    aria-hidden="true"
                                    className="size-4 shrink-0 text-muted-foreground"
                                />
                            </Link>
                        </li>
                    ))}
                </ul>
            </CardContent>
        </Card>
    );
}
