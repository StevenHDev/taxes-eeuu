import { useId } from 'react';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import type { PreguntaPaso, ValorEntrevista } from '@/types/entrevista';
import { OrigenRespuesta } from './origen-respuesta';

type Props = {
    legend: string;
    preguntas: PreguntaPaso[];
    valores: Record<string, ValorEntrevista>;
    conConyuge: boolean;
    errores: Record<string, string>;
    onCambiar: (cambios: Record<string, ValorEntrevista>) => void;
};

type Quien = 'usted' | 'conyuge';

const marcada = (valor: ValorEntrevista, quien: Quien | null) =>
    quien === null
        ? valor === 'si' || (Array.isArray(valor) && valor.length > 0)
        : Array.isArray(valor) && valor.includes(quien);

/**
 * Casilla con su etiqueta al lado (no adentro): si la casilla va dentro del
 * `label`, un toque justo sobre el cuadrito la cambia dos veces y no cambia
 * nada. La etiqueta ocupa el resto de la fila, así toda la fila responde.
 */
function FilaCasilla({
    id,
    checked,
    onChange,
    invalido,
    children,
    className = '',
}: {
    id: string;
    checked: boolean;
    onChange: (activo: boolean) => void;
    invalido?: boolean;
    children: React.ReactNode;
    className?: string;
}) {
    return (
        <div className={`flex items-start gap-3 ${className}`}>
            <Checkbox
                id={id}
                className="mt-3.5"
                checked={checked}
                onCheckedChange={(v) => onChange(v === true)}
                aria-invalid={invalido}
            />
            <Label
                htmlFor={id}
                className="flex min-h-11 flex-1 cursor-pointer flex-col items-start justify-center gap-1 py-2 font-normal"
            >
                {children}
            </Label>
        </div>
    );
}

/**
 * Una pantalla de casillas con "Ninguna" (pantallas 3, 5, 6 y 7): marcar es
 * "sí" (o "yo" / "mi cónyuge"), no marcar es "no" al guardar. "Ninguna" se
 * resuelve en el clic: deja todo en "no".
 */
export function ListaCasillas({
    legend,
    preguntas,
    valores,
    conConyuge,
    errores,
    onCambiar,
}: Props) {
    const { t } = useTranslation();
    const ningunaId = useId();

    const ninguna = preguntas.every((p) => valores[p.codigo] === 'no');

    const cambiar = (
        pregunta: PreguntaPaso,
        quien: Quien | null,
        activo: boolean,
    ) => {
        if (pregunta.tipo === 'si_no') {
            onCambiar({ [pregunta.codigo]: activo ? 'si' : 'no' });

            return;
        }

        const actual = valores[pregunta.codigo];
        const lista = new Set(Array.isArray(actual) ? actual : []);
        const persona = quien ?? 'usted';

        if (activo) {
            lista.add(persona);
        } else {
            lista.delete(persona);
        }

        const orden = (['usted', 'conyuge'] as const).filter((q) =>
            lista.has(q),
        );
        onCambiar({ [pregunta.codigo]: orden.length > 0 ? orden : 'no' });
    };

    const marcarNinguna = (activo: boolean) =>
        onCambiar(
            Object.fromEntries(
                preguntas.map((p) => [p.codigo, activo ? 'no' : null]),
            ),
        );

    return (
        <fieldset className="space-y-2">
            <legend className="sr-only">{legend}</legend>

            {preguntas.map((p) => {
                const id = `casilla-${p.codigo}`;
                const dosColumnas = p.tipo === 'usted_conyuge_no' && conConyuge;

                return (
                    <div
                        key={p.codigo}
                        className="rounded-lg border border-border bg-card px-3 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-ring/50"
                    >
                        {dosColumnas ? (
                            <div className="space-y-2 py-3">
                                <p
                                    id={`${id}-texto`}
                                    className="text-sm font-medium"
                                >
                                    {p.texto}
                                </p>
                                <div
                                    role="group"
                                    aria-labelledby={`${id}-texto`}
                                    className="grid gap-2 sm:grid-cols-2"
                                >
                                    {(['usted', 'conyuge'] as const).map(
                                        (quien) => (
                                            <FilaCasilla
                                                key={quien}
                                                id={`${id}-${quien}`}
                                                checked={marcada(
                                                    valores[p.codigo],
                                                    quien,
                                                )}
                                                onChange={(activo) =>
                                                    cambiar(p, quien, activo)
                                                }
                                                className="rounded-md border border-border px-3 hover:bg-accent"
                                            >
                                                {t(`entrevista.quien.${quien}`)}
                                            </FilaCasilla>
                                        ),
                                    )}
                                </div>
                            </div>
                        ) : (
                            <FilaCasilla
                                id={id}
                                checked={marcada(valores[p.codigo], null)}
                                onChange={(activo) => cambiar(p, null, activo)}
                                invalido={Boolean(errores[p.codigo])}
                            >
                                <span className="text-sm leading-snug">
                                    {p.texto}
                                </span>
                                {p.ayuda ? (
                                    <span className="text-xs text-muted-foreground">
                                        {p.ayuda}
                                    </span>
                                ) : null}
                            </FilaCasilla>
                        )}
                        <OrigenRespuesta pregunta={p} />
                        <InputError message={errores[p.codigo]} />
                    </div>
                );
            })}

            <FilaCasilla
                id={ningunaId}
                checked={ninguna}
                onChange={marcarNinguna}
                className="rounded-lg border border-dashed border-border px-3 hover:bg-accent"
            >
                {t('entrevista.ninguna')}
            </FilaCasilla>
        </fieldset>
    );
}
