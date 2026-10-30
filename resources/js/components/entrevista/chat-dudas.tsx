import { Deferred } from '@inertiajs/react';
import { MessageCircleQuestion } from 'lucide-react';
import { lazy, Suspense } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { Skeleton } from '@/components/ui/skeleton';
import type { PortalMensaje } from '@/types/agente';

// Solo se descarga cuando el cliente abre el chat (en el celular) o en
// pantallas grandes, donde va al costado.
const PortalChatPanel = lazy(() =>
    import('@/components/portal-chat-panel').then((m) => ({
        default: m.PortalChatPanel,
    })),
);

const Cargando = () => (
    <div className="space-y-3" aria-hidden="true">
        <Skeleton className="h-10 w-3/4" />
        <Skeleton className="ml-auto h-10 w-2/3" />
        <Skeleton className="h-10 w-1/2" />
    </div>
);

function Panel({ mensajes }: { mensajes: PortalMensaje[] | undefined }) {
    return (
        <Deferred data="mensajes" fallback={<Cargando />}>
            <Suspense fallback={<Cargando />}>
                <PortalChatPanel
                    mensajes={mensajes ?? []}
                    conArchivos={false}
                />
            </Suspense>
        </Deferred>
    );
}

/** Botón que abre el chat de dudas en un panel lateral (celular y tablet). */
export function ChatDudasBoton({
    mensajes,
}: {
    mensajes: PortalMensaje[] | undefined;
}) {
    const { t } = useTranslation();

    return (
        <Sheet>
            <SheetTrigger asChild>
                <Button variant="outline" size="sm" className="lg:hidden">
                    <MessageCircleQuestion aria-hidden="true" />
                    {t('entrevista.chat.abrir')}
                </Button>
            </SheetTrigger>
            <SheetContent className="flex w-full flex-col sm:max-w-md">
                <SheetHeader>
                    <SheetTitle>{t('entrevista.chat.titulo')}</SheetTitle>
                    <SheetDescription>
                        {t('entrevista.chat.descripcion')}
                    </SheetDescription>
                </SheetHeader>
                <div className="min-h-0 flex-1 px-4 pb-4">
                    <Panel mensajes={mensajes} />
                </div>
            </SheetContent>
        </Sheet>
    );
}

/** El mismo chat al costado, desde pantallas grandes. */
export function ChatDudasLateral({
    mensajes,
}: {
    mensajes: PortalMensaje[] | undefined;
}) {
    const { t } = useTranslation();

    return (
        <aside
            aria-label={t('entrevista.chat.titulo')}
            className="sticky top-4 hidden h-[calc(100svh-6rem)] flex-col gap-2 lg:flex"
        >
            <h2 className="text-sm font-medium text-foreground">
                {t('entrevista.chat.titulo')}
            </h2>
            <div className="min-h-0 flex-1">
                <Panel mensajes={mensajes} />
            </div>
        </aside>
    );
}
