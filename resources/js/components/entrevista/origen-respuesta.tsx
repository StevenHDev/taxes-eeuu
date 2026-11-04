import { MessageCircle } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import type { PreguntaPaso, ValorEntrevista } from '@/types/entrevista';

/**
 * "Respondiste por WhatsApp el 30 oct.": lo que llegó por otro canal se ve
 * junto a la pregunta, ya marcado, y se puede cambiar (9.6). Lo respondido
 * en el portal no lleva nota.
 *
 * En una lista de casillas un "no" es una casilla sin marcar y se ve igual que
 * una pregunta sin responder: con `valor` la nota dice qué se respondió
 * ("Por WhatsApp respondiste «no»"), para que no parezca que falta.
 */
export function OrigenRespuesta({
    pregunta,
    valor,
}: {
    pregunta: PreguntaPaso;
    valor?: ValorEntrevista;
}) {
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

    const respuesta =
        pregunta.canal === 'whatsapp' && (valor === 'si' || valor === 'no')
            ? t(
                  valor === 'si'
                      ? 'entrevista.respuestaSi'
                      : 'entrevista.respuestaNo',
              )
            : null;

    return (
        <p className="flex items-center gap-1 text-xs text-muted-foreground">
            <MessageCircle className="size-3.5" aria-hidden="true" />
            {respuesta !== null
                ? t('entrevista.origenConRespuesta.whatsapp', {
                      respuesta,
                      fecha,
                  })
                : t(`entrevista.origen.${pregunta.canal}`, { fecha })}
        </p>
    );
}
