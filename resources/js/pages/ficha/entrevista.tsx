import { Deferred, Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import {
    EntrevistaCliente,
    EntrevistaClienteSkeleton,
} from '@/components/cliente/entrevista-cliente';
import { SeccionError } from '@/components/ficha/seccion-error';
import { dashboard } from '@/routes';
import { index as clientesIndex } from '@/routes/clientes';
import type { VistaEntrevista } from '@/types/entrevista';
import type { FichaEncabezado } from '@/types/expediente';

/** Pestaña Entrevista: las respuestas por pantalla, con corregir, revelar e historial. */
export default function FichaEntrevista({
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
                title={`${t('ficha.pestanas.entrevista')} · ${ficha.cliente.name}`}
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
                <EntrevistaCliente
                    clienteId={ficha.cliente.id}
                    taxYear={ficha.tax_year}
                    entrevista={entrevista ?? null}
                />
            </Deferred>
        </>
    );
}

FichaEntrevista.layout = {
    breadcrumbs: [
        { title: 'nav.dashboard', href: dashboard() },
        { title: 'nav.clients', href: clientesIndex() },
    ],
};
