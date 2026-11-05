import { Deferred, Head } from '@inertiajs/react';
import { History } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { DerivationLogsPanel } from '@/components/derivation-logs-panel';
import { DeterminacionFiscalPanel } from '@/components/determinacion-fiscal-panel';
import { FichaSkeleton } from '@/components/ficha/ficha-skeleton';
import { LineaTiempo } from '@/components/ficha/linea-tiempo';
import { SeccionError } from '@/components/ficha/seccion-error';
import { FormasAnteriores } from '@/components/ficha-anterior/ficha-anterior';
import type { AnteriorFicha } from '@/components/ficha-anterior/ficha-anterior';
import { dashboard } from '@/routes';
import { index as clientesIndex } from '@/routes/clientes';
import type { EventoExpediente, FichaEncabezado } from '@/types/expediente';

export default function FichaHistorial({
    ficha,
    eventos,
    anterior,
}: {
    ficha: FichaEncabezado;
    eventos?: EventoExpediente[];
    anterior?: AnteriorFicha | null;
}) {
    const { t } = useTranslation();

    return (
        <>
            <Head
                title={`${ficha.cliente.name} · ${t('ficha.pestanas.historial')}`}
            />
            <div className="space-y-10">
                <section
                    aria-labelledby="historial-eventos"
                    className="space-y-3"
                >
                    <h2
                        id="historial-eventos"
                        className="text-title font-semibold text-foreground"
                    >
                        {t('ficha.historial.eventos')}
                    </h2>
                    <Deferred
                        data="eventos"
                        fallback={<FichaSkeleton />}
                        rescue={<SeccionError only={['eventos']} />}
                    >
                        {eventos && eventos.length > 0 ? (
                            <LineaTiempo eventos={eventos} />
                        ) : (
                            <p className="flex items-center gap-2 text-sm text-muted-foreground">
                                <History
                                    className="size-4 shrink-0"
                                    aria-hidden="true"
                                />
                                {t('ficha.historial.vacio')}
                            </p>
                        )}
                    </Deferred>
                </section>

                <Deferred
                    data="anterior"
                    fallback={<FichaSkeleton />}
                    rescue={<SeccionError only={['anterior']} />}
                >
                    {anterior ? (
                        <section
                            aria-labelledby="historial-anterior"
                            className="space-y-6"
                        >
                            <div className="space-y-1">
                                <h2
                                    id="historial-anterior"
                                    className="text-title font-semibold text-foreground"
                                >
                                    {t('ficha.historial.anterior')}
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    {t('ficha.historial.anteriorAyuda')}
                                </p>
                            </div>
                            <DeterminacionFiscalPanel
                                clienteId={ficha.cliente.id}
                                taxYear={ficha.tax_year}
                                determinaciones={anterior.determinaciones}
                            />
                            <DerivationLogsPanel
                                logs={anterior.derivationLogs}
                            />
                            <FormasAnteriores
                                clienteId={ficha.cliente.id}
                                taxYear={ficha.tax_year}
                                anterior={anterior}
                            />
                        </section>
                    ) : null}
                </Deferred>
            </div>
        </>
    );
}

FichaHistorial.layout = {
    breadcrumbs: [
        { title: 'nav.dashboard', href: dashboard() },
        { title: 'nav.clients', href: clientesIndex() },
    ],
};
