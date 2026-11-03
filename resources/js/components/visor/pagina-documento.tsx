import { ChevronLeft, ChevronRight, ImageOff } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import type { Hoja, Recuadro } from '@/types/lectura';

/**
 * La imagen de una hoja con el recuadro del dato elegido encima. El recuadro
 * es un <rect> SVG en coordenadas 0–1 (origen arriba a la izquierda), así
 * que sigue a la imagen sea cual sea su tamaño en pantalla. Mientras la
 * imagen carga se ve un esqueleto; si falla, un aviso.
 */
export function PaginaDocumento({
    hojas,
    indice,
    onCambiar,
    recuadro,
}: {
    hojas: Hoja[];
    indice: number;
    onCambiar: (indice: number) => void;
    recuadro: Recuadro | null;
}) {
    const { t } = useTranslation();
    const hoja = hojas[indice];

    return (
        <div className="space-y-3">
            {hojas.length > 1 ? (
                <div className="flex items-center justify-between gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => onCambiar(indice - 1)}
                        disabled={indice === 0}
                    >
                        <ChevronLeft aria-hidden="true" />
                        {t('visor.anterior')}
                    </Button>
                    <p
                        className="text-sm text-muted-foreground"
                        aria-live="polite"
                    >
                        {t('visor.hojaDe', {
                            actual: indice + 1,
                            total: hojas.length,
                        })}
                    </p>
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => onCambiar(indice + 1)}
                        disabled={indice === hojas.length - 1}
                    >
                        {t('visor.siguiente')}
                        <ChevronRight aria-hidden="true" />
                    </Button>
                </div>
            ) : null}
            {/* `key` reinicia el estado de carga al cambiar de hoja. */}
            <ImagenHoja
                key={hoja.url}
                hoja={hoja}
                primera={indice === 0}
                recuadro={recuadro}
            />
        </div>
    );
}

function ImagenHoja({
    hoja,
    primera,
    recuadro,
}: {
    hoja: Hoja;
    primera: boolean;
    recuadro: Recuadro | null;
}) {
    const { t } = useTranslation();
    const [estado, setEstado] = useState<'cargando' | 'lista' | 'error'>(
        'cargando',
    );

    if (estado === 'error') {
        return (
            <div
                role="alert"
                className="flex min-h-48 flex-col items-center justify-center gap-2 rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground"
            >
                <ImageOff className="size-6" aria-hidden="true" />
                {t('visor.imagenError')}
            </div>
        );
    }

    return (
        <div className="relative overflow-hidden rounded-lg border bg-muted/30">
            {estado === 'cargando' ? (
                <Skeleton
                    className="aspect-[8.5/11] w-full rounded-none"
                    aria-label={t('visor.cargandoImagen')}
                />
            ) : null}
            <div className={estado === 'cargando' ? 'hidden' : 'relative'}>
                <img
                    src={hoja.url}
                    alt={t('visor.imagenAlt', { archivo: hoja.archivo })}
                    loading={primera ? 'eager' : 'lazy'}
                    className="block h-auto w-full"
                    onLoad={() => setEstado('lista')}
                    onError={() => setEstado('error')}
                />
                {recuadro ? (
                    <svg
                        aria-hidden="true"
                        viewBox="0 0 1 1"
                        preserveAspectRatio="none"
                        className="pointer-events-none absolute inset-0 size-full"
                    >
                        <rect
                            x={recuadro.x}
                            y={recuadro.y}
                            width={recuadro.ancho}
                            height={recuadro.alto}
                            className="fill-amber-400/25 stroke-amber-500"
                            strokeWidth={3}
                            vectorEffect="non-scaling-stroke"
                        />
                    </svg>
                ) : null}
            </div>
        </div>
    );
}
