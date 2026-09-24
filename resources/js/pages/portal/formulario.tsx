import { Head, router, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    Check,
    Circle,
    FileDown,
    MinusCircle,
    Plus,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { FieldValue } from '@/components/field-value';
import { PortalChatPanel } from '@/components/portal-chat-panel';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { humanizarClave } from '@/lib/humanizar-clave';
import { store as guardarCampo } from '@/routes/portal/formulario/campos';
import type { PortalMensaje } from '@/types/agente';
import type { CampoCliente, CampoPendiente } from '@/types/tax-event';

const TRANSVERSAL = 'transversal';
const DOCUMENTOS_EXTRA = 'documentos_extra';

type Estado = CampoCliente['estado'] | 'pendiente';

// Mismo criterio visual que mi-informacion.tsx / clientes/show.tsx: un riel
// de color al borde para leer el estado de reojo.
const ESTADO_RIEL: Record<Estado, string> = {
    pendiente: 'border-l-state-pendiente',
    recibido: 'border-l-state-recibido',
    invalido: 'border-l-state-invalido',
    no_aplica: 'border-l-state-no-aplica',
};

const ESTADO_TINTA: Record<Estado, string> = {
    pendiente: 'text-muted-foreground',
    recibido: 'text-foreground',
    invalido: 'text-destructive',
    no_aplica: 'text-muted-foreground',
};

const ESTADO_ICONO: Record<Estado, typeof Circle> = {
    pendiente: Circle,
    recibido: Check,
    invalido: AlertTriangle,
    no_aplica: MinusCircle,
};

function EstadoTag({ estado }: { estado: Estado }) {
    const { t } = useTranslation();
    const Icon = ESTADO_ICONO[estado];

    return (
        <span
            className={`inline-flex items-center gap-1 text-xs font-medium ${ESTADO_TINTA[estado]}`}
        >
            <Icon className="size-3" />
            {t(`clienteShow.fieldState.${estado}`)}
        </span>
    );
}

type FilaCampo = {
    forma: string;
    campo: string;
    tipoCampo: 'documento' | 'dato' | 'mixto';
    tipoDato: CampoPendiente['tipo_dato'];
    subcampos: string[] | null;
    formatosAceptados: string[] | null;
    obligatorio: boolean;
    estado: Estado;
    valorActual: unknown;
    documento: CampoCliente['documento'];
    // Posición estable en el catálogo — se ordena por esto, no por si el
    // campo vino de `pendientes` o de `respondidos`, para que guardar un dato
    // no lo salte al final de la lista (ver PortalFormularioController).
    orden: number;
    // Grupo de baja frecuencia (ej. "Créditos menos comunes") — null si es
    // un campo obligatorio o un opcional que se muestra suelto.
    grupo: string | null;
};

export default function PortalFormulario({
    taxYear,
    formas,
    pendientes,
    respondidos,
    mensajesChat,
}: {
    taxYear: number;
    formas: { forma: string; label: string }[];
    pendientes: CampoPendiente[];
    respondidos: CampoCliente[];
    mensajesChat: PortalMensaje[];
}) {
    const { t } = useTranslation();

    const filas = new Map<string, FilaCampo>();

    for (const p of pendientes) {
        filas.set(`${p.forma}|${p.campo}`, {
            forma: p.forma,
            campo: p.campo,
            tipoCampo: p.tipo_campo,
            tipoDato: p.tipo_dato,
            subcampos: p.subcampos,
            formatosAceptados: p.formatos_aceptados,
            obligatorio: p.obligatorio,
            estado: 'pendiente',
            valorActual: null,
            documento: null,
            orden: p.orden ?? Number.MAX_SAFE_INTEGER,
            grupo: p.grupo ?? null,
        });
    }

    for (const r of respondidos) {
        filas.set(`${r.forma}|${r.campo}`, {
            forma: r.forma,
            campo: r.campo,
            tipoCampo: r.tipo_campo,
            tipoDato: r.tipo_dato,
            subcampos: r.subcampos,
            formatosAceptados: r.formatos_aceptados,
            obligatorio: r.obligatorio,
            estado: r.estado,
            valorActual: r.valor,
            documento: r.documento,
            orden: r.orden ?? Number.MAX_SAFE_INTEGER,
            grupo: r.grupo ?? null,
        });
    }

    const porForma = new Map<string, FilaCampo[]>();

    for (const fila of filas.values()) {
        const lista = porForma.get(fila.forma);

        if (lista) {
            lista.push(fila);
        } else {
            porForma.set(fila.forma, [fila]);
        }
    }

    for (const lista of porForma.values()) {
        lista.sort((a, b) => a.orden - b.orden);
    }

    const formaLabel = (forma: string): string => {
        if (forma === TRANSVERSAL) {
            return t('clienteShow.transversalLabel');
        }

        if (forma === DOCUMENTOS_EXTRA) {
            return t('clienteShow.documentosExtraLabel');
        }

        return formas.find((f) => f.forma === forma)?.label ?? forma;
    };

    const secciones = [
        TRANSVERSAL,
        DOCUMENTOS_EXTRA,
        ...formas.map((f) => f.forma),
    ].filter((forma) => porForma.has(forma));

    const totalPendientesObligatorios = pendientes.filter(
        (p) => p.obligatorio,
    ).length;

    return (
        <>
            <Head title={t('portalFormulario.pageTitle')} />

            {/* Sin max-w/mx-auto a propósito: mismo criterio que dashboard.tsx
                y api-docs.tsx (los únicos dos que ya sabíamos se ven "bien
                anchos") — llenan el 100% del ancho que da SidebarInset, sin
                capar nada. */}
            <div className="grid gap-6 p-4 lg:grid-cols-[1fr_420px] lg:p-8">
                {/* max-w-2xl solo acá adentro (la grilla de la página sigue
                    a ancho completo): un input de texto angosto estirado a
                    1500px se ve peor que uno con un ancho de lectura normal —
                    ver clientes/show.tsx, mismo criterio en sus <dl>. */}
                <div className="max-w-2xl min-w-0 space-y-8">
                    <div>
                        <h1 className="font-display text-display text-foreground">
                            {t('portalFormulario.pageTitle')}
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {totalPendientesObligatorios > 0
                                ? t('portalFormulario.subtitlePendientes', {
                                      count: totalPendientesObligatorios,
                                  })
                                : t('portalFormulario.subtitleCompleto')}
                        </p>
                    </div>

                    {secciones.map((forma) => {
                        const todas = porForma.get(forma)!;
                        const obligatorios = todas.filter((f) => f.obligatorio);
                        const opcionales = todas.filter((f) => !f.obligatorio);
                        const opcionalesSueltos = opcionales.filter(
                            (f) => !f.grupo,
                        );
                        const grupos = new Map<string, FilaCampo[]>();

                        for (const f of opcionales) {
                            if (!f.grupo) {
                                continue;
                            }

                            const lista = grupos.get(f.grupo);

                            if (lista) {
                                lista.push(f);
                            } else {
                                grupos.set(f.grupo, [f]);
                            }
                        }

                        return (
                            <section key={forma}>
                                <h2 className="mb-3 text-title font-semibold text-foreground">
                                    {formaLabel(forma)}
                                </h2>
                                <div className="space-y-3">
                                    {obligatorios.map((fila) => (
                                        <CampoFormField
                                            key={`${fila.forma}|${fila.campo}`}
                                            fila={fila}
                                            taxYear={taxYear}
                                        />
                                    ))}
                                </div>

                                {(opcionalesSueltos.length > 0 ||
                                    grupos.size > 0) && (
                                    <div className="mt-6">
                                        <p className="text-micro text-muted-foreground uppercase">
                                            {t(
                                                'portalFormulario.opcionalesTitle',
                                            )}
                                        </p>
                                        <div className="mt-2 space-y-3">
                                            {opcionalesSueltos.map((fila) => (
                                                <CampoFormField
                                                    key={`${fila.forma}|${fila.campo}`}
                                                    fila={fila}
                                                    taxYear={taxYear}
                                                />
                                            ))}

                                            {[...grupos.entries()].map(
                                                ([etiqueta, miembros]) => (
                                                    <details
                                                        key={etiqueta}
                                                        className="rounded-lg border"
                                                    >
                                                        <summary className="cursor-pointer px-3 py-2 text-sm font-medium text-foreground">
                                                            {etiqueta}{' '}
                                                            <span className="font-normal text-muted-foreground">
                                                                (
                                                                {
                                                                    miembros.length
                                                                }
                                                                )
                                                            </span>
                                                        </summary>
                                                        <div className="space-y-3 border-t p-3">
                                                            {miembros.map(
                                                                (fila) => (
                                                                    <CampoFormField
                                                                        key={`${fila.forma}|${fila.campo}`}
                                                                        fila={
                                                                            fila
                                                                        }
                                                                        taxYear={
                                                                            taxYear
                                                                        }
                                                                    />
                                                                ),
                                                            )}
                                                        </div>
                                                    </details>
                                                ),
                                            )}
                                        </div>
                                    </div>
                                )}
                            </section>
                        );
                    })}
                </div>

                {/* Mismo descuento de 4rem que portal/chat.tsx (la barra fija
                    de AppSidebarHeader) — sin esto, el panel se pasaba del
                    fondo de la pantalla y el cuadro de texto quedaba fuera de
                    vista hasta hacer scroll. */}
                <div className="lg:sticky lg:top-4 lg:h-[calc(100svh-4rem-2rem)]">
                    <h2 className="mb-2 font-display text-sm text-muted-foreground">
                        {t('portalFormulario.dudasTitle')}
                    </h2>
                    <div className="h-[500px] lg:h-[calc(100%-2rem)]">
                        <PortalChatPanel mensajes={mensajesChat} />
                    </div>
                </div>
            </div>
        </>
    );
}

function CampoFormField({
    fila,
    taxYear,
}: {
    fila: FilaCampo;
    taxYear: number;
}) {
    const { t } = useTranslation();
    const [editando, setEditando] = useState(fila.estado === 'pendiente');

    if (!editando) {
        return (
            <div className={`border-l-2 py-3 pl-3 ${ESTADO_RIEL[fila.estado]}`}>
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <span className="text-sm font-medium text-foreground">
                        {humanizarClave(fila.campo)}
                    </span>
                    <EstadoTag estado={fila.estado} />
                </div>

                {fila.estado === 'recibido' && (
                    <div className="mt-1.5 flex items-center justify-between gap-2 text-sm">
                        {fila.documento ? (
                            <a
                                href={fila.documento.download_url}
                                className="inline-flex items-center gap-1.5 text-primary hover:underline"
                            >
                                <FileDown className="size-3.5" />
                                {fila.documento.file_original_name}
                            </a>
                        ) : (
                            <FieldValue value={fila.valorActual} />
                        )}
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => setEditando(true)}
                        >
                            {t('common.edit')}
                        </Button>
                    </div>
                )}

                {fila.estado === 'no_aplica' && (
                    <div className="mt-1 flex items-center justify-between">
                        <p className="text-xs text-muted-foreground">
                            {t('clienteShow.fieldState.no_aplica')}
                        </p>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => setEditando(true)}
                        >
                            {t('common.edit')}
                        </Button>
                    </div>
                )}

                {fila.estado === 'invalido' && (
                    <div className="mt-1 flex items-center justify-between">
                        <p className="text-xs text-destructive">
                            {t('portalFormulario.invalido')}
                        </p>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => setEditando(true)}
                        >
                            {t('common.edit')}
                        </Button>
                    </div>
                )}
            </div>
        );
    }

    return (
        <CampoInputForm
            fila={fila}
            taxYear={taxYear}
            onSaved={() => setEditando(false)}
            onCancel={
                fila.estado !== 'pendiente'
                    ? () => setEditando(false)
                    : undefined
            }
        />
    );
}

