import { useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import {
    CampoCasilla,
    CampoTexto,
    FilasEditables,
} from '@/components/catalogo-fiscal/campos';
import { DialogoEdicion } from '@/components/catalogo-fiscal/dialogo-edicion';
import { Input } from '@/components/ui/input';
import { update } from '@/routes/catalogo-fiscal/parametros';
import type { ParametroCatalogo } from '@/types';

type Tramo = { desde: string; tasa: string };

/**
 * Editar un parámetro del año (F1.5). Se puede dejar "pendiente" (sin
 * cifra): la plataforma nunca inventa un monto.
 */
export function FormularioParametro({
    anio,
    anioVigente,
    parametro,
    abierto,
    onCerrar,
    onGuardado,
}: {
    anio: number;
    anioVigente: boolean;
    parametro: ParametroCatalogo;
    abierto: boolean;
    onCerrar: () => void;
    onGuardado: () => void;
}) {
    const { t } = useTranslation();
    const esTabla = parametro.tipo === 'tabla';
    const form = useForm({
        pendiente: parametro.valor === null,
        valor:
            parametro.valor === null || esTabla ? '' : String(parametro.valor),
        tramos: (esTabla && Array.isArray(parametro.valor)
            ? (parametro.valor as { desde: number; tasa: number }[]).map(
                  (x) => ({ desde: String(x.desde), tasa: String(x.tasa) }),
              )
            : []) as Tramo[],
        nombre_es: parametro.nombre,
        condicion_es: parametro.condicion ?? '',
        formulario_linea: parametro.formulario_linea ?? '',
        fuente_texto: parametro.fuente_texto ?? '',
        fuente_url: parametro.fuente_url ?? '',
    });

    const guardar = () => {
        form.transform((d) => ({
            valor: d.pendiente ? null : esTabla ? d.tramos : d.valor,
            nombre_es: d.nombre_es,
            condicion_es: d.condicion_es || null,
            formulario_linea: d.formulario_linea || null,
            fuente_texto: d.fuente_texto || null,
            fuente_url: d.fuente_url || null,
        }));

        form.patch(update({ anio, parametro: parametro.id }).url, {
            preserveScroll: true,
            onSuccess: onGuardado,
        });
    };

    const ayudaValor =
        parametro.tipo === 'porcentaje'
            ? t('catalogoFiscal.edicion.ayudaPorcentaje')
            : parametro.tipo === 'monto'
              ? t('catalogoFiscal.edicion.ayudaMonto')
              : undefined;

    return (
        <DialogoEdicion
            abierto={abierto}
            onCerrar={onCerrar}
            titulo={parametro.nombre}
            descripcion={parametro.codigo}
            anioVigente={anioVigente}
            anio={anio}
            procesando={form.processing}
            onGuardar={guardar}
        >
            <CampoCasilla
                id="pa-pendiente"
                etiqueta={t('catalogoFiscal.edicion.dejarPendiente')}
                ayuda={t('catalogoFiscal.edicion.ayudaPendiente')}
                marcado={form.data.pendiente}
                onCambio={(v) => form.setData('pendiente', v)}
            />

            {form.data.pendiente ? null : esTabla ? (
                <FilasEditables<Tramo>
                    nombre="valor"
                    titulo={t('catalogoFiscal.edicion.tramos')}
                    filas={form.data.tramos}
                    onCambio={(f) => form.setData('tramos', f)}
                    nueva={() => ({ desde: '', tasa: '' })}
                    errores={form.errors as Record<string, string | undefined>}
                    textoAgregar={t('catalogoFiscal.edicion.agregarTramo')}
                    columnas={[
                        {
                            campo: 'desde',
                            etiqueta: t('catalogoFiscal.edicion.desde'),
                            render: (v, c, id, inv) => (
                                <Input
                                    id={id}
                                    type="number"
                                    step="any"
                                    value={String(v ?? '')}
                                    onChange={(e) => c(e.target.value)}
                                    aria-invalid={inv || undefined}
                                />
                            ),
                        },
                        {
                            campo: 'tasa',
                            etiqueta: t('catalogoFiscal.edicion.tasa'),
                            render: (v, c, id, inv) => (
                                <Input
                                    id={id}
                                    type="number"
                                    step="any"
                                    value={String(v ?? '')}
                                    onChange={(e) => c(e.target.value)}
                                    aria-invalid={inv || undefined}
                                />
                            ),
                        },
                    ]}
                />
            ) : (
                <CampoTexto
                    id="pa-valor"
                    etiqueta={t('catalogoFiscal.columnas.valor')}
                    valor={form.data.valor}
                    onCambio={(v) => form.setData('valor', v)}
                    error={form.errors.valor}
                    ayuda={ayudaValor}
                    tipo={parametro.tipo === 'fecha' ? 'date' : 'number'}
                    requerido
                />
            )}

            <CampoTexto
                id="pa-nombre"
                etiqueta={t('catalogoFiscal.columnas.parametro')}
                valor={form.data.nombre_es}
                onCambio={(v) => form.setData('nombre_es', v)}
                error={form.errors.nombre_es}
                requerido
            />
            <CampoTexto
                id="pa-condicion"
                etiqueta={t('catalogoFiscal.parametros.condicion')}
                valor={form.data.condicion_es}
                onCambio={(v) => form.setData('condicion_es', v)}
                error={form.errors.condicion_es}
                multilinea
            />
            <CampoTexto
                id="pa-linea"
                etiqueta={t('catalogoFiscal.columnas.dondeSeUsa')}
                valor={form.data.formulario_linea}
                onCambio={(v) => form.setData('formulario_linea', v)}
                error={form.errors.formulario_linea}
            />
            <CampoTexto
                id="pa-fuente"
                etiqueta={t('catalogoFiscal.detalle.fuente')}
                valor={form.data.fuente_texto}
                onCambio={(v) => form.setData('fuente_texto', v)}
                error={form.errors.fuente_texto}
                multilinea
            />
            <CampoTexto
                id="pa-fuente-url"
                etiqueta={t('catalogoFiscal.edicion.enlaceFuente')}
                valor={form.data.fuente_url}
                onCambio={(v) => form.setData('fuente_url', v)}
                error={form.errors.fuente_url}
                tipo="url"
                placeholder="https://www.irs.gov/…"
            />
        </DialogoEdicion>
    );
}
