import type { PreguntaPaso, ValorEntrevista } from '@/types/entrevista';
import { PreguntaCampo } from './pregunta-campo';

type Opcion = { valor: string; texto: string };

/**
 * Una sola opción de una lista (radios como filas completas tocables). Lo
 * usan las preguntas de opciones y las de sí o no.
 */
export function PreguntaOpciones({
    pregunta,
    opciones = pregunta.opciones,
    valor,
    error,
    onCambiar,
}: {
    pregunta: PreguntaPaso;
    opciones?: Opcion[];
    valor: ValorEntrevista;
    error?: string;
    onCambiar: (valor: ValorEntrevista) => void;
}) {
    return (
        <PreguntaCampo pregunta={pregunta} error={error}>
            <div className="grid gap-2">
                {opciones.map((o) => {
                    const id = `opcion-${pregunta.codigo}-${o.valor}`;

                    return (
                        <label
                            key={o.valor}
                            htmlFor={id}
                            className="flex min-h-11 cursor-pointer items-center gap-3 rounded-md border border-border px-3 text-sm hover:bg-accent has-[:checked]:border-primary has-[:checked]:bg-primary/5 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-ring/50"
                        >
                            <input
                                id={id}
                                type="radio"
                                name={`respuestas.${pregunta.codigo}`}
                                value={o.valor}
                                checked={valor === o.valor}
                                onChange={() => onCambiar(o.valor)}
                                aria-invalid={Boolean(error)}
                                className="size-4 accent-primary"
                            />
                            {o.texto}
                        </label>
                    );
                })}
            </div>
        </PreguntaCampo>
    );
}
