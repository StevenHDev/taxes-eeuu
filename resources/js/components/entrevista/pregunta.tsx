import type { PreguntaPaso, ValorEntrevista } from '@/types/entrevista';
import { PreguntaDato } from './pregunta-dato';
import { PreguntaOpciones } from './pregunta-opciones';
import { PreguntaSiNo } from './pregunta-si-no';
import { PreguntaUstedConyuge } from './pregunta-usted-conyuge';

/**
 * El control de una pregunta según su tipo. Lo usan el paso del portal y la
 * corrección del preparador (2.9), para que la pregunta se vea igual.
 */
export function Pregunta({
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
