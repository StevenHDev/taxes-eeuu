import { Deferred, Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { entrevista as rutaEntrevista } from '@/actions/App/Http/Controllers/FichaClienteController';
import { DebidaContenido } from '@/components/debida-diligencia/contenido';
import { BotonImprimir } from '@/components/ficha/boton-imprimir';
import { FichaSkeleton } from '@/components/ficha/ficha-skeleton';
import { SeccionError } from '@/components/ficha/seccion-error';
import { dashboard } from '@/routes';
import { index as clientesIndex } from '@/routes/clientes';
import type { DebidaDiligenciaVista } from '@/types/debida-diligencia';
import type { FichaEncabezado } from '@/types/expediente';

type Props = { ficha: FichaEncabezado; debida?: DebidaDiligenciaVista };

export default function FichaDebidaDiligencia({ ficha, debida }: Props) {
    const { t } = useTranslation();
    const urlEntrevista = rutaEntrevista(ficha.cliente.id, {
        query: { tax_year: ficha.tax_year },
    }).url;

    return (
        <>
            <Head title={t('debidaDiligencia.titulo')} />
            <div className="space-y-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 className="text-xl font-semibold">
                            {t('debidaDiligencia.titulo')}
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            {t('debidaDiligencia.subtitulo')}
                        </p>
                    </div>
                    <BotonImprimir etiqueta={t('debidaDiligencia.imprimir')} />
                </div>
                <Deferred
                    data="debida"
                    fallback={<FichaSkeleton />}
                    rescue={<SeccionError only={['debida']} />}
                >
                    {debida && (
                        <DebidaContenido
                            debida={debida}
                            clienteId={ficha.cliente.id}
                            taxYear={ficha.tax_year}
                            urlEntrevista={urlEntrevista}
                        />
                    )}
                </Deferred>
            </div>
        </>
    );
}

FichaDebidaDiligencia.layout = {
    breadcrumbs: [
        { title: 'nav.dashboard', href: dashboard() },
        { title: 'nav.clients', href: clientesIndex() },
    ],
};