type CampoValor =
    string | string[] | Record<string, string> | Record<string, string>[];

type CampoFormData = {
    forma: string;
    tax_year: number;
    campo: string;
    tipo_campo: string;
    modo: string;
    tipo_dato: string | null;
    contenido: CampoValor;
    archivo: File | null;
};

function CampoInputForm({
    fila,
    taxYear,
    onSaved,
    onCancel,
}: {
    fila: FilaCampo;
    taxYear: number;
    onSaved: () => void;
    onCancel?: () => void;
}) {
    const { t } = useTranslation();
    const esDocumento = fila.tipoCampo === 'documento';
    const esMixto = fila.tipoCampo === 'mixto';

    const form = useForm<CampoFormData>({
        forma: fila.forma,
        tax_year: taxYear,
        campo: fila.campo,
        tipo_campo: fila.tipoCampo,
        modo: esDocumento ? 'archivo' : 'texto',
        tipo_dato: fila.tipoDato,
        contenido:
            fila.tipoDato === 'array_string'
                ? ['']
                : fila.tipoDato === 'array_object'
                  ? []
                  : fila.tipoDato === 'object'
                    ? {}
                    : '',
        archivo: null,
    });

    const enviar = () => {
        form.post(guardarCampo.url(), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: onSaved,
        });
    };

    const marcarNoAplica = () => {
        router.post(
            guardarCampo.url(),
            {
                forma: fila.forma,
                tax_year: taxYear,
                campo: fila.campo,
                tipo_campo: fila.tipoCampo,
                modo: 'no_aplica',
            },
            { preserveScroll: true, onSuccess: onSaved },
        );
    };

    return (
        <div className="rounded-lg border p-3">
            <div className="mb-2 flex items-center justify-between">
                <span className="text-sm font-medium text-foreground">
                    {humanizarClave(fila.campo)}
                    {fila.obligatorio && (
                        <span className="ml-1 text-destructive">*</span>
                    )}
                </span>
                {onCancel && (
                    <Button variant="ghost" size="sm" onClick={onCancel}>
                        {t('common.cancel')}
                    </Button>
                )}
            </div>

            <div className="space-y-2">
                {(esDocumento || esMixto) && (
                    <input
                        type="file"
                        accept={
                            fila.formatosAceptados
                                ? fila.formatosAceptados
                                      .map((f) => `.${f}`)
                                      .join(',')
                                : undefined
                        }
                        onChange={(e) => {
                            const archivo = e.target.files?.[0] ?? null;
                            form.setData('archivo', archivo);
                            form.setData('modo', archivo ? 'archivo' : 'texto');
                        }}
                        className="block w-full text-sm text-muted-foreground file:mr-3 file:rounded-md file:border file:bg-background file:px-3 file:py-1.5 file:text-sm"
                    />
                )}

                {!esDocumento && <CampoValorInput fila={fila} form={form} />}

                <div className="flex items-center gap-2">
                    <Button
                        type="button"
                        size="sm"
                        onClick={enviar}
                        disabled={form.processing}
                    >
                        {form.processing
                            ? t('portalFormulario.guardando')
                            : t('common.save')}
                    </Button>

                    {!fila.obligatorio && (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={marcarNoAplica}
                            disabled={form.processing}
                        >
                            {t('portalFormulario.noAplica')}
                        </Button>
                    )}
                </div>

                {Object.values(form.errors).map((error, i) => (
                    <p key={i} className="text-sm text-destructive">
                        {error}
                    </p>
                ))}
            </div>
        </div>
    );
}

