import { useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import {
    CampoSelect,
    CampoTexto,
    FilasEditables,
} from '@/components/catalogo-fiscal/campos';
import { DialogoEdicion } from '@/components/catalogo-fiscal/dialogo-edicion';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { store, update } from '@/routes/catalogo-fiscal/formularios';
import type { FormularioCatalogo } from '@/types';

const FICHAS = ['completa', 'conector', 'pendiente'] as const;
const ORIGENES = ['casilla', 'linea', 'respuesta', 'calculo'] as const;

type Linea = {
    linea: string;
    descripcion: string;
    origen_tipo: string;
    origen_clave: string;
};

export function FormularioFormulario({
    anio,
    anioVigente,
    formulario,
    abierto,
    onCerrar,
    onGuardado,
}: {
    anio: number;
    anioVigente: boolean;
    formulario: FormularioCatalogo | null;
    abierto: boolean;
    onCerrar: () => void;
    onGuardado: () => void;
}) {
    const { t } = useTranslation();
    const creando = formulario === null;
    const form = useForm({
        codigo: '',
        nombre: formulario?.nombre ?? '',
        para_que: formulario?.para_que ?? '',
        ficha: formulario?.ficha ?? 'pendiente',
        lineas: (formulario?.lineas ?? []).map((l) => ({
            linea: l.linea,
            descripcion: l.descripcion,
            origen_tipo: l.origen_tipo,
            origen_clave: l.origen_clave ?? '',
        })) as Linea[],
        fuente_texto: formulario?.fuente_texto ?? '',
        fuente_url: formulario?.fuente_url ?? '',
    });

    const guardar = () => {
        form.transform((d) => ({
            ...(creando ? { codigo: d.codigo } : {}),
            nombre: d.nombre,
            para_que: d.para_que || null,
            ficha: d.ficha,
            lineas: d.lineas.map((l) => ({
                ...l,
                origen_clave:
                    l.origen_tipo === 'calculo' ? null : l.origen_clave || null,
            })),
            fuente_texto: d.fuente_texto || null,
            fuente_url: d.fuente_url || null,
        }));

        const opciones = { preserveScroll: true, onSuccess: onGuardado };

        if (creando) {
            form.post(store({ anio }).url, opciones);
        } else {
            form.patch(
                update({ anio, formulario: formulario.id }).url,
                opciones,
            );
        }
    };

    return (
        <DialogoEdicion
            abierto={abierto}
            onCerrar={onCerrar}
            titulo={
                creando
                    ? t('catalogoFiscal.edicion.nuevoFormulario')
                    : t('catalogoFiscal.edicion.editarPieza', {
                          codigo: formulario.codigo,
                      })
            }
            anioVigente={anioVigente}
            anio={anio}
            procesando={form.processing}
            onGuardar={guardar}
        >
            <div className="grid gap-4 sm:grid-cols-2">
                {creando ? (
                    <CampoTexto
                        id="f-codigo"
                        etiqueta={t('catalogoFiscal.columnas.codigo')}
                        valor={form.data.codigo}
                        onCambio={(v) => form.setData('codigo', v)}
                        error={form.errors.codigo}
                        requerido
                        placeholder="f8863"
                    />
                ) : null}
                <CampoTexto
                    id="f-nombre"
                    etiqueta={t('catalogoFiscal.columnas.formulario')}
                    valor={form.data.nombre}
                    onCambio={(v) => form.setData('nombre', v)}
                    error={form.errors.nombre}
                    requerido
                />
                <CampoSelect
                    id="f-ficha"
                    etiqueta={t('catalogoFiscal.columnas.ficha')}
                    valor={form.data.ficha}
                    onCambio={(v) =>
                        form.setData('ficha', v as typeof form.data.ficha)
                    }
                    error={form.errors.ficha}
                    opciones={FICHAS.map((f) => ({
                        valor: f,
                        texto: t(`catalogoFiscal.fichas.${f}`),
                    }))}
                />
            </div>
            <CampoTexto
                id="f-para-que"
                etiqueta={t('catalogoFiscal.edicion.paraQue')}
                valor={form.data.para_que}
                onCambio={(v) => form.setData('para_que', v)}
                error={form.errors.para_que}
                multilinea
            />

            <FilasEditables<Linea>
                nombre="lineas"
                titulo={t('catalogoFiscal.formularios.lineas')}
                filas={form.data.lineas}
                onCambio={(f) => form.setData('lineas', f)}
                nueva={() => ({
                    linea: '',
                    descripcion: '',
                    origen_tipo: 'calculo',
                    origen_clave: '',
                })}
                errores={form.errors as Record<string, string | undefined>}
                textoAgregar={t('catalogoFiscal.edicion.agregarLinea')}
                columnas={[
                    {
                        campo: 'linea',
                        etiqueta: t('catalogoFiscal.formularios.linea'),
                        render: (v, c, id, inv) => (
                            <Input
                                id={id}
                                value={String(v ?? '')}
                                onChange={(e) => c(e.target.value)}
                                aria-invalid={inv || undefined}
                                className="font-mono"
                            />
                        ),
                    },
                    {
                        campo: 'descripcion',
                        etiqueta: t('catalogoFiscal.documentos.descripcion'),
                        ancho: 'sm:col-span-2',
                        render: (v, c, id, inv) => (
                            <Input
                                id={id}
                                value={String(v ?? '')}
                                onChange={(e) => c(e.target.value)}
                                aria-invalid={inv || undefined}
                            />
                        ),
                    },
                    {
                        campo: 'origen_tipo',
                        etiqueta: t('catalogoFiscal.formularios.deDondeSale'),
                        render: (v, c, id) => (
                            <Select
                                value={String(v)}
                                onValueChange={(x) => c(x)}
                            >
                                <SelectTrigger id={id}>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {ORIGENES.map((o) => (
                                        <SelectItem key={o} value={o}>
                                            {t(`catalogoFiscal.origenes.${o}`)}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        ),
                    },
                    {
                        campo: 'origen_clave',
                        etiqueta: t('catalogoFiscal.edicion.origenClave'),
                        render: (v, c, id, inv) => (
                            <Input
                                id={id}
                                value={String(v ?? '')}
                                onChange={(e) => c(e.target.value)}
                                aria-invalid={inv || undefined}
                                placeholder="w2:1"
                                className="font-mono"
                            />
                        ),
                    },
                ]}
            />

            <CampoTexto
                id="f-fuente"
                etiqueta={t('catalogoFiscal.detalle.fuente')}
                valor={form.data.fuente_texto}
                onCambio={(v) => form.setData('fuente_texto', v)}
                error={form.errors.fuente_texto}
                multilinea
            />
            <CampoTexto
                id="f-fuente-url"
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
