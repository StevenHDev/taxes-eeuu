import type { TFunction } from 'i18next';
import { Eye, Pencil } from 'lucide-react';
import { useRef, useState } from 'react';
import type { KeyboardEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { show as confirmPasswordShow } from '@/actions/Laravel/Fortify/Http/Controllers/ConfirmablePasswordController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { formatearConfianza } from '@/components/visor/confianza-panel';
import { revelar as rutaRevelar } from '@/routes/clientes/documentos/visor';
import type { DatoLeido, ValorRevelado } from '@/types/lectura';

function csrf(): string {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

/**
 * Los datos leídos, uno por fila. Con el foco en la lista, las flechas
 * arriba y abajo pasan de un dato al siguiente y lo eligen. Lo sensible se
 * revela dato por dato (contraseña reciente) y el valor vive solo en
 * memoria: nunca en el almacenamiento del navegador ni en la URL.
 */
export function ListaDatos({
    clienteId,
    itemId,
    datos,
    umbral,
    seleccionadoId,
    revelados,
    onSeleccionar,
    onRevelado,
    onCorregir,
}: {
    clienteId: number;
    itemId: number;
    datos: DatoLeido[];
    umbral: number;
    seleccionadoId: number | null;
    revelados: Record<number, ValorRevelado>;
    onSeleccionar: (id: number) => void;
    onRevelado: (id: number, revelado: ValorRevelado) => void;
    onCorregir: (dato: DatoLeido) => void;
}) {
    const lista = useRef<HTMLUListElement>(null);

    const teclas = (e: KeyboardEvent<HTMLUListElement>) => {
        if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') {
            return;
        }

        const botones = Array.from(
            lista.current?.querySelectorAll<HTMLButtonElement>(
                'button[data-dato]',
            ) ?? [],
        );
        const actual = botones.findIndex((b) => b === document.activeElement);

        if (actual === -1) {
            return;
        }

        e.preventDefault();

        const destino =
            botones[
                Math.min(
                    Math.max(actual + (e.key === 'ArrowDown' ? 1 : -1), 0),
                    botones.length - 1,
                )
            ];

        destino.focus();
        onSeleccionar(Number(destino.dataset.dato));
    };

    return (
        <ul
            ref={lista}
            onKeyDown={teclas}
            className="divide-y rounded-lg border"
        >
            {datos.map((d) => (
                <DatoFila
                    key={d.id}
                    clienteId={clienteId}
                    itemId={itemId}
                    dato={d}
                    umbral={umbral}
                    seleccionado={d.id === seleccionadoId}
                    revelado={revelados[d.id]}
                    onSeleccionar={() => onSeleccionar(d.id)}
                    onRevelado={(r) => onRevelado(d.id, r)}
                    onCorregir={() => onCorregir(d)}
                />
            ))}
        </ul>
    );
}

function DatoFila({
    clienteId,
    itemId,
    dato: d,
    umbral,
    seleccionado,
    revelado,
    onSeleccionar,
    onRevelado,
    onCorregir,
}: {
    clienteId: number;
    itemId: number;
    dato: DatoLeido;
    umbral: number;
    seleccionado: boolean;
    revelado: ValorRevelado | undefined;
    onSeleccionar: () => void;
    onRevelado: (revelado: ValorRevelado) => void;
    onCorregir: () => void;
}) {
    const { t, i18n } = useTranslation();
    const [revelando, setRevelando] = useState(false);
    const [pideContrasena, setPideContrasena] = useState(false);
    const [verMotivo, setVerMotivo] = useState(false);

    const oculto = d.sensible && d.tiene_valor && revelado === undefined;
    const valor = d.sensible ? (revelado?.valor ?? null) : d.valor;
    const baja = d.confianza !== null && d.confianza < umbral;
    const idMotivo = `dato-${d.id}-motivo`;

    const revelar = async () => {
        setRevelando(true);

        try {
            const respuesta = await fetch(
                rutaRevelar({ cliente: clienteId, item: itemId, dato: d.id })
                    .url,
                {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrf(),
                    },
                },
            );

            if (respuesta.status === 423) {
                setPideContrasena(true);

                return;
            }

            if (!respuesta.ok) {
                toast.error(t('visor.revelarError'));

                return;
            }

            setPideContrasena(false);
            onRevelado(await respuesta.json());
        } catch {
            toast.error(t('visor.revelarError'));
        } finally {
            setRevelando(false);
        }
    };

    let contenido;

    if (oculto) {
        contenido = (
            <span className="text-muted-foreground">{t('visor.oculto')}</span>
        );
    } else if (valor) {
        contenido = <span className="font-medium break-all">{valor}</span>;
    } else {
        contenido = (
            <span className="text-amber-700 dark:text-amber-400">
                {t('visor.sinValor')}
            </span>
        );
    }

    return (
        <li
            className={
                seleccionado
                    ? 'space-y-2 bg-accent/60 p-3'
                    : 'space-y-2 p-3 transition-colors hover:bg-accent/30'
            }
        >
            <button
                type="button"
                data-dato={d.id}
                aria-pressed={seleccionado}
                onClick={onSeleccionar}
                className="flex w-full flex-col items-start gap-0.5 rounded-md text-left outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
            >
                <span className="text-xs text-muted-foreground">
                    {d.casilla} · {d.descripcion}
                </span>
                <span className="text-sm">{contenido}</span>
            </button>
            <div className="flex flex-wrap items-center gap-1.5">
                {d.confianza !== null ? (
                    <Badge
                        variant="outline"
                        className={
                            baja
                                ? 'border-amber-500/60 text-amber-700 dark:text-amber-400'
                                : undefined
                        }
                    >
                        {t('visor.confianzaValor', {
                            valor: formatearConfianza(
                                d.confianza,
                                i18n.language,
                            ),
                        })}
                    </Badge>
                ) : null}
                {d.motivo_confianza ? (
                    <button
                        type="button"
                        aria-expanded={verMotivo}
                        aria-controls={idMotivo}
                        onClick={() => setVerMotivo((v) => !v)}
                        className="rounded-md outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    >
                        <Badge
                            variant="secondary"
                            className="cursor-pointer hover:bg-secondary/70"
                        >
                            {d.motivo_confianza.titulo}
                        </Badge>
                    </button>
                ) : null}
                {d.origen === 'preparador' ? (
                    <Badge variant="outline" className="font-normal">
                        {d.corregido_por
                            ? t('visor.corregidoPor', {
                                  nombre: d.corregido_por,
                              })
                            : t('visor.corregido')}
                    </Badge>
                ) : null}
                <div className="ml-auto flex items-center gap-1">
                    {oculto ? (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={revelar}
                            disabled={revelando}
                            aria-label={t('visor.revelarAria', {
                                casilla: d.casilla,
                            })}
                        >
                            {revelando ? (
                                <Spinner aria-hidden="true" />
                            ) : (
                                <Eye aria-hidden="true" />
                            )}
                            {t('visor.revelar')}
                        </Button>
                    ) : null}
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={onCorregir}
                        aria-label={t('visor.corregirAria', {
                            casilla: d.casilla,
                        })}
                    >
                        <Pencil aria-hidden="true" />
                        {t('visor.corregirBoton')}
                    </Button>
                </div>
            </div>
            {pideContrasena ? (
                <a
                    href={confirmPasswordShow().url}
                    className="text-xs text-amber-700 underline dark:text-amber-400"
                >
                    {t('visor.confirmarContrasena')}
                </a>
            ) : null}
            {d.motivo_confianza && verMotivo ? (
                <p
                    id={idMotivo}
                    className="rounded-md bg-muted p-2 text-xs text-muted-foreground"
                >
                    {d.motivo_confianza.explicacion}
                </p>
            ) : null}
            {d.motivo ? (
                <p className="text-xs text-muted-foreground">
                    {t('visor.motivoCorreccion', { texto: d.motivo })}
                </p>
            ) : null}
            {d.excepciones.length > 0 ? (
                <ul className="space-y-1">
                    {d.excepciones.map((e) => (
                        <li
                            key={e.id}
                            className="flex items-start gap-1.5 text-xs text-amber-700 dark:text-amber-400"
                        >
                            <Badge
                                variant="outline"
                                className="border-amber-500/60 font-normal"
                            >
                                {e.regla_codigo}
                            </Badge>
                            <span>{textoDeLaExcepcion(e, t)}</span>
                        </li>
                    ))}
                </ul>
            ) : null}
        </li>
    );
}

/**
 * Lo que dice la excepción junto al dato. El servidor manda un `detalle` con
 * datos de la regla (nunca el valor leído): si trae un `problema` en palabras
 * se muestra; si no, solo que la regla está pendiente.
 */
function textoDeLaExcepcion(
    excepcion: {
        regla_codigo: string;
        detalle: Record<string, unknown> | null;
    },
    t: TFunction,
): string {
    const problema = excepcion.detalle?.problema;

    return typeof problema === 'string'
        ? problema
        : t('visor.reglaPendiente', { regla: excepcion.regla_codigo });
}