/**
 * El input del valor en sí, para el caso "dato" (no documento) — separado de
 * CampoInputForm para que el switch por tipo_dato no infle esa función.
 */
function CampoValorInput({
    fila,
    form,
}: {
    fila: FilaCampo;
    form: ReturnType<typeof useForm<CampoFormData>>;
}) {
    const { t } = useTranslation();

    if (fila.tipoDato === 'number') {
        return (
            <Input
                type="number"
                value={(form.data.contenido as string) ?? ''}
                onChange={(e) => form.setData('contenido', e.target.value)}
            />
        );
    }

    if (fila.tipoDato === 'object') {
        const valor = (form.data.contenido as Record<string, string>) ?? {};

        return (
            <div className="grid gap-2 sm:grid-cols-2">
                {(fila.subcampos ?? []).map((subcampo) => (
                    <div key={subcampo}>
                        <label className="mb-1 block text-xs text-muted-foreground">
                            {humanizarClave(subcampo)}
                        </label>
                        <Input
                            value={valor[subcampo] ?? ''}
                            onChange={(e) =>
                                form.setData('contenido', {
                                    ...valor,
                                    [subcampo]: e.target.value,
                                })
                            }
                        />
                    </div>
                ))}
            </div>
        );
    }

    if (fila.tipoDato === 'array_string') {
        const valores = (form.data.contenido as string[]) ?? [''];

        return (
            <div className="space-y-2">
                {valores.map((valor, i) => (
                    <div key={i} className="flex gap-2">
                        <Input
                            value={valor}
                            onChange={(e) => {
                                const copia = [...valores];
                                copia[i] = e.target.value;
                                form.setData('contenido', copia);
                            }}
                        />
                        {valores.length > 1 && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                onClick={() =>
                                    form.setData(
                                        'contenido',
                                        valores.filter((_, j) => j !== i),
                                    )
                                }
                            >
                                <Trash2 className="size-4" />
                            </Button>
                        )}
                    </div>
                ))}
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => form.setData('contenido', [...valores, ''])}
                >
                    <Plus className="size-3.5" />
                    {t('common.add')}
                </Button>
            </div>
        );
    }

    if (fila.tipoDato === 'array_object') {
        const filas = (form.data.contenido as Record<string, string>[]) ?? [];
        const vacio = Object.fromEntries(
            (fila.subcampos ?? []).map((s) => [s, '']),
        );

        return (
            <div className="space-y-3">
                {filas.map((item, i) => (
                    <div key={i} className="rounded border p-2">
                        <div className="mb-1 flex justify-end">
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                onClick={() =>
                                    form.setData(
                                        'contenido',
                                        filas.filter((_, j) => j !== i),
                                    )
                                }
                            >
                                <Trash2 className="size-4" />
                            </Button>
                        </div>
                        <div className="grid gap-2 sm:grid-cols-2">
                            {(fila.subcampos ?? []).map((subcampo) => (
                                <div key={subcampo}>
                                    <label className="mb-1 block text-xs text-muted-foreground">
                                        {humanizarClave(subcampo)}
                                    </label>
                                    <Input
                                        value={item[subcampo] ?? ''}
                                        onChange={(e) => {
                                            const copia = [...filas];
                                            copia[i] = {
                                                ...copia[i],
                                                [subcampo]: e.target.value,
                                            };
                                            form.setData('contenido', copia);
                                        }}
                                    />
                                </div>
                            ))}
                        </div>
                    </div>
                ))}
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => form.setData('contenido', [...filas, vacio])}
                >
                    <Plus className="size-3.5" />
                    {t('common.add')}
                </Button>
            </div>
        );
    }

    return (
        <Input
            value={(form.data.contenido as string) ?? ''}
            onChange={(e) => form.setData('contenido', e.target.value)}
        />
    );
}
