import { Deferred, Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { FichaSkeleton } from '@/components/ficha/ficha-skeleton';
import { LineaTiempo, useFormatoFecha } from '@/components/ficha/linea-tiempo';
import { QueFalta } from '@/components/ficha/que-falta';
import type { AccionPrincipal } from '@/components/ficha/que-falta';
import { SeccionError } from '@/components/ficha/seccion-error';
import { dashboard } from '@/routes';
import { index as clientesIndex } from '@/routes/clientes';
import type {
    ClavePestana,
    Diagnostico,
    EventoExpediente,
    FichaEncabezado,
} from '@/types/expediente';

type ResumenExpediente = {
    diagnostico: Diagnostico;
    entrevista: {
        estado: string;
        iniciada_en: string;
        confirmada_en: string | null;
        canal_inicio: string;
    } | null;
    consentimiento: {
        version: string | number;
        provisional: boolean;
        aceptado_en: string;
    } | null;
    eventos: EventoExpediente[];
    accion_principal: AccionPrincipal | null;
};

function ContenidoResumen({
    ficha,
    resumen,
}: {
    ficha: FichaEncabezado;
    resumen: ResumenExpediente;
}) {
    const { t } = useTranslation();
    const fecha = useFormatoFecha();
    const { entrevista, consentimiento } = resumen;

    const url = (clave: ClavePestana) =>
        ficha.pestanas.find((p) => p.clave === clave)?.url ?? null;

    // Datos de la entrevista y del consentimiento, en una línea.
    const partes = [
        entrevista
            ? t('ficha.resumen.entrevistaIniciada', {
                  fecha: fecha(entrevista.iniciada_en),
                  canal: t(`ficha.resumen.canal.${entrevista.canal_inicio}`, {
                      defaultValue: entrevista.canal_inicio,
                  }),
              })
            : t('ficha.resumen.sinEntrevista'),
        consentimiento
            ? t(
                  consentimiento.provisional
                      ? 'ficha.resumen.consentimientoLineaProvisional'
                      : 'ficha.resumen.consentimientoLinea',
                  { version: consentimiento.version },
              )
            : t('ficha.resumen.sinConsentimiento'),
    ];

    return (
        <div className="space-y-6">
            <QueFalta
                diagnostico={resumen.diagnostico}
                consentimiento={consentimiento}
                entrevistaConfirmadaEn={entrevista?.confirmada_en ?? null}
                accionPrincipal={resumen.accion_principal}
                turno={ficha.expediente.turno}
                urlDocumentos={url('documentos')}
                urlExcepciones={url('excepciones')}
            />

            <p className="text-sm text-muted-foreground">
                {partes.join(' · ')}
            </p>

            <section aria-labelledby="ficha-linea-tiempo" className="space-y-3">
                <h2
                    id="ficha-linea-tiempo"
                    className="text-title font-semibold text-foreground"
                >
                    {t('ficha.resumen.lineaTiempo')}
                </h2>
                {resumen.eventos.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t('ficha.historial.vacio')}
                    </p>
                ) : (
                    <LineaTiempo eventos={resumen.eventos} />
                )}
            </section>
        </div>
    );
}

export default function FichaResumen({
    ficha,
    resumen,
}: {
    ficha: FichaEncabezado;
    resumen?: ResumenExpediente;
}) {
    const { t } = useTranslation();

    return (
        <>
            <Head
                title={`${ficha.cliente.name} · ${t('ficha.pestanas.resumen')}`}
            />
            <Deferred
                data="resumen"
                fallback={<FichaSkeleton />}
                rescue={
                    <SeccionError
                        only={['resumen']}
                        detalle={t('ficha.resumen.errorDetalle')}
                    />
                }
            >
                {resumen ? (
                    <ContenidoResumen ficha={ficha} resumen={resumen} />
                ) : null}
            </Deferred>
        </>
    );
}

FichaResumen.layout = {
    breadcrumbs: [
        { title: 'nav.dashboard', href: dashboard() },
        { title: 'nav.clients', href: clientesIndex() },
    ],
};
