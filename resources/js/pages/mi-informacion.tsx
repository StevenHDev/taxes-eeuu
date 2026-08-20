import { Head, router } from '@inertiajs/react';
import { AlertTriangle, Check, Circle, FileDown, MinusCircle } from 'lucide-react';
import { Fragment } from 'react';
import { useTranslation } from 'react-i18next';
import { DeterminacionFiscalPanel } from '@/components/determinacion-fiscal-panel';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import type { CampoCliente, ClienteForma, Determinacion } from '@/types';

const TRANSVERSAL = 'transversal';
const DOCUMENTOS_EXTRA = 'documentos_extra';

// Mismo criterio visual que la ficha del preparador (clientes/show.tsx): un
// riel de color al borde para leer el estado de reojo, más una etiqueta para
// confirmar al detenerse. Se duplica acá (en vez de importarse) porque esos
// componentes viven como funciones internas no exportadas de esa página.
const ESTADO_RIEL: Record<CampoCliente['estado'], string> = {
    pendiente: 'border-l-state-pendiente',
    recibido: 'border-l-state-recibido',
    invalido: 'border-l-state-invalido',
    no_aplica: 'border-l-state-no-aplica',
};

const ESTADO_TINTA: Record<CampoCliente['estado'], string> = {
    pendiente: 'text-muted-foreground',
    recibido: 'text-foreground',
    invalido: 'text-destructive',
    no_aplica: 'text-muted-foreground',
};

const ESTADO_ICONO: Record<CampoCliente['estado'], typeof Circle> = {
    pendiente: Circle,
    recibido: Check,
    invalido: AlertTriangle,
    no_aplica: MinusCircle,
};

function EstadoTag({ estado }: { estado: CampoCliente['estado'] }) {
    const { t } = useTranslation();
    const Icon = ESTADO_ICONO[estado];

    return (
        <span
            className={`inline-flex items-center gap-1 text-xs font-medium ${ESTADO_TINTA[estado]}`}
        >
            <Icon className="size-3" />
            {t(`clienteShow.fieldState.${estado}`)}
        </span>
    );
}

const ACRONIMOS = new Set([
    'ssn',
    'itin',
    'ein',
    'agi',
    'ctc',
    'odc',
    'ira',
    'hsa',
]);

function humanizarClave(clave: string): string {
    return clave
        .split('_')
        .map((palabra) =>
            ACRONIMOS.has(palabra)
                ? palabra.toUpperCase()
                : palabra.charAt(0).toUpperCase() + palabra.slice(1),
        )
        .join(' ');
}

// Versión de solo lectura del renderer de valores de clientes/show.tsx — sin
// los diálogos de edición/historial/revelado que esa página sí necesita.
function FieldValue({ value }: { value: unknown }) {
    const { t } = useTranslation();

    if (
        value === null ||
        value === undefined ||
        (typeof value === 'string' && value.trim() === '')
    ) {
        return <span className="text-muted-foreground">{t('common.none')}</span>;
    }

    if (typeof value === 'boolean') {
        return <span>{value ? t('common.yes') : t('common.no')}</span>;
    }

    if (typeof value === 'number' || typeof value === 'string') {
        const esDato = typeof value === 'number' || /\d/.test(value);

        return (
            <span
                className={`wrap-break-word whitespace-pre-wrap ${esDato ? 'font-mono tabular-nums' : ''}`}
            >
                {String(value)}
            </span>
        );
    }

    if (Array.isArray(value)) {
        if (value.length === 0) {
            return (
                <span className="text-muted-foreground">
                    {t('clienteShow.value.emptyList')}
                </span>
            );
        }

        const soloPrimitivos = value.every(
            (v) => v === null || typeof v !== 'object',
        );

        if (soloPrimitivos) {
            return (
                <div className="flex flex-wrap gap-1">
                    {value.map((v, i) => (
                        <Badge key={i} variant="secondary" className="font-normal">
                            {String(v)}
                        </Badge>
                    ))}
                </div>
            );
        }

        return (
            <div className="space-y-2">
                {value.map((v, i) => (
                    <div key={i} className="rounded-md border bg-muted/30 p-2">
                        <div className="mb-1 text-xs font-medium text-muted-foreground">
                            {t('clienteShow.value.record', { n: i + 1 })}
                        </div>
                        <FieldValue value={v} />
                    </div>
                ))}
            </div>
        );
    }

    if (typeof value === 'object') {
        const entries = Object.entries(value as Record<string, unknown>);

        if (entries.length === 0) {
            return (
                <span className="text-muted-foreground">
                    {t('clienteShow.value.emptyList')}
                </span>
            );
        }

        return (
            <dl className="grid gap-x-3 gap-y-1 sm:grid-cols-[minmax(0,auto)_1fr]">
                {entries.map(([k, v]) => (
                    <Fragment key={k}>
                        <dt className="text-xs font-medium text-muted-foreground sm:text-right">
                            {humanizarClave(k)}
                        </dt>
                        <dd className="text-sm">
                            <FieldValue value={v} />
                        </dd>
                    </Fragment>
                ))}
            </dl>
        );
    }

    return <span>{String(value)}</span>;
}

