import { useTranslation } from 'react-i18next';
import type { PreguntaPaso, ValorEntrevista } from '@/types/entrevista';
import { PreguntaOpciones } from './pregunta-opciones';

export function PreguntaSiNo(props: {
    pregunta: PreguntaPaso;
    valor: ValorEntrevista;
    error?: string;
    onCambiar: (valor: ValorEntrevista) => void;
}) {
    const { t } = useTranslation();

    return (
        <PreguntaOpciones
            {...props}
            opciones={[
                { valor: 'si', texto: t('entrevista.si') },
                { valor: 'no', texto: t('entrevista.no') },
            ]}
        />
    );
}
