import { useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import {
    CampoCasilla,
    CampoSelect,
    CampoTexto,
    FilasEditables,
} from '@/components/catalogo-fiscal/campos';
import { DialogoEdicion } from '@/components/catalogo-fiscal/dialogo-edicion';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { store, update } from '@/routes/catalogo-fiscal/documentos';
import type { DocumentoCatalogo } from '@/types';

const GRUPOS = [
    'identidad',
    'trabajo',
    'negocio',
    'jubilacion',
    'inversiones',
    'otros_ingresos',
    'gastos',
    'situaciones',
    'pagos',
] as const;
const UNO_POR = ['unico', 'empleo', 'poliza', 'persona', 'fuente'] as const;
const FORMATOS = ['pdf', 'jpg', 'png', 'heic', 'xlsx', 'csv'] as const;

type Casilla = {
    casilla: string;
    descripcion: string;
    tipo_dato: string;
    clave: boolean;
};

export function FormularioDocumento({
    anio,
    anioVigente,
    documento,
    abierto,
    onCerrar,
    onGuardado,
}: {
    anio: number;
    anioVigente: boolean;
    documento: DocumentoCatalogo | null;
    abierto: boolean;
    onCerrar: () => void;
    onGuardado: () => void;
}) {
    const { t } = useTranslation();
    const creando = documento === null;
    const form = useForm({
        codigo: '',
        grupo: documento?.grupo ?? 'identidad',
        nombre_sencillo_es: documento?.nombre ?? '',
        nombre_oficial: documento?.nombre_oficial ?? '',
        emisor_es: documento?.emisor ?? '',
        fecha_llegada: documento?.fecha_llegada ?? '',
        si_no_llega_es: documento?.si_no_llega ?? '',
        formatos: documento?.formatos ?? ['pdf', 'jpg', 'png', 'heic'],
        uno_por: documento?.uno_por ?? 'unico',
        casillas: (documento?.casillas ?? []).map((c) => ({
            ...c,
            tipo_dato: c.tipo_dato ?? '',
        })) as Casilla[],
        fuente_texto: documento?.fuente_texto ?? '',
        fuente_url: documento?.fuente_url ?? '',
    });

    const guardar = () => {
        form.transform((d) => ({
            ...(creando ? { codigo: d.codigo } : {}),
            grupo: d.grupo,
            nombre_sencillo_es: d.nombre_sencillo_es,
            nombre_oficial: d.nombre_oficial || null,
            emisor_es: d.emisor_es || null,
            fecha_llegada: d.fecha_llegada || null,
            si_no_llega_es: d.si_no_llega_es || null,
            formatos: d.formatos,
            uno_por: d.uno_por,
            casillas: d.casillas.map((c) => ({
                ...c,
                tipo_dato: c.tipo_dato || null,
            })),
            fuente_texto: d.fuente_texto || null,
            fuente_url: d.fuente_url || null,
        }));

        const opciones = { preserveScroll: true, onSuccess: onGuardado };

        if (creando) {
            form.post(store({ anio }).url, opciones);
        } else {
            form.patch(update({ anio, documento: documento.id }).url, opciones);
        }
    };

    const alternarFormato = (f: string, marcado: boolean) =>
        form.setData(
            'formatos',
            marcado
                ? [...form.data.formatos, f]
                : form.data.formatos.filter((x) => x !== f),
        );

    return (
        <DialogoEdicion
            abierto={abierto}
            onCerrar={onCerrar}
            titulo={
                creando
                    ? t('catalogoFiscal.edicion.nuevoDocumento')
                    : t('catalogoFiscal.edicion.editarPieza', {
                          codigo: documento.codigo,
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
                        id="d-codigo"
                        etiqueta={t('catalogoFiscal.columnas.codigo')}
                        valor={form.data.codigo}
                        onCambio={(v) => form.setData('codigo', v)}
                        error={form.errors.codigo}
                        requerido
                        placeholder="1099-int"
                    />
                ) : null}
                <CampoSelect
                    id="d-grupo"
                    etiqueta={t('catalogoFiscal.columnas.grupo')}
                    valor={form.data.grupo}
                    onCambio={(v) => form.setData('grupo', v)}
                    error={form.errors.grupo}
                    opciones={GRUPOS.map((g) => ({
                        valor: g,
                        texto: t(`catalogoFiscal.gruposDocumento.${g}`),
                    }))}
                />
            </div>
            <CampoTexto
                id="d-nombre"
                etiqueta={t('catalogoFiscal.edicion.nombreSencillo')}
                valor={form.data.nombre_sencillo_es}
                onCambio={(v) => form.setData('nombre_sencillo_es', v)}
                error={form.errors.nombre_sencillo_es}
                requerido
            />
            <div className="grid gap-4 sm:grid-cols-2">
                <CampoTexto
                    id="d-oficial"
                    etiqueta={t('catalogoFiscal.edicion.nombreOficial')}
                    valor={form.data.nombre_oficial}
                    onCambio={(v) => form.setData('nombre_oficial', v)}
                    error={form.errors.nombre_oficial}
                />
                <CampoTexto
                    id="d-emisor"
                    etiqueta={t('catalogoFiscal.documentos.emisor')}
                    valor={form.data.emisor_es}
                    onCambio={(v) => form.setData('emisor_es', v)}
                    error={form.errors.emisor_es}
                />
                <CampoTexto
                    id="d-fecha"
                    etiqueta={t('catalogoFiscal.documentos.llegaA')}
                    valor={form.data.fecha_llegada}
                    onCambio={(v) => form.setData('fecha_llegada', v)}
                    error={form.errors.fecha_llegada}
                    tipo="date"
                />
                <CampoSelect
                    id="d-uno-por"
                    etiqueta={t('catalogoFiscal.documentos.unoPor')}
                    valor={form.data.uno_por}
                    onCambio={(v) => form.setData('uno_por', v)}
                    error={form.errors.uno_por}
                    opciones={UNO_POR.map((u) => ({
                        valor: u,
                        texto: t(`catalogoFiscal.unoPor.${u}`),
                    }))}
                />
            </div>
            <CampoTexto
                id="d-si-no-llega"
                etiqueta={t('catalogoFiscal.documentos.siNoLlega')}
                valor={form.data.si_no_llega_es}
                onCambio={(v) => form.setData('si_no_llega_es', v)}
                error={form.errors.si_no_llega_es}
                multilinea
            />

            <fieldset className="grid gap-2">
                <legend className="mb-1 text-sm font-medium">
                    {t('catalogoFiscal.documentos.formatos')}
                </legend>
                <div className="flex flex-wrap gap-4">
                    {FORMATOS.map((f) => (
                        <CampoCasilla
                            key={f}
                            id={`d-formato-${f}`}
                            etiqueta={f.toUpperCase()}
                            marcado={form.data.formatos.includes(f)}
                            onCambio={(v) => alternarFormato(f, v)}
                        />
                    ))}
                </div>
            </fieldset>

            <FilasEditables<Casilla>
                nombre="casillas"
                titulo={t('catalogoFiscal.documentos.casillas')}
                filas={form.data.casillas}
                onCambio={(f) => form.setData('casillas', f)}
                nueva={() => ({
                    casilla: '',
                    descripcion: '',
                    tipo_dato: '',
                    clave: false,
                })}
                errores={form.errors as Record<string, string | undefined>}
                textoAgregar={t('catalogoFiscal.edicion.agregarCasilla')}
                columnas={[
                    {
                        campo: 'casilla',
                        etiqueta: t('catalogoFiscal.documentos.casilla'),
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
                        campo: 'clave',
                        etiqueta: t('catalogoFiscal.documentos.clave'),
                        render: (v, c, id) => (
                            <Checkbox
                                id={id}
                                checked={v === true}
                                onCheckedChange={(x) => c(x === true)}
                            />
                        ),
                    },
                ]}
            />

            <CampoTexto
                id="d-fuente"
                etiqueta={t('catalogoFiscal.detalle.fuente')}
                valor={form.data.fuente_texto}
                onCambio={(v) => form.setData('fuente_texto', v)}
                error={form.errors.fuente_texto}
                multilinea
            />
            <CampoTexto
                id="d-fuente-url"
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
