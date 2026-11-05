import { Deferred, Head } from '@inertiajs/react';
import { TriangleAlert } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Avisos } from '@/components/panel/avisos';
import { ColaTeToca } from '@/components/panel/cola-te-toca';
import { PanelSkeleton } from '@/components/panel/panel-skeleton';
import { TarjetasEstado } from '@/components/panel/tarjetas-estado';
import { Tiempos } from '@/components/panel/tiempos';
import { UltimosClientes } from '@/components/panel/ultimos-clientes';
import { VencenPronto } from '@/components/panel/vencen-pronto';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { dashboard } from '@/routes';
import type { PanelResumen } from '@/types/panel';

function PanelContenido({ panel }: { panel?: PanelResumen | null }) {
    const { t } = useTranslation();

    if (!panel) {
        return (
            <Alert variant="destructive" role="alert">
                <TriangleAlert aria-hidden="true" />
                <AlertDescription>{t('panel.error')}</AlertDescription>
            </Alert>
        );
    }

    return (
        <div className="space-y-6">
            <TarjetasEstado estados={panel.por_estado} />
            <div className="grid gap-4 lg:grid-cols-2">
                <ColaTeToca
                    colas={panel.colas}
                    vencen={panel.vencen_pronto.length}
                />
                <Avisos avisos={panel.avisos} />
                <VencenPronto filas={panel.vencen_pronto} />
                <Tiempos tiempos={panel.tiempos} />
                <UltimosClientes clientes={panel.ultimos_clientes} />
            </div>
        </div>
    );
}

export default function Dashboard({
    panel,
    taxYearActual,
}: {
    panel?: PanelResumen | null;
    taxYearActual: number;
}) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('panel.titulo')} />

            <div className="space-y-6 p-4">
                <div className="flex flex-col gap-1">
                    <h1 className="text-xl font-semibold">
                        {t('panel.tituloAnio', { anio: taxYearActual })}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {t('panel.subtitulo')}
                    </p>
                </div>

                <Deferred data="panel" fallback={<PanelSkeleton />}>
                    <PanelContenido panel={panel} />
                </Deferred>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'nav.dashboard',
            href: dashboard(),
        },
    ],
};
