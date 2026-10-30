import { Head, useForm } from '@inertiajs/react';
import { useId, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import AlertError from '@/components/alert-error';
import { DocumentoFila } from '@/components/entrevista/documento-fila';
import { ListaCasillas } from '@/components/entrevista/lista-casillas';
import { MarcoEntrevista } from '@/components/entrevista/marco-entrevista';
import { PreguntaDato } from '@/components/entrevista/pregunta-dato';
import { PreguntaOpciones } from '@/components/entrevista/pregunta-opciones';
import { PreguntaSiNo } from '@/components/entrevista/pregunta-si-no';
import { PreguntaUstedConyuge } from '@/components/entrevista/pregunta-usted-conyuge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import type { PortalMensaje } from '@/types/agente';
import type {
    DocumentoEntrevista,
    PreguntaPaso,
    ValorEntrevista,
} from '@/types/entrevista';

type Props = {
    taxYear: number;
    pantalla: number;
    paso: 'entrada' | 'seguimiento';
    persona: { id: number; orden: number; etiqueta: string } | null;
    preguntas: PreguntaPaso[];
    conConyuge: boolean;
    accion: string;
    anterior: string;
    sugeridos: Record<string, string>;
    /** F2.6: lo que se sube en este paso (identidad, tarjeta de la persona). */
    documentos: DocumentoEntrevista[];
    mensajes?: PortalMensaje[];
};

/**
 * Un paso de la entrevista (F2.5). Las preguntas ya vienen filtradas por el
 * servidor; la página solo las muestra y guarda todas juntas. El `key`
 * hace que cada paso empiece con su propio formulario.
 */
export default function PasoEntrevista(props: Props) {
    return (
        <FormularioPaso
            key={`${props.pantalla}-${props.paso}-${props.persona?.id ?? ''}`}
            {...props}
        />
    );
}

function FormularioPaso({
    taxYear,
    pantalla,
    paso,
    persona,
    preguntas,
    conConyuge,
    accion,
    anterior,
    sugeridos,
    documentos,
    mensajes,
}: Props) {
    const { t } = useTranslation();
    const resumenRef = useRef<HTMLDivElement>(null);
    const motivoId = useId();

    const form = useForm<{ respuestas: Record<string, ValorEntrevista> }>({
        respuestas: Object.fromEntries(
            preguntas.map((p) => [
                p.codigo,
                p.valor ?? sugeridos[p.codigo] ?? null,
            ]),
        ),
    });

    const casillas = preguntas.filter((p) => p.casilla);
    const sueltas = preguntas.filter((p) => !p.casilla);

    const errores: Record<string, string> = Object.fromEntries(
        Object.entries(form.errors)
            .filter(([clave]) => clave.startsWith('respuestas.'))
            .map(([clave, mensaje]) => [
                clave.slice('respuestas.'.length),
                mensaje as string,
            ]),
    );
    const listaErrores = Object.values(form.errors) as string[];

    const sinMarcar =
        casillas.length > 0 &&
        casillas.every((p) => form.data.respuestas[p.codigo] === null);
    const motivo = sinMarcar ? t('entrevista.faltaMarcar') : null;

    const cambiar = (cambios: Record<string, ValorEntrevista>) =>
        form.setData('respuestas', { ...form.data.respuestas, ...cambios });

    const guardar = () =>
        form.put(accion, {
            preserveScroll: true,
            onError: () => resumenRef.current?.focus(),
        });

    const titulo = persona
        ? paso === 'seguimiento'
            ? t('entrevista.persona.seguimiento', {
                  etiqueta: t('entrevista.personas.nombre', {
                      orden: persona.orden,
                  }),
              })
            : t('entrevista.personas.nombre', { orden: persona.orden })
        : paso === 'seguimiento'
          ? t('entrevista.seguimiento.titulo')
          : t(`entrevista.pantallas.${pantalla}`, { anio: taxYear });

    const descripcion =
        paso === 'seguimiento'
            ? t('entrevista.seguimiento.descripcion')
            : casillas.length > 0
              ? t('entrevista.marcaLasQueApliquen')
              : undefined;

    return (
        <>
            <Head title={titulo} />

            <MarcoEntrevista
                pantalla={pantalla}
                titulo={titulo}
                descripcion={descripcion}
                anterior={anterior}
                mensajes={mensajes}
                accion={
                    <>
                        <Button
                            type="submit"
                            form="paso-entrevista"
                            disabled={form.processing || sinMarcar}
                            aria-describedby={motivo ? motivoId : undefined}
                            className="w-full sm:w-auto"
                        >
                            {form.processing ? (
                                <Spinner aria-hidden="true" />
                            ) : null}
                            {form.processing
                                ? t('entrevista.guardando')
                                : t('entrevista.continuar')}
                        </Button>
                        {motivo ? (
                            <p
                                id={motivoId}
                                className="text-xs text-muted-foreground"
                            >
                                {motivo}
                            </p>
                        ) : null}
                    </>
                }
            >
                <form
                    id="paso-entrevista"
                    className="space-y-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        guardar();
                    }}
                >
                    {listaErrores.length > 0 ? (
                        <div
                            ref={resumenRef}
                            tabIndex={-1}
                            role="alert"
                            className="outline-none"
                        >
                            <AlertError
                                errors={listaErrores}
                                title={t('entrevista.revisaErrores')}
                            />
                        </div>
                    ) : null}

                    {preguntas.length === 0 ? (
                        <p className="rounded-lg border border-dashed border-border p-4 text-sm text-muted-foreground">
                            {t('entrevista.sinPreguntas')}
                        </p>
                    ) : null}

                    {sueltas.map((p) => (
                        <Pregunta
                            key={p.codigo}
                            pregunta={p}
                            valor={form.data.respuestas[p.codigo]}
                            conConyuge={conConyuge}
                            error={errores[p.codigo]}
                            onCambiar={(v) => cambiar({ [p.codigo]: v })}
                        />
                    ))}

                    {casillas.length > 0 ? (
                        <ListaCasillas
                            legend={titulo}
                            preguntas={casillas}
                            valores={form.data.respuestas}
                            conConyuge={conConyuge}
                            errores={errores}
                            onCambiar={cambiar}
                        />
                    ) : null}
                </form>

                {documentos.length > 0 ? (
                    <section
                        aria-labelledby="documentos-del-paso"
                        className="space-y-3"
                    >
                        <h2
                            id="documentos-del-paso"
                            className="text-base font-medium text-foreground"
                        >
                            {t('entrevista.documentos.enEstePaso')}
                        </h2>
                        <ul className="space-y-3">
                            {documentos.map((d) => (
                                <DocumentoFila
                                    key={`${d.codigo}-${d.persona_id ?? ''}`}
                                    documento={d}
                                />
                            ))}
                        </ul>
                    </section>
                ) : null}
            </MarcoEntrevista>
        </>
    );
}

function Pregunta({
    pregunta,
    valor,
    conConyuge,
    error,
    onCambiar,
}: {
    pregunta: PreguntaPaso;
    valor: ValorEntrevista;
    conConyuge: boolean;
    error?: string;
    onCambiar: (valor: ValorEntrevista) => void;
}) {
    switch (pregunta.tipo) {
        case 'si_no':
            return (
                <PreguntaSiNo
                    pregunta={pregunta}
                    valor={valor}
                    error={error}
                    onCambiar={onCambiar}
                />
            );
        case 'usted_conyuge_no':
            return (
                <PreguntaUstedConyuge
                    pregunta={pregunta}
                    valor={valor}
                    conConyuge={conConyuge}
                    error={error}
                    onCambiar={onCambiar}
                />
            );
        case 'opciones':
            return (
                <PreguntaOpciones
                    pregunta={pregunta}
                    valor={valor}
                    error={error}
                    onCambiar={onCambiar}
                />
            );
        default:
            return (
                <PreguntaDato
                    pregunta={pregunta}
                    valor={valor}
                    error={error}
                    onCambiar={onCambiar}
                />
            );
    }
}
