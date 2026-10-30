import { useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import {
    CampoCasilla,
    CampoSelect,
    CampoTexto,
    FilasEditables,
} from '@/components/catalogo-fiscal/campos';
import { DialogoEdicion } from '@/components/catalogo-fiscal/dialogo-edicion';
import { Input } from '@/components/ui/input';
import { store, update } from '@/routes/catalogo-fiscal/preguntas';
import type { PreguntaCatalogo } from '@/types';

const TIPOS = [
    'si_no',
    'usted_conyuge_no',
    'opciones',
    'casillas',
    'dato',
    'fecha',
    'numero',
] as const;
const NIVELES = ['entrada', 'seguimiento', 'no_se_pregunta'] as const;
const BLOQUES = ['A', 'B', 'C', 'D', 'E', 'F', 'G'] as const;

type Opcion = { valor: string; texto_es: string; texto_en?: string | null };

/**
 * Crear o editar una pregunta (F1.5). El código y el bloque solo se ponen
 * al crear. La condición se escribe como JSON (ver el README del catálogo).
 */
export function FormularioPregunta({
    anio,
    anioVigente,
    pregunta,
    abierto,
    onCerrar,
    onGuardado,
}: {
    anio: number;
    anioVigente: boolean;
    pregunta: PreguntaCatalogo | null;
    abierto: boolean;
    onCerrar: () => void;
    onGuardado: () => void;
}) {
    const { t } = useTranslation();
    const creando = pregunta === null;
    const form = useForm({
        codigo: '',
        bloque: 'A',
        texto_es: pregunta?.texto_es ?? '',
        texto_en: pregunta?.texto_en ?? '',
        ayuda_es: pregunta?.ayuda_es ?? '',
        ayuda_en: pregunta?.ayuda_en ?? '',
        tipo_respuesta: pregunta?.tipo_respuesta ?? 'si_no',
        opciones: (pregunta?.opciones ?? []) as Opcion[],
        nivel: pregunta?.nivel ?? 'entrada',
        pantalla: pregunta?.pantalla ? String(pregunta.pantalla) : '',
        por_persona: pregunta?.por_persona ?? false,
        sensible: pregunta?.sensible ?? false,
        condicion: pregunta?.condicion
            ? JSON.stringify(pregunta.condicion)
            : '',
        efecto_es: pregunta?.efecto ?? '',
        fuente_texto: pregunta?.fuente_texto ?? '',
        fuente_url: pregunta?.fuente_url ?? '',
    });
    const llevaOpciones =
        form.data.tipo_respuesta === 'opciones' ||
        form.data.tipo_respuesta === 'casillas';

    const guardar = () => {
        let condicion: unknown = null;

        if (form.data.condicion.trim() !== '') {
            try {
                condicion = JSON.parse(form.data.condicion);
            } catch {
                form.setError(
                    'condicion',
                    t('catalogoFiscal.edicion.jsonInvalido'),
                );

                return;
            }
        }

        form.transform((d) => ({
            ...(creando ? { codigo: d.codigo, bloque: d.bloque } : {}),
            texto_es: d.texto_es,
            texto_en: d.texto_en || null,
            ayuda_es: d.ayuda_es || null,
            ayuda_en: d.ayuda_en || null,
            tipo_respuesta: d.tipo_respuesta,
            opciones: llevaOpciones
                ? d.opciones.map((o) => ({
                      ...o,
                      texto_en: o.texto_en || null,
                  }))
                : [],
            nivel: d.nivel,
            pantalla: d.pantalla === '' ? null : Number(d.pantalla),
            por_persona: d.por_persona,
            sensible: d.sensible,
            condicion,
            efecto_es: d.efecto_es || null,
            fuente_texto: d.fuente_texto || null,
            fuente_url: d.fuente_url || null,
        }));

        const opciones = { preserveScroll: true, onSuccess: onGuardado };

        if (creando) {
            form.post(store({ anio }).url, opciones);
        } else {
            form.patch(update({ anio, pregunta: pregunta.id }).url, opciones);
        }
    };

    return (
        <DialogoEdicion
            abierto={abierto}
            onCerrar={onCerrar}
            titulo={
                creando
                    ? t('catalogoFiscal.edicion.nuevaPregunta')
                    : t('catalogoFiscal.edicion.editarPieza', {
                          codigo: pregunta.codigo,
                      })
            }
            descripcion={t('catalogoFiscal.edicion.anioEnTexto')}
            anioVigente={anioVigente}
            anio={anio}
            procesando={form.processing}
            onGuardar={guardar}
        >
            {creando ? (
                <div className="grid gap-4 sm:grid-cols-2">
                    <CampoTexto
                        id="p-codigo"
                        etiqueta={t('catalogoFiscal.columnas.codigo')}
                        valor={form.data.codigo}
                        onCambio={(v) => form.setData('codigo', v)}
                        error={form.errors.codigo}
                        requerido
                        placeholder="D18"
                    />
                    <CampoSelect
                        id="p-bloque"
                        etiqueta={t('catalogoFiscal.columnas.bloque')}
                        valor={form.data.bloque}
                        onCambio={(v) => form.setData('bloque', v)}
                        error={form.errors.bloque}
                        opciones={BLOQUES.map((b) => ({
                            valor: b,
                            texto: t(`catalogoFiscal.bloques.${b}`),
                        }))}
                    />
                </div>
            ) : null}

            <CampoTexto
                id="p-texto"
                etiqueta={t('catalogoFiscal.edicion.textoPregunta')}
                valor={form.data.texto_es}
                onCambio={(v) => form.setData('texto_es', v)}
                error={form.errors.texto_es}
                multilinea
                requerido
            />
            <CampoTexto
                id="p-ayuda"
                etiqueta={t('catalogoFiscal.preguntas.ayuda')}
                valor={form.data.ayuda_es}
                onCambio={(v) => form.setData('ayuda_es', v)}
                error={form.errors.ayuda_es}
                multilinea
            />
            <CampoTexto
                id="p-texto-en"
                etiqueta={t('catalogoFiscal.edicion.textoPreguntaEn')}
                valor={form.data.texto_en}
                onCambio={(v) => form.setData('texto_en', v)}
                error={form.errors.texto_en}
                multilinea
            />
            <CampoTexto
                id="p-ayuda-en"
                etiqueta={t('catalogoFiscal.edicion.ayudaEn')}
                valor={form.data.ayuda_en}
                onCambio={(v) => form.setData('ayuda_en', v)}
                error={form.errors.ayuda_en}
                multilinea
            />

            <div className="grid gap-4 sm:grid-cols-3">
                <CampoSelect
                    id="p-tipo"
                    etiqueta={t('catalogoFiscal.preguntas.respuesta')}
                    valor={form.data.tipo_respuesta}
                    onCambio={(v) => form.setData('tipo_respuesta', v)}
                    error={form.errors.tipo_respuesta}
                    opciones={TIPOS.map((x) => ({
                        valor: x,
                        texto: t(`catalogoFiscal.tiposRespuesta.${x}`),
                    }))}
                />
                <CampoSelect
                    id="p-nivel"
                    etiqueta={t('catalogoFiscal.columnas.nivel')}
                    valor={form.data.nivel}
                    onCambio={(v) =>
                        form.setData('nivel', v as typeof form.data.nivel)
                    }
                    error={form.errors.nivel}
                    opciones={NIVELES.map((x) => ({
                        valor: x,
                        texto: t(`catalogoFiscal.niveles.${x}`),
                    }))}
                />
                <CampoTexto
                    id="p-pantalla"
                    etiqueta={t('catalogoFiscal.edicion.pantalla')}
                    valor={form.data.pantalla}
                    onCambio={(v) => form.setData('pantalla', v)}
                    error={form.errors.pantalla}
                    tipo="number"
                />
            </div>

            {llevaOpciones ? (
                <FilasEditables<Opcion>
                    nombre="opciones"
                    titulo={t('catalogoFiscal.edicion.opciones')}
                    filas={form.data.opciones}
                    onCambio={(f) => form.setData('opciones', f)}
                    nueva={() => ({ valor: '', texto_es: '', texto_en: '' })}
                    errores={form.errors as Record<string, string | undefined>}
                    textoAgregar={t('catalogoFiscal.edicion.agregarOpcion')}
                    columnas={[
                        {
                            campo: 'texto_es',
                            etiqueta: t('catalogoFiscal.edicion.textoOpcion'),
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
                            campo: 'texto_en',
                            etiqueta: t('catalogoFiscal.edicion.textoOpcionEn'),
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
                            campo: 'valor',
                            etiqueta: t('catalogoFiscal.edicion.valorOpcion'),
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
                    ]}
                />
            ) : null}

            <div className="grid gap-3 sm:grid-cols-2">
                <CampoCasilla
                    id="p-por-persona"
                    etiqueta={t('catalogoFiscal.preguntas.porPersona')}
                    marcado={form.data.por_persona}
                    onCambio={(v) => form.setData('por_persona', v)}
                />
                <CampoCasilla
                    id="p-sensible"
                    etiqueta={t('catalogoFiscal.preguntas.sensible')}
                    marcado={form.data.sensible}
                    onCambio={(v) => form.setData('sensible', v)}
                />
            </div>

            <CampoTexto
                id="p-condicion"
                etiqueta={t('catalogoFiscal.preguntas.cuandoSeMuestra')}
                valor={form.data.condicion}
                onCambio={(v) => form.setData('condicion', v)}
                error={form.errors.condicion}
                ayuda={t('catalogoFiscal.edicion.ayudaCondicion')}
                placeholder='{"pregunta":"B1","es":"casado"}'
            />
            <CampoTexto
                id="p-efecto"
                etiqueta={t('catalogoFiscal.preguntas.efecto')}
                valor={form.data.efecto_es}
                onCambio={(v) => form.setData('efecto_es', v)}
                error={form.errors.efecto_es}
                multilinea
            />
            <CampoTexto
                id="p-fuente"
                etiqueta={t('catalogoFiscal.detalle.fuente')}
                valor={form.data.fuente_texto}
                onCambio={(v) => form.setData('fuente_texto', v)}
                error={form.errors.fuente_texto}
                multilinea
            />
            <CampoTexto
                id="p-fuente-url"
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
