import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import Heading from '@/components/heading';
import { raw as procesoRaw, show as procesoShow } from '@/routes/proceso';

/**
 * El diagrama (docs/proceso-global-tax.html, generado con el skill
 * archify — ver agentes-subagentes-flujos-motor-decision.md) es un
 * documento autocontenido con su propio head/CSS/JS (temas, pan/zoom,
 * exportación) — va en un <iframe> apuntando a ProcesoController::raw(),
 * nunca inyectado como fragmento en esta página.
 */
export default function ProcesoShow() {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('proceso.title')} />

            <div className="flex h-full flex-col gap-4 px-4 py-6">
                <Heading
                    title={t('proceso.title')}
                    description={t('proceso.description')}
                />

                <iframe
                    src={procesoRaw().url}
                    title={t('proceso.title')}
                    className="min-h-[70vh] w-full flex-1 rounded-md border"
                />
            </div>
        </>
    );
}

ProcesoShow.layout = {
    breadcrumbs: [
        {
            title: 'nav.proceso',
            href: procesoShow,
        },
    ],
};
