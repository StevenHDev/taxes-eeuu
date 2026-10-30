import { Input } from '@/components/ui/input';
import type { PreguntaPaso, ValorEntrevista } from '@/types/entrevista';
import { PreguntaCampo } from './pregunta-campo';

/**
 * Texto, fecha o número. El valor sugerido (A1 y A2 desde la cuenta) se
 * muestra ya escrito para que el cliente lo confirme o lo corrija.
 */
export function PreguntaDato({
    pregunta,
    valor,
    error,
    onCambiar,
}: {
    pregunta: PreguntaPaso;
    valor: ValorEntrevista;
    error?: string;
    onCambiar: (valor: ValorEntrevista) => void;
}) {
    const tipo =
        pregunta.tipo === 'fecha'
            ? 'date'
            : pregunta.tipo === 'numero'
              ? 'number'
              : 'text';

    return (
        <PreguntaCampo pregunta={pregunta} error={error}>
            <Input
                id={`dato-${pregunta.codigo}`}
                type={tipo}
                inputMode={tipo === 'number' ? 'numeric' : undefined}
                min={tipo === 'number' ? 0 : undefined}
                aria-label={pregunta.texto}
                aria-invalid={Boolean(error)}
                autoComplete={pregunta.sensible ? 'off' : undefined}
                value={
                    valor === null || Array.isArray(valor) ? '' : String(valor)
                }
                onChange={(e) =>
                    onCambiar(e.target.value === '' ? null : e.target.value)
                }
            />
        </PreguntaCampo>
    );
}
