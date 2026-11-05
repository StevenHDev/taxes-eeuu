import { router } from '@inertiajs/react';
import { CircleAlert } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

/**
 * Error de una sección con datos diferidos (`<Deferred rescue>`): dice qué
 * pasó y deja reintentar solo esas props, sin recargar la página entera.
 */
export function SeccionError({
    only,
    titulo,
    detalle,
}: {
    only: string[];
    titulo?: string;
    detalle?: string;
}) {
    const { t } = useTranslation();
    const [cargando, setCargando] = useState(false);

    const reintentar = () => {
        setCargando(true);
        router.reload({
            only,
            onFinish: () => setCargando(false),
        });
    };

    return (
        <Alert variant="destructive" role="alert">
            <CircleAlert aria-hidden="true" />
            <AlertTitle>{titulo ?? t('ficha.errorSeccion.titulo')}</AlertTitle>
            <AlertDescription className="space-y-3">
                <p>{detalle ?? t('ficha.errorSeccion.detalle')}</p>
                <Button
                    type="button"
                    variant="outline"
                    className="h-11 sm:h-9"
                    disabled={cargando}
                    aria-busy={cargando}
                    onClick={reintentar}
                >
                    {cargando ? <Spinner aria-hidden="true" /> : null}
                    {cargando
                        ? t('ficha.errorSeccion.reintentando')
                        : t('ficha.errorSeccion.reintentar')}
                </Button>
            </AlertDescription>
        </Alert>
    );
}
