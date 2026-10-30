import { router, usePage } from '@inertiajs/react';
import { CircleCheck, Pencil, Power } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import {
    activo as rutaActivo,
    validar as rutaValidar,
} from '@/routes/catalogo-fiscal';
import type { FuenteIrs } from '@/types';

type Tipo =
    'preguntas' | 'documentos' | 'formularios' | 'reglas' | 'parametros';

/** Datos para editar: solo existe si quien mira es administrador. */
export type Editor = { anio: number; vigente: boolean };

/**
 * Acciones en el detalle de una pieza: el administrador edita, valida y
 * desactiva (F1.5); el preparador solo valida (F2.1), así que sin
 * `onEditar` solo aparece "Marcar como validado". Para el administrador la
 * acción principal es "Editar"; para el preparador, validar.
 */
export function AccionesPieza({
    anio,
    tipo,
    pieza,
    onEditar,
    onHecho,
    sePuedeDesactivar = true,
}: {
    anio: number;
    tipo: Tipo;
    pieza: FuenteIrs & { id: number; activo: boolean; codigo: string };
    /** Solo el administrador; sin esto no se ven Editar ni Desactivar. */
    onEditar?: () => void;
    onHecho: () => void;
    sePuedeDesactivar?: boolean;
}) {
    const { t } = useTranslation();
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const [enCurso, setEnCurso] = useState<'validar' | 'activo' | null>(null);

    const validar = () => {
        setEnCurso('validar');
        router.post(
            rutaValidar({ anio, tipo, id: pieza.id }).url,
            {},
            {
                preserveScroll: true,
                onSuccess: onHecho,
                onFinish: () => setEnCurso(null),
            },
        );
    };

    const cambiarActivo = () => {
        setEnCurso('activo');
        router.patch(
            rutaActivo({ anio, tipo, id: pieza.id }).url,
            { activo: !pieza.activo },
            {
                preserveScroll: true,
                onSuccess: onHecho,
                onFinish: () => setEnCurso(null),
            },
        );
    };

    const sinFuente = !pieza.fuente_url;

    return (
        <section
            className="grid gap-3 rounded-lg border border-border/60 bg-muted/30 p-3"
            aria-label={t('catalogoFiscal.edicion.acciones')}
        >
            <div className="flex flex-wrap gap-2">
                {onEditar ? (
                    <Button
                        type="button"
                        onClick={onEditar}
                        disabled={enCurso !== null}
                    >
                        <Pencil aria-hidden="true" />
                        {t('catalogoFiscal.edicion.editar')}
                    </Button>
                ) : null}
                <Button
                    type="button"
                    variant={onEditar ? 'outline' : 'default'}
                    onClick={validar}
                    disabled={
                        enCurso !== null ||
                        Boolean(pieza.validado_en) ||
                        sinFuente
                    }
                    aria-describedby={
                        sinFuente ? `validar-ayuda-${pieza.id}` : undefined
                    }
                >
                    {enCurso === 'validar' ? (
                        <Spinner aria-hidden="true" />
                    ) : (
                        <CircleCheck aria-hidden="true" />
                    )}
                    {pieza.validado_en
                        ? t('catalogoFiscal.edicion.yaValidado')
                        : t('catalogoFiscal.edicion.marcarValidado')}
                </Button>
                {onEditar && sePuedeDesactivar ? (
                    <Button
                        type="button"
                        variant="ghost"
                        onClick={cambiarActivo}
                        disabled={enCurso !== null}
                    >
                        {enCurso === 'activo' ? (
                            <Spinner aria-hidden="true" />
                        ) : (
                            <Power aria-hidden="true" />
                        )}
                        {pieza.activo
                            ? t('catalogoFiscal.edicion.desactivar')
                            : t('catalogoFiscal.edicion.reactivar')}
                    </Button>
                ) : null}
            </div>
            {sinFuente && !pieza.validado_en ? (
                <p
                    id={`validar-ayuda-${pieza.id}`}
                    className="text-xs text-muted-foreground"
                >
                    {onEditar
                        ? t('catalogoFiscal.edicion.validarSinFuente')
                        : t(
                              'catalogoFiscal.edicion.validarSinFuentePreparador',
                          )}
                </p>
            ) : null}
            <InputError message={errors.fuente_url} />
        </section>
    );
}
