import { MessageCircle } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import type { PreguntaPaso } from '@/types/entrevista';

/**
 * "Respondiste por WhatsApp el 30 oct.": lo que llegó por otro canal se ve
 * junto a la pregunta, ya marcado, y se puede cambiar (9.6). Lo respondido
 * en el portal no lleva nota.
 */
export function OrigenRespuesta({ pregunta }: { pregunta: PreguntaPaso }) {
    const { t, i18n } = useTranslation();

    if (
        !pregunta.canal ||
        pregunta.canal === 'portal' ||
        !pregunta.respondida_en
    ) {
        return null;
    }

    const fecha = new Intl.DateTimeFormat(i18n.language, {
        day: 'numeric',
        month: 'short',
    }).format(new Date(pregunta.respondida_en));

    return (
        <p className="flex items-center gap-1 text-xs text-muted-foreground">
            <MessageCircle className="size-3.5" aria-hidden="true" />
            {t(`entrevista.origen.${pregunta.canal}`, { fecha })}
        </p>
    );
}
