import { Timer } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { numero } from '@/components/panel/formato';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { TiemposPanel } from '@/types/panel';

export function Tiempos({ tiempos }: { tiempos: TiemposPanel | null }) {
    const { t, i18n } = useTranslation();
    const sinDatos = tiempos === null || tiempos.muestra === 0;

    const cifras = sinDatos
        ? []
        : [
              {
                  clave: 'diasHastaListo',
                  valor: tiempos.dias_hasta_listo,
              },
              {
                  clave: 'horasPreparacion',
                  valor: tiempos.horas_preparacion,
              },
              {
                  clave: 'consultas',
                  valor: tiempos.consultas_por_expediente,
              },
              {
                  clave: 'correcciones',
                  valor: tiempos.correcciones_por_expediente,
              },
          ];

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2 text-sm">
                    <Timer aria-hidden="true" className="size-4" />
                    {t('panel.tiempos.titulo')}
                </CardTitle>
            </CardHeader>
            <CardContent>
                {sinDatos ? (
                    <p className="py-4 text-sm text-muted-foreground">
                        {t('panel.tiempos.sinDatos')}
                    </p>
                ) : (
                    <>
                        <dl className="grid gap-3 sm:grid-cols-2">
                            {cifras.map((c) => (
                                <div
                                    key={c.clave}
                                    className="rounded-lg border p-3"
                                >
                                    <dt className="text-xs text-muted-foreground">
                                        {t(`panel.tiempos.${c.clave}.etiqueta`)}
                                    </dt>
                                    <dd className="text-xl font-semibold tabular-nums">
                                        {c.valor === null
                                            ? '—'
                                            : numero(c.valor, i18n.language)}
                                    </dd>
                                    <p className="text-xs text-muted-foreground">
                                        {t(`panel.tiempos.${c.clave}.ayuda`)}
                                    </p>
                                </div>
                            ))}
                        </dl>
                        <p className="mt-3 text-xs text-muted-foreground">
                            {t('panel.tiempos.muestra', {
                                count: tiempos.muestra,
                            })}
                        </p>
                    </>
                )}
            </CardContent>
        </Card>
    );
}
