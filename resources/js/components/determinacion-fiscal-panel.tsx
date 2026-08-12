import { router } from '@inertiajs/react';
import { Landmark, Receipt, Scale, Wallet } from 'lucide-react';
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
    DeduccionAplicableResultado,
    Determinacion,
    DependientesResultado,
    FilingStatusResultado,
    FilingStatusValue,
    ImpuestoAutoempleoResultado,
    ImpuestoIngresoResultado,
    ImpuestoMedicareAdicionalResultado,
    LiquidacionResultado,
    NiitResultado,
    QbiResultado,
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
    readOnly = false,
}: {
    clienteId: number;
    taxYear: number;
    determinaciones: Determinacion[];
    /**
     * Vista de autoservicio del cliente (`mi-informacion.tsx`): el cliente
     * nunca dispara el motor de reglas, solo un preparador/administrador
     * desde su panel — ver ClientePolicy y DeterminacionFiscalController.
     */
    readOnly?: boolean;
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
    const creditos = resultadoDe<CreditosResultado>(
        determinaciones,
        'creditos',
    );
    const deduccionAplicable = resultadoDe<DeduccionAplicableResultado>(
        determinaciones,
        'deduccion_aplicable',
    );
    const qbi = resultadoDe<QbiResultado>(determinaciones, 'qbi');
    const impuestoIngreso = resultadoDe<ImpuestoIngresoResultado>(
        determinaciones,
        'impuesto_ingreso',
    );
    const impuestoAutoempleo = resultadoDe<ImpuestoAutoempleoResultado>(
        determinaciones,
        'impuesto_autoempleo',
    );
    const impuestoMedicareAdicional =
        resultadoDe<ImpuestoMedicareAdicionalResultado>(
            determinaciones,
            'impuesto_medicare_adicional',
        );
    const niit = resultadoDe<NiitResultado>(determinaciones, 'niit');
    const liquidacion = resultadoDe<LiquidacionResultado>(
        determinaciones,
        'liquidacion',
    );

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
                {!readOnly && (
                    <Button size="sm" onClick={calcular} disabled={calculando}>
                        {yaCalculado
                            ? t('determinacionFiscal.recalculate')
                            : t('determinacionFiscal.calculateNow')}
                    </Button>
                )}
            </CardHeader>

            {yaCalculado && (
                <CardContent className="space-y-6">
                    {liquidacion?.disponible && (
                        <div
                            className={`rounded-lg p-4 ${
                                liquidacion.saldo_a_pagar > 0
                                    ? 'bg-destructive/10'
                                    : 'bg-emerald-500/10'
                            }`}
                        >
                            <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                {t(
                                    liquidacion.saldo_a_pagar > 0
                                        ? 'determinacionFiscal.settlement.balanceDue'
                                        : 'determinacionFiscal.settlement.refund',
                                )}
                            </p>
                            <p className="mt-1 text-2xl font-semibold text-foreground tabular-nums">
                                {formatCurrency(
                                    liquidacion.saldo_a_pagar > 0
                                        ? liquidacion.saldo_a_pagar
                                        : liquidacion.reembolso,
                                )}
                            </p>
                            <dl className="mt-3 grid gap-1 text-xs text-muted-foreground sm:grid-cols-2">
                                <div className="flex justify-between gap-2">
                                    <dt>
                                        {t(
                                            'determinacionFiscal.settlement.totalTax',
                                        )}
                                    </dt>
                                    <dd className="tabular-nums">
                                        {formatCurrency(
                                            liquidacion.total_impuesto,
                                        )}
                                    </dd>
                                </div>
                                <div className="flex justify-between gap-2">
                                    <dt>
                                        {t(
                                            'determinacionFiscal.settlement.totalPayments',
                                        )}
                                    </dt>
                                    <dd className="tabular-nums">
                                        {formatCurrency(
                                            liquidacion.total_pagos,
                                        )}
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    )}

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
                                    {dependientes.dependientes.map((dep, i) => (
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
                                    ))}
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
                                        {t('determinacionFiscal.dependentCare')}
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

                    {(deduccionAplicable?.disponible || qbi?.disponible) && (
                        <div>
                            <h3 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                {t('determinacionFiscal.deduction.title')}
                            </h3>
                            <dl className="mt-2 space-y-1 text-sm">
                                {deduccionAplicable?.disponible && (
                                    <>
                                        <div className="flex justify-between">
                                            <dt className="text-muted-foreground">
                                                {t(
                                                    'determinacionFiscal.deduction.standard',
                                                )}
                                            </dt>
                                            <dd className="tabular-nums">
                                                {formatCurrency(
                                                    deduccionAplicable.deduccion_estandar,
                                                )}
                                            </dd>
                                        </div>
                                        <div className="flex justify-between">
                                            <dt className="text-muted-foreground">
                                                {t(
                                                    'determinacionFiscal.deduction.itemized',
                                                )}
                                            </dt>
                                            <dd className="tabular-nums">
                                                {formatCurrency(
                                                    deduccionAplicable.deduccion_itemizada,
                                                )}
                                            </dd>
                                        </div>
                                        <div className="flex justify-between border-t pt-1 font-medium text-foreground">
                                            <dt className="flex items-center gap-1.5">
                                                {t(
                                                    'determinacionFiscal.deduction.applied',
                                                )}
                                                {deduccionAplicable.usa_itemizada && (
                                                    <Badge variant="secondary">
                                                        {t(
                                                            'determinacionFiscal.deduction.usingItemized',
                                                        )}
                                                    </Badge>
                                                )}
                                            </dt>
                                            <dd className="tabular-nums">
                                                {formatCurrency(
                                                    deduccionAplicable.deduccion_aplicable,
                                                )}
                                            </dd>
                                        </div>
                                    </>
                                )}
                                {qbi?.disponible && qbi.deduccion > 0 && (
                                    <div className="flex justify-between">
                                        <dt className="flex items-center gap-1.5 text-muted-foreground">
                                            {t(
                                                'determinacionFiscal.deduction.qbiDeduction',
                                            )}
                                            {qbi.requiere_revision_manual && (
                                                <Badge variant="outline">
                                                    {t(
                                                        'determinacionFiscal.deduction.qbiManualReview',
                                                    )}
                                                </Badge>
                                            )}
                                        </dt>
                                        <dd className="tabular-nums">
                                            {formatCurrency(qbi.deduccion)}
                                        </dd>
                                    </div>
                                )}
                            </dl>
                        </div>
                    )}

                    {impuestoIngreso?.disponible &&
                        (impuestoAutoempleo?.disponible ||
                            impuestoMedicareAdicional?.disponible ||
                            niit?.disponible) && (
                            <div>
                                <h3 className="flex items-center gap-1.5 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                    <Landmark className="size-3.5" />
                                    {t('determinacionFiscal.otherTaxes.title')}
                                </h3>
                                <dl className="mt-2 space-y-1 text-sm">
                                    <div className="flex justify-between">
                                        <dt className="text-muted-foreground">
                                            {t(
                                                'determinacionFiscal.otherTaxes.incomeTax',
                                            )}
                                        </dt>
                                        <dd className="tabular-nums">
                                            {formatCurrency(
                                                impuestoIngreso.impuesto,
                                            )}
                                        </dd>
                                    </div>
                                    {impuestoAutoempleo?.disponible &&
                                        impuestoAutoempleo.impuesto_se > 0 && (
                                            <div className="flex justify-between">
                                                <dt className="text-muted-foreground">
                                                    {t(
                                                        'determinacionFiscal.otherTaxes.selfEmploymentTax',
                                                    )}
                                                </dt>
                                                <dd className="tabular-nums">
                                                    {formatCurrency(
                                                        impuestoAutoempleo.impuesto_se,
                                                    )}
                                                </dd>
                                            </div>
                                        )}
                                    {impuestoMedicareAdicional?.disponible &&
                                        impuestoMedicareAdicional.impuesto >
                                            0 && (
                                            <div className="flex justify-between">
                                                <dt className="text-muted-foreground">
                                                    {t(
                                                        'determinacionFiscal.otherTaxes.additionalMedicare',
                                                    )}
                                                </dt>
                                                <dd className="tabular-nums">
                                                    {formatCurrency(
                                                        impuestoMedicareAdicional.impuesto,
                                                    )}
                                                </dd>
                                            </div>
                                        )}
                                    {niit?.disponible && niit.impuesto > 0 && (
                                        <div className="flex justify-between">
                                            <dt className="text-muted-foreground">
                                                {t(
                                                    'determinacionFiscal.otherTaxes.niit',
                                                )}
                                            </dt>
                                            <dd className="tabular-nums">
                                                {formatCurrency(niit.impuesto)}
                                            </dd>
                                        </div>
                                    )}
                                </dl>
                            </div>
                        )}
                </CardContent>
            )}

            {!yaCalculado && (
                <CardContent>
                    <p className="text-sm text-muted-foreground">
                        {t(
                            readOnly
                                ? 'determinacionFiscal.emptyStateReadOnly'
                                : 'determinacionFiscal.emptyState',
                        )}
                    </p>
                </CardContent>
            )}
        </Card>
    );
}
