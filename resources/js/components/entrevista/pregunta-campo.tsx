import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import type { PreguntaPaso } from '@/types/entrevista';
import { OrigenRespuesta } from './origen-respuesta';

/**
 * Marco común de una pregunta que no va en la lista de casillas: la
 * pregunta como `legend`, la ayuda debajo, el control, el canal y el error.
 */
export function PreguntaCampo({
    pregunta,
    error,
    children,
}: {
    pregunta: PreguntaPaso;
    error?: string;
    children: ReactNode;
}) {
    return (
        <fieldset
            className="space-y-3 rounded-lg border border-border bg-card p-4"
            aria-describedby={error ? `error-${pregunta.codigo}` : undefined}
        >
            <legend className="sr-only">{pregunta.texto}</legend>
            <div aria-hidden="true" className="space-y-1">
                <p className="text-base font-medium text-foreground">
                    {pregunta.texto}
                </p>
                {pregunta.ayuda ? (
                    <p className="text-sm text-muted-foreground">
                        {pregunta.ayuda}
                    </p>
                ) : null}
            </div>
            {children}
            <OrigenRespuesta pregunta={pregunta} />
            <InputError id={`error-${pregunta.codigo}`} message={error} />
        </fieldset>
    );
}
