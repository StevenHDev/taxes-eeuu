import { AlertTriangle, CheckCircle2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type RelacionDeclarada = {
    forma: string;
    campo: string;
    subcampo: string | null;
    descripcion: string | null;
    acumulable: boolean;
};

export type DerivationLog = {
    documento_campo: string;
    documento_nombre: string | null;
    relaciones_esperadas: RelacionDeclarada[];
    relaciones_faltantes: RelacionDeclarada[];
    created_at: string;
};

/**
 * Traza de qué relaciones documento→campo (ver App\Models\RelacionDocumentoCampo)
 * se cubrieron o no al guardar cada documento — ver
 * EventoRecoleccionService::registrarDerivacion(). Pensado para que el
 * preparador vea de un vistazo "este W-2 dejó sin cubrir tal campo", en vez
 * de tener que releer la conversación completa para diagnosticarlo.
 */
export function DerivationLogsPanel({ logs }: { logs: DerivationLog[] }) {
    const { t, i18n } = useTranslation();

    if (logs.length === 0) {
        return null;
    }

    const formatDate = (iso: string) =>
        new Intl.DateTimeFormat(i18n.language, {
            dateStyle: 'medium',
            timeStyle: 'short',
        }).format(new Date(iso));

    return (
        <Card>
            <CardHeader>
                <CardTitle>{t('clienteShow.derivationLogs.title')}</CardTitle>
                <p className="mt-1 text-xs text-muted-foreground">
                    {t('clienteShow.derivationLogs.description')}
                </p>
            </CardHeader>
            <CardContent className="space-y-3">
                {logs.map((log, i) => {
                    const faltan = log.relaciones_faltantes.length;

                    return (
                        <div
                            key={`${log.documento_campo}-${log.created_at}-${i}`}
                            className={`rounded-lg border p-3 text-sm ${
                                faltan > 0
                                    ? 'border-amber-500/40 bg-amber-500/10'
                                    : 'border-border'
                            }`}
                        >
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <span className="font-mono font-medium">
                                    {log.documento_campo}
                                    {log.documento_nombre && (
                                        <span className="ml-2 font-sans text-xs text-muted-foreground">
                                            {log.documento_nombre}
                                        </span>
                                    )}
                                </span>
                                <span className="text-xs text-muted-foreground">
                                    {formatDate(log.created_at)}
                                </span>
                            </div>

                            <div className="mt-2 flex items-start gap-1.5">
                                {faltan > 0 ? (
                                    <AlertTriangle className="mt-0.5 size-4 shrink-0 text-amber-500" />
                                ) : (
                                    <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-emerald-500" />
                                )}
                                <div>
                                    <p>
                                        {faltan > 0
                                            ? t('clienteShow.derivationLogs.missing', { count: faltan })
                                            : t('clienteShow.derivationLogs.complete')}
                                    </p>
                                    {faltan > 0 && (
                                        <ul className="mt-1 list-inside list-disc font-mono text-xs">
                                            {log.relaciones_faltantes.map((r) => (
                                                <li key={`${r.forma}-${r.campo}-${r.subcampo ?? ''}`}>
                                                    {r.forma}.{r.campo}
                                                    {r.subcampo ? `.${r.subcampo}` : ''}
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </div>
                            </div>
                        </div>
                    );
                })}
            </CardContent>
        </Card>
    );
}
