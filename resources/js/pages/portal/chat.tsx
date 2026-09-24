import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { PortalChatPanel } from '@/components/portal-chat-panel';
import type { PortalMensaje } from '@/types/agente';

/**
 * Chat de autoservicio del cliente en el portal seguro, a pantalla completa —
 * lo que ve mientras todavía no tiene ninguna forma declarada (fase
 * DeterminacionFormas: ver PortalFormularioController::index(), que redirige
 * acá en ese caso). Una vez declaradas, este mismo chat aparece embebido al
 * lado del formulario en vez de acá.
 */
export default function PortalChat({
    mensajes,
}: {
    mensajes: PortalMensaje[];
}) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('portalChat.pageTitle')} />

            {/* h-16 (4rem) es la altura fija de AppSidebarHeader, la barra
                que ya viene arriba de esta página (ver app-sidebar-layout.tsx)
                — sin descontarla acá, este bloque se pasaba de la pantalla y
                forzaba scroll en la página entera en vez de solo en el chat. */}
            <div className="mx-auto flex h-[calc(100svh-4rem)] max-w-2xl flex-col p-4">
                <div className="mb-4 shrink-0">
                    <h1 className="font-display text-display text-foreground">
                        {t('portalChat.pageTitle')}
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {t('portalChat.subtitle')}
                    </p>
                </div>

                <div className="min-h-0 flex-1">
                    <PortalChatPanel mensajes={mensajes} />
                </div>
            </div>
        </>
    );
}
