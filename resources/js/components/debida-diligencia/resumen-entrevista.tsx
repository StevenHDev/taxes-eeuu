import { useTranslation } from 'react-i18next';
import { formatearFechaHora } from '@/components/debida-diligencia/formato-fecha';
import type { EntrevistaDebida } from '@/types/debida-diligencia';

export function ResumenEntrevista({
    entrevista,
}: {
    entrevista: EntrevistaDebida;
}) {
    const { t, i18n } = useTranslation();
    const fecha = (iso: string) => formatearFechaHora(iso, i18n.language);

    const partes = [
        t('debidaDiligencia.entrevista.iniciada', {
            fecha: fecha(entrevista.iniciada_en),
            canal: t(`debidaDiligencia.canal.${entrevista.canal_inicio}`),
        }),
        entrevista.confirmada_en
            ? t('debidaDiligencia.entrevista.confirmada', {
                  fecha: fecha(entrevista.confirmada_en),
              })
            : t('debidaDiligencia.entrevista.sinConfirmar'),
        entrevista.consentimiento
            ? t('debidaDiligencia.entrevista.consentimiento', {
                  version: entrevista.consentimiento.version,
                  fecha: fecha(entrevista.consentimiento.aceptado_en),
              })
            : t('debidaDiligencia.entrevista.sinConsentimiento'),
    ];

    return (
        <p className="text-sm leading-relaxed">
            <span className="font-medium">
                {t('debidaDiligencia.entrevista.titulo')}:{' '}
            </span>
            {partes.join(' · ')}
        </p>
    );
}
