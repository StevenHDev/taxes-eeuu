import { useTranslation } from 'react-i18next';
import type { RespuestaCliente } from '@/types/revision';

/** Lo que respondió el cliente, en palabras, al lado de la decisión. */
export function RespuestasCliente({
    respuestas,
}: {
    respuestas: RespuestaCliente[];
}) {
    const { t } = useTranslation();

    return (
        <div className="space-y-1.5">
            <p className="text-sm font-medium">
                {t('revision.respondioCliente')}
            </p>
            {respuestas.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    {t('revision.sinRespuestas')}
                </p>
            ) : (
                <ul className="space-y-1 text-sm">
                    {respuestas.map((r) => (
                        <li key={r.codigo} className="break-words">
                            <span className="text-muted-foreground">
                                {r.texto}:
                            </span>{' '}
                            <span className="font-medium">{r.valor_texto}</span>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
