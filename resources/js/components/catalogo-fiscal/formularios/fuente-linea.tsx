import { router, usePage } from '@inertiajs/react';
import { CircleCheck, ExternalLink } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { EstadoValidacion } from '@/components/catalogo-fiscal/estado-validacion';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { validar as rutaValidar } from '@/routes/catalogo-fiscal';
import type { LineaCatalogo } from '@/types';

/**
 * Fuente del IRS de una línea que se calcula y su estado de validación (F6,
 * 6.0). Marcar "validada" solo se habilita si un caso de prueba contra el IRS
 * cubre la línea; si no, el botón queda deshabilitado y dice por qué.
 */
export function FuenteLinea({
    anio,
    linea,
    puedeValidar,
}: {
    anio: number;
    linea: LineaCatalogo;
    puedeValidar: boolean;
}) {
    const { t, i18n } = useTranslation();
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const [enCurso, setEnCurso] = useState(false);

    if (linea.origen_tipo !== 'calculo' && linea.origen_tipo !== 'linea') {
        return null;
    }

    const sinFuente = !linea.fuente_texto && !linea.fuente_url;
    const ayudaId = `validar-linea-ayuda-${linea.id}`;
    const motivo = sinFuente
        ? t('catalogoFiscal.formularios.sinFuenteLinea')
        : !linea.cubierta
          ? t('catalogoFiscal.formularios.faltaCaso')
          : null;

    const validar = () => {
        setEnCurso(true);
        router.post(
            rutaValidar({ anio, tipo: 'lineas', id: linea.id }).url,
            {},
            { preserveScroll: true, onFinish: () => setEnCurso(false) },
        );
    };

    return (
        <div className="mt-2 space-y-1.5 text-xs">
            <div className="flex flex-wrap items-center gap-2">
                <EstadoValidacion pieza={linea} />
                {linea.fuente_texto ? (
                    <span className="text-muted-foreground">
                        {linea.fuente_texto}
                    </span>
                ) : null}
                {linea.fuente_pagina ? (
                    <span className="text-muted-foreground">
                        {linea.fuente_pagina}
                    </span>
                ) : null}
                {linea.fuente_url ? (
                    <a
                        href={linea.fuente_url}
                        target="_blank"
                        rel="noreferrer noopener"
                        className="inline-flex items-center gap-1 rounded-sm font-medium text-primary underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        {t('catalogoFiscal.detalle.verEnIrs')}
                        <ExternalLink className="size-3" aria-hidden="true" />
                        <span className="sr-only">
                            {t('catalogoFiscal.detalle.abreNuevaPestana')}
                        </span>
                    </a>
                ) : null}
            </div>

            {linea.validado_en ? (
                <p className="text-muted-foreground">
                    {t('catalogoFiscal.formularios.validadaPor', {
                        nombre: linea.validado_por ?? '—',
                        fecha: new Date(linea.validado_en).toLocaleDateString(
                            i18n.language,
                        ),
                    })}
                </p>
            ) : puedeValidar ? (
                <div className="flex flex-wrap items-center gap-2">
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={validar}
                        disabled={enCurso || motivo !== null}
                        aria-describedby={motivo ? ayudaId : undefined}
                    >
                        {enCurso ? (
                            <Spinner aria-hidden="true" />
                        ) : (
                            <CircleCheck aria-hidden="true" />
                        )}
                        {t('catalogoFiscal.formularios.marcarLineaValidada')}
                    </Button>
                    {motivo ? (
                        <span id={ayudaId} className="text-muted-foreground">
                            {motivo}
                        </span>
                    ) : null}
                </div>
            ) : null}
            <InputError message={errors.validar} />
        </div>
    );
}
