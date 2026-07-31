import { router } from '@inertiajs/react';
import { Receipt, Scale, Wallet } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { StatCard } from '@/components/dashboard/stat-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { store as calcularDeterminaciones } from '@/routes/clientes/determinaciones';
import type {
    AgiResultado,
    CreditosResultado,
    Determinacion,
    DependientesResultado,
    FilingStatusResultado,
    FilingStatusValue,
    TipoDeterminacion,
} from '@/types';

const FILING_STATUS_LABEL: Record<FilingStatusValue, string> = {
    mfj: 'Married Filing Jointly',
    single: 'Single',
    hoh: 'Head of Household',
    qss: 'Qualifying Surviving Spouse',
};

function resultadoDe<T>(
    determinaciones: Determinacion[],
    tipo: TipoDeterminacion,
): T | null {
    const fila = determinaciones.find((d) => d.tipo === tipo);

    return fila ? (fila.resultado as T) : null;
}

const formatCurrency = (value: number) =>
    new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        maximumFractionDigits: 0,
    }).format(value);

export function DeterminacionFiscalPanel({
    clienteId,
    taxYear,
    determinaciones,
}: {
    clienteId: number;
    taxYear: number;
    determinaciones: Determinacion[];
}) {
    const { t, i18n } = useTranslation();
    const [calculando, setCalculando] = useState(false);

    const yaCalculado = determinaciones.length > 0;
    const ultimoCalculo = determinaciones[0]?.calculado_en ?? null;

    const filingStatus = resultadoDe<FilingStatusResultado>(
        determinaciones,
        'filing_status',
    );
    const dependientes = resultadoDe<DependientesResultado>(
        determinaciones,
        'dependientes',
    );
    const agi = resultadoDe<AgiResultado>(determinaciones, 'agi');
    const creditos = resultadoDe<CreditosResultado>(determinaciones, 'creditos');

    const calcular = () => {
        setCalculando(true);
        router.post(
            calcularDeterminaciones({ cliente: clienteId }).url,
            { tax_year: taxYear },
            { preserveScroll: true, onFinish: () => setCalculando(false) },
        );
    };

    const formatDate = (iso: string) =>
        new Intl.DateTimeFormat(i18n.language, {
            dateStyle: 'medium',
            timeStyle: 'short',
        }).format(new Date(iso));

    return (
        <Card>
            <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-3">
                <div>
                    <CardTitle>
                        {t('determinacionFiscal.title', { year: taxYear })}
                    </CardTitle>
                    <p className="mt-1 text-xs text-muted-foreground">
                        {yaCalculado && ultimoCalculo
                            ? t('determinacionFiscal.lastCalculated', {
                                  date: formatDate(ultimoCalculo),
                              })
                            : t('determinacionFiscal.neverCalculated')}
                    </p>
                </div>
                <Button size="sm" onClick={calcular} disabled={calculando}>
                    {yaCalculado
                        ? t('determinacionFiscal.recalculate')
                        : t('determinacionFiscal.calculateNow')}
                </Button>
            </CardHeader>

            {yaCalculado && (
                <CardContent className="space-y-6">
                    <div className="grid gap-4 sm:grid-cols-3">
                        <StatCard
                            icon={Scale}
                            label={t('determinacionFiscal.filingStatus')}
                            value={
                                filingStatus?.disponible
                                    ? FILING_STATUS_LABEL[filingStatus.estado]
                                    : t('common.none')
                            }
                            hint={
                                filingStatus && !filingStatus.disponible
                                    ? filingStatus.motivo_no_disponible
                                    : undefined
                            }
                        />
                        <StatCard
                            icon={Wallet}
                            label={t('determinacionFiscal.agi')}
                            value={
                                agi?.disponible
                                    ? formatCurrency(agi.agi)
                                    : t('common.none')
                            }
                            hint={
                                agi && !agi.disponible
                                    ? agi.motivo_no_disponible
                                    : undefined
                            }
                        />
                        <StatCard
                            icon={Receipt}
                            label={t('determinacionFiscal.totalCredits')}
                            accent="strong"
                            value={
                                creditos?.disponible
                                    ? formatCurrency(creditos.total)
                                    : t('common.none')
                            }
                            hint={
                                creditos && !creditos.disponible
                                    ? creditos.motivo_no_disponible
                                    : undefined
                            }
                        />
                    </div>

                    {dependientes?.disponible &&
                        dependientes.dependientes.length > 0 && (
                            <div>
                                <h3 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                    {t('determinacionFiscal.dependents')}
                                </h3>
                                <ul className="mt-2 space-y-2">
                                    {dependientes.dependientes.map(
                                        (dep, i) => (
                                            <li
                                                key={i}
                                                className="flex items-center justify-between gap-2 text-sm"
                                            >
                                                <span className="text-foreground">
                                                    {dep.nombre_completo ??
                                                        t(
                                                            'determinacionFiscal.unnamedDependent',
                                                        )}
                                                </span>
                                                <Badge
                                                    variant={
                                                        dep.elegible_ctc ||
                                                        dep.elegible_odc
                                                            ? 'secondary'
                                                            : 'outline'
                                                    }
                                                >
                                                    {dep.elegible_ctc
                                                        ? 'CTC'
                                                        : dep.elegible_odc
                                                          ? 'ODC'
                                                          : t(
                                                                'determinacionFiscal.notQualified',
                                                            )}
                                                </Badge>
                                            </li>
                                        ),
                                    )}
                                </ul>
                            </div>
                        )}

                    {creditos?.disponible && (
                        <div>
                            <h3 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                {t('determinacionFiscal.creditBreakdown')}
                            </h3>
                            <dl className="mt-2 space-y-1 text-sm">
                                <div className="flex justify-between">
                                    <dt className="text-muted-foreground">
                                        Child Tax Credit
                                    </dt>
                                    <dd className="tabular-nums">
                                        {formatCurrency(creditos.ctc)}
                                    </dd>
                                </div>
                                <div className="flex justify-between">
                                    <dt className="text-muted-foreground">
                                        Credit for Other Dependents
                                    </dt>
                                    <dd className="tabular-nums">
                                        {formatCurrency(creditos.odc)}
                                    </dd>
                                </div>
                                <div className="flex justify-between">
                                    <dt className="text-muted-foreground">
                                        {t(
                                            'determinacionFiscal.dependentCare',
                                        )}
                                    </dt>
                                    <dd className="tabular-nums">
                                        {formatCurrency(
                                            creditos.cuidado_dependientes,
                                        )}
                                    </dd>
                                </div>
                                {creditos.reduccion_por_agi > 0 && (
                                    <div className="flex justify-between">
                                        <dt className="text-muted-foreground">
                                            {t(
                                                'determinacionFiscal.agiReduction',
                                            )}
                                        </dt>
                                        <dd className="tabular-nums">
                                            -
                                            {formatCurrency(
                                                creditos.reduccion_por_agi,
                                            )}
                                        </dd>
                                    </div>
                                )}
                                <div className="flex justify-between border-t pt-1 font-medium text-foreground">
                                    <dt>{t('determinacionFiscal.total')}</dt>
                                    <dd className="tabular-nums">
                                        {formatCurrency(creditos.total)}
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    )}
                </CardContent>
            )}

            {!yaCalculado && (
                <CardContent>
                    <p className="text-sm text-muted-foreground">
                        {t('determinacionFiscal.emptyState')}
                    </p>
                </CardContent>
            )}
        </Card>
    );
}
