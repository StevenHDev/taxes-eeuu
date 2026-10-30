import { useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { CampoSelect, CampoTexto } from '@/components/catalogo-fiscal/campos';
import { DialogoEdicion } from '@/components/catalogo-fiscal/dialogo-edicion';
import { store, update } from '@/routes/catalogo-fiscal/reglas';
import type { ReglaCatalogo } from '@/types';

const SEVERIDADES = ['bloqueante', 'revision', 'informativa'] as const;

export function FormularioRegla({
    anio,
    anioVigente,
    regla,
    abierto,
    onCerrar,
    onGuardado,
}: {
    anio: number;
    anioVigente: boolean;
    regla: ReglaCatalogo | null;
    abierto: boolean;
    onCerrar: () => void;
    onGuardado: () => void;
}) {
    const { t } = useTranslation();
    const creando = regla === null;
    const form = useForm({
        codigo: '',
        descripcion: regla?.descripcion ?? '',
        severidad: regla?.severidad ?? 'revision',
        fuente_texto: regla?.fuente_texto ?? '',
        fuente_url: regla?.fuente_url ?? '',
    });

    const guardar = () => {
        form.transform((d) => ({
            ...(creando ? { codigo: d.codigo } : {}),
            descripcion: d.descripcion,
            severidad: d.severidad,
            fuente_texto: d.fuente_texto || null,
            fuente_url: d.fuente_url || null,
        }));

        const opciones = { preserveScroll: true, onSuccess: onGuardado };

        if (creando) {
            form.post(store({ anio }).url, opciones);
        } else {
            form.patch(update({ anio, regla: regla.id }).url, opciones);
        }
    };

    return (
        <DialogoEdicion
            abierto={abierto}
            onCerrar={onCerrar}
            titulo={
                creando
                    ? t('catalogoFiscal.edicion.nuevaRegla')
                    : t('catalogoFiscal.edicion.editarPieza', {
                          codigo: regla.codigo,
                      })
            }
            anioVigente={anioVigente}
            anio={anio}
            procesando={form.processing}
            onGuardar={guardar}
        >
            {creando ? (
                <CampoTexto
                    id="r-codigo"
                    etiqueta={t('catalogoFiscal.columnas.codigo')}
                    valor={form.data.codigo}
                    onCambio={(v) => form.setData('codigo', v)}
                    error={form.errors.codigo}
                    requerido
                    placeholder="R-14"
                />
            ) : null}
            <CampoTexto
                id="r-descripcion"
                etiqueta={t('catalogoFiscal.columnas.regla')}
                valor={form.data.descripcion}
                onCambio={(v) => form.setData('descripcion', v)}
                error={form.errors.descripcion}
                multilinea
                requerido
            />
            <CampoSelect
                id="r-severidad"
                etiqueta={t('catalogoFiscal.columnas.severidad')}
                valor={form.data.severidad}
                onCambio={(v) =>
                    form.setData('severidad', v as typeof form.data.severidad)
                }
                error={form.errors.severidad}
                opciones={SEVERIDADES.map((s) => ({
                    valor: s,
                    texto: `${t(`catalogoFiscal.severidades.${s}`)} — ${t(`catalogoFiscal.severidadesAyuda.${s}`)}`,
                }))}
            />
            <CampoTexto
                id="r-fuente"
                etiqueta={t('catalogoFiscal.detalle.fuente')}
                valor={form.data.fuente_texto}
                onCambio={(v) => form.setData('fuente_texto', v)}
                error={form.errors.fuente_texto}
                multilinea
            />
            <CampoTexto
                id="r-fuente-url"
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
