import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import type { PortalMensaje } from '@/types/agente';
import { ChatDudasBoton, ChatDudasLateral } from './chat-dudas';

const PANTALLAS = 8;

/**
 * Marco de cada paso de la entrevista (F2.5): arriba el número de pantalla
 * y la barra de avance; el contenido en una columna; abajo, fija, la
 * acción principal con "Atrás" a un lado. El chat de dudas va al costado
 * desde `lg` y en un panel en el celular.
 */
export function MarcoEntrevista({
    pantalla,
    titulo,
    descripcion,
    anterior,
    mensajes,
    accion,
    children,
}: {
    pantalla: number;
    titulo: string;
    descripcion?: string;
    anterior: string;
    mensajes: PortalMensaje[] | undefined;
    /** El botón principal (y su motivo si está deshabilitado). */
    accion: ReactNode;
    children: ReactNode;
}) {
    const { t } = useTranslation();
    const etiqueta = t('entrevista.pantallaDe', {
        actual: pantalla,
        total: PANTALLAS,
    });

    return (
        <div className="mx-auto grid w-full max-w-5xl gap-8 px-4 pt-4 sm:px-6 sm:pt-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <div className="mx-auto w-full max-w-xl space-y-6">
                <header className="space-y-3">
                    <div className="flex items-center justify-between gap-2">
                        <p className="text-sm text-muted-foreground">
                            {etiqueta}
                        </p>
                        <ChatDudasBoton mensajes={mensajes} />
                    </div>
                    <div
                        role="progressbar"
                        aria-valuemin={1}
                        aria-valuemax={PANTALLAS}
                        aria-valuenow={pantalla}
                        aria-valuetext={etiqueta}
                        className="h-1.5 w-full overflow-hidden rounded-full bg-muted"
                    >
                        <div
                            className="h-full rounded-full bg-primary transition-[width]"
                            style={{
                                width: `${(pantalla / PANTALLAS) * 100}%`,
                            }}
                        />
                    </div>
                    <h1 className="font-display text-display text-foreground">
                        {titulo}
                    </h1>
                    {descripcion ? (
                        <p className="text-sm text-muted-foreground">
                            {descripcion}
                        </p>
                    ) : null}
                </header>

                {children}

                <div className="sticky bottom-0 z-10 -mx-4 border-t border-border bg-background/95 px-4 py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] backdrop-blur supports-[backdrop-filter]:bg-background/80 sm:mx-0 sm:px-0">
                    <div className="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <Button variant="ghost" asChild>
                            <Link href={anterior} prefetch>
                                <ArrowLeft aria-hidden="true" />
                                {t('entrevista.atras')}
                            </Link>
                        </Button>
                        <div className="flex flex-col gap-1 sm:items-end">
                            {accion}
                        </div>
                    </div>
                </div>
            </div>

            <ChatDudasLateral mensajes={mensajes} />
        </div>
    );
}
