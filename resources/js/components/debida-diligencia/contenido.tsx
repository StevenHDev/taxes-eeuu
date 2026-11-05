import { Info } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { CreditosForm } from '@/components/debida-diligencia/creditos-form';
import { formatearFechaHora } from '@/components/debida-diligencia/formato-fecha';
import { ParteCard } from '@/components/debida-diligencia/parte-card';
import { ResumenEntrevista } from '@/components/debida-diligencia/resumen-entrevista';
import { Alert, AlertDescription } from '@/components/ui/alert';
import type { DebidaDiligenciaVista } from '@/types/debida-diligencia';

type Props = {
    debida: DebidaDiligenciaVista;
    clienteId: number;
    taxYear: number;
    urlEntrevista: string;
};

export function DebidaContenido({
    debida,
    clienteId,
    taxYear,
    urlEntrevista,
}: Props) {
    const { t, i18n } = useTranslation();

    if (!debida.aplica) {
        return (
            <Alert>
                <Info aria-hidden="true" />
                <AlertDescription>
                    {t('debidaDiligencia.sinEntrevista')}
                </AlertDescription>
            </Alert>
        );
    }

    const sinCreditos = !debida.creditos.some((c) => c.reclamado);
    // Se reinicia el formulario cuando cambia lo guardado.
    const llaveForm = `${debida.creditos_en ?? 'sin'}-${debida.creditos.map((c) => `${c.credito}${c.reclamado ? 1 : 0}`).join('')}`;

    return (
        <div className="space-y-6">
            <CreditosForm
                key={llaveForm}
                clienteId={clienteId}
                taxYear={taxYear}
                debida={debida}
            />

            {sinCreditos && (
                <Alert>
                    <Info aria-hidden="true" />
                    <AlertDescription>
                        {t('debidaDiligencia.sinCreditos')}
                    </AlertDescription>
                </Alert>
            )}

            {debida.entrevista && (
                <ResumenEntrevista entrevista={debida.entrevista} />
            )}

            <div className="space-y-4">
                {debida.secciones.map((s) => (
                    <ParteCard
                        key={s.parte}
                        seccion={s}
                        urlEntrevista={urlEntrevista}
                    />
                ))}
            </div>

            <p className="text-xs text-muted-foreground">
                {t('debidaDiligencia.generadoEn', {
                    fecha: formatearFechaHora(
                        debida.generado_en,
                        i18n.language,
                    ),
                })}
            </p>
        </div>
    );
}
