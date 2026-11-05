import { Deferred, Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { DocumentosCliente } from '@/components/cliente/documentos-cliente';
import { EntrevistaClienteSkeleton } from '@/components/cliente/entrevista-cliente';
import { SeccionError } from '@/components/ficha/seccion-error';
import { dashboard } from '@/routes';
import { index as clientesIndex } from '@/routes/clientes';
import type { VistaEntrevista } from '@/types/entrevista';
import type { FichaEncabezado } from '@/types/expediente';

/** Pestaña Documentos: resumen, excepciones abiertas y la lista con revisión y "Ver datos". */
export default function FichaDocumentos({
    ficha,
    entrevista,
}: {
    ficha: FichaEncabezado;
    entrevista?: VistaEntrevista | null;
}) {
    const { t } = useTranslation();

    return (
        <>
            <Head
                title={`${t('ficha.pestanas.documentos')} · ${ficha.cliente.name}`}
            />
            <Deferred
                data="entrevista"
                fallback={<EntrevistaClienteSkeleton />}
                rescue={
                    <SeccionError
                        only={['entrevista']}
                        detalle={t('clienteShow.entrevista.error')}
                    />
                }
            >
                <DocumentosCliente
                    clienteId={ficha.cliente.id}
                    entrevista={entrevista ?? null}
                />
            </Deferred>
        </>
    );
}

FichaDocumentos.layout = {
    breadcrumbs: [
        { title: 'nav.dashboard', href: dashboard() },
        { title: 'nav.clients', href: clientesIndex() },
    ],
};