function CampoRow({ campo }: { campo: CampoCliente }) {
    const { t } = useTranslation();

    return (
        <div
            className={`border-l-2 py-3 pl-3 ${ESTADO_RIEL[campo.estado]}`}
        >
            <div className="flex flex-wrap items-center justify-between gap-2">
                <span className="text-sm font-medium text-foreground">
                    {humanizarClave(campo.campo)}
                </span>
                <EstadoTag estado={campo.estado} />
            </div>

            {campo.estado === 'recibido' && (
                <div className="mt-1.5 text-sm">
                    {campo.documento ? (
                        <a
                            href={campo.documento.download_url}
                            className="inline-flex items-center gap-1.5 text-primary hover:underline"
                        >
                            <FileDown className="size-3.5" />
                            {campo.documento.file_original_name}
                        </a>
                    ) : (
                        <FieldValue value={campo.valor} />
                    )}
                </div>
            )}

            {campo.estado === 'no_aplica' && (
                <p className="mt-1 text-xs text-muted-foreground">
                    {t('clienteShow.fieldState.no_aplica')}
                </p>
            )}
        </div>
    );
}

export default function MiInformacion({
    cliente,
    formas,
    campos,
    taxYearActual,
    determinaciones,
}: {
    cliente: { id: number; name: string; email: string; phone: string | null };
    formas: ClienteForma[];
    campos: CampoCliente[];
    taxYearActual: number;
    determinaciones: Determinacion[];
}) {
    const { t } = useTranslation();

    const anosSeleccionables = Array.from(
        new Set([taxYearActual - 1, taxYearActual, taxYearActual + 1]),
    ).sort((a, b) => b - a);

    const cambiarAno = (nuevoAno: number) => {
        router.get(
            dashboard().url,
            { tax_year: nuevoAno },
            { preserveState: true, preserveScroll: true },
        );
    };

    const porForma = new Map<string, CampoCliente[]>();

    for (const campo of campos) {
        const lista = porForma.get(campo.forma);

        if (lista) {
            lista.push(campo);
        } else {
            porForma.set(campo.forma, [campo]);
        }
    }

    const formaLabel = (forma: string) => {
        if (forma === TRANSVERSAL) {
            return t('clienteShow.transversalLabel');
        }

        if (forma === DOCUMENTOS_EXTRA) {
            return t('clienteShow.documentosExtraLabel');
        }

        return formas.find((f) => f.forma === forma)?.forma_label ?? forma;
    };

    const declaradas = [
        TRANSVERSAL,
        DOCUMENTOS_EXTRA,
        ...formas.map((f) => f.forma),
    ];
    const secciones = [
        ...declaradas,
        ...[...porForma.keys()].filter((f) => !declaradas.includes(f)),
    ].filter((forma) => porForma.has(forma));

    return (
        <>
            <Head title={t('miInformacion.pageTitle')} />

            <div className="space-y-8 p-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="min-w-0">
                        <h1 className="font-display text-display text-foreground">
                            {t('miInformacion.pageTitle')}
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {t('miInformacion.subtitle')}
                        </p>
                        <p className="mt-1.5 font-mono text-xs text-muted-foreground">
                            {cliente.email}
                            {cliente.phone ? ` · ${cliente.phone}` : ''}
                        </p>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="tax_year_selector">
                            {t('catalogo.form.taxYear')}
                        </Label>
                        <select
                            id="tax_year_selector"
                            className="rounded border bg-background p-2 text-sm"
                            value={taxYearActual}
                            onChange={(e) => cambiarAno(Number(e.target.value))}
                        >
                            {anosSeleccionables.map((ano) => (
                                <option key={ano} value={ano}>
                                    {ano}
                                </option>
                            ))}
                        </select>
                    </div>
                </div>

                <DeterminacionFiscalPanel
                    clienteId={cliente.id}
                    taxYear={taxYearActual}
                    determinaciones={determinaciones}
                    readOnly
                />

                {secciones.length === 0 ? (
                    <p className="border-t pt-6 text-sm text-muted-foreground">
                        {t('miInformacion.empty')}
                    </p>
                ) : (
                    <div className="space-y-8">
                        {secciones.map((forma) => {
                            const camposForma = porForma.get(forma) ?? [];
                            const infoForma = formas.find(
                                (f) => f.forma === forma,
                            );

                            return (
                                <Card key={forma}>
                                    <CardHeader className="flex-row items-center justify-between space-y-0">
                                        <CardTitle className="text-base">
                                            {formaLabel(forma)}
                                        </CardTitle>
                                        {infoForma && (
                                            <Badge
                                                variant={
                                                    infoForma.estado ===
                                                    'completo'
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                            >
                                                {t(
                                                    `clienteShow.formState.${infoForma.estado === 'completo' ? 'complete' : 'inProgress'}`,
                                                )}
                                            </Badge>
                                        )}
                                    </CardHeader>
                                    <CardContent className="divide-y">
                                        {camposForma.map((campo) => (
                                            <CampoRow
                                                key={`${campo.forma}:${campo.campo}`}
                                                campo={campo}
                                            />
                                        ))}
                                    </CardContent>
                                </Card>
                            );
                        })}
                    </div>
                )}
            </div>
        </>
    );
}

MiInformacion.layout = {
    breadcrumbs: [{ title: 'nav.dashboard', href: dashboard() }],
};
