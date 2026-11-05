import { router } from '@inertiajs/react';
import { TriangleAlert } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { ClienteCuentaAcciones } from '@/components/cuenta-usuario-acciones';
import { EstadoExpedienteBadge } from '@/components/ficha/estado-expediente';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { WhatsappConversationDialog } from '@/components/whatsapp-conversation-dialog';
import type { ClavePestana, FichaEncabezado } from '@/types/expediente';

function useFecha() {
    const { i18n } = useTranslation();

    return (iso: string) => {
        // Las fechas de solo día ("2026-04-15") se leen como locales.
        const fecha = /^\d{4}-\d{2}-\d{2}$/.test(iso)
            ? new Date(`${iso}T00:00:00`)
            : new Date(iso);

        return new Intl.DateTimeFormat(i18n.language, {
            dateStyle: 'medium',
        }).format(fecha);
    };
}

export function FichaEncabezadoCliente({
    ficha,
    activa,
}: {
    ficha: FichaEncabezado;
    activa: ClavePestana;
}) {
    const { t } = useTranslation();
    const fecha = useFecha();
    const { cliente, expediente, conteos } = ficha;

    // Estado derivado en el render: las partes del resumen de conteos.
    const partesConteos = [
        conteos.excepciones > 0
            ? t('ficha.conteos.excepciones', { count: conteos.excepciones }) +
              (conteos.bloqueantes > 0
                  ? ` (${t('ficha.conteos.bloqueantes', { count: conteos.bloqueantes })})`
                  : '')
            : null,
        conteos.documentos_por_validar > 0
            ? t('ficha.conteos.porValidar', {
                  count: conteos.documentos_por_validar,
              })
            : null,
        conteos.documentos_faltan > 0
            ? t('ficha.conteos.faltan', { count: conteos.documentos_faltan })
            : null,
    ].filter((parte): parte is string => parte !== null);

    const cambiarAnio = (anio: string) => {
        const base =
            ficha.pestanas.find((p) => p.clave === activa)?.url ??
            window.location.href;
        const url = new URL(base, window.location.origin);

        url.searchParams.set('tax_year', anio);
        router.visit(url.pathname + url.search);
    };

    return (
        <header className="space-y-3">
            <p className="hidden text-sm text-muted-foreground print:block">
                {t('ficha.impresion', {
                    cliente: cliente.name,
                    anio: ficha.tax_year,
                })}
            </p>

            <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between print:hidden">
                <div className="min-w-0">
                    <h1 className="flex flex-wrap items-center gap-2 font-display text-display text-foreground">
                        {cliente.name}
                        {cliente.es_prueba ? (
                            <Badge variant="outline">
                                {t('cuentaUsuario.badgePrueba')}
                            </Badge>
                        ) : null}
                    </h1>
                    {/* Correo y teléfono son identificadores, no prosa. */}
                    <p className="mt-1.5 font-mono text-xs break-all text-muted-foreground">
                        {cliente.email}
                        {cliente.phone ? ` · ${cliente.phone}` : ''}
                    </p>
                </div>

                <div className="flex flex-wrap items-end gap-2">
                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor="ficha-anio" className="text-sm">
                            {t('ficha.anio')}
                        </Label>
                        <Select
                            value={String(ficha.tax_year)}
                            onValueChange={cambiarAnio}
                        >
                            <SelectTrigger
                                id="ficha-anio"
                                className="h-11 w-28"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {ficha.anios.map((anio) => (
                                    <SelectItem
                                        key={anio}
                                        value={String(anio)}
                                        className="min-h-11"
                                    >
                                        {anio}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    {cliente.phone ? (
                        <WhatsappConversationDialog
                            clienteId={cliente.id}
                            clienteName={cliente.name}
                        />
                    ) : null}
                    {ficha.puede_administrar ? (
                        <ClienteCuentaAcciones cliente={cliente} />
                    ) : null}
                </div>
            </div>

            <div className="space-y-1.5 text-sm">
                <p className="flex flex-wrap items-center gap-x-3 gap-y-1.5">
                    {expediente.existe &&
                    expediente.estado &&
                    expediente.label ? (
                        <>
                            <EstadoExpedienteBadge
                                estado={expediente.estado}
                                label={expediente.label}
                                turno={expediente.turno}
                            />
                            {expediente.desde ? (
                                <span className="text-muted-foreground">
                                    {t('ficha.desde', {
                                        fecha: fecha(expediente.desde),
                                    })}
                                </span>
                            ) : null}
                            {expediente.turno ? (
                                <span>
                                    {t('ficha.leToca.etiqueta', {
                                        quien: t(
                                            `ficha.leToca.${expediente.turno}`,
                                        ),
                                    })}
                                </span>
                            ) : null}
                            {expediente.vence_en ? (
                                <span className="text-muted-foreground">
                                    {t('ficha.vence', {
                                        fecha: fecha(expediente.vence_en),
                                    })}
                                </span>
                            ) : null}
                        </>
                    ) : (
                        <span className="text-muted-foreground">
                            {t('ficha.sinExpediente')}
                        </span>
                    )}
                </p>
                {partesConteos.length > 0 ? (
                    <p className="text-muted-foreground">
                        {partesConteos.join(' · ')}
                    </p>
                ) : null}
            </div>

            {expediente.cambios_tras_presentar ? (
                <Alert variant="destructive" className="print:hidden">
                    <TriangleAlert aria-hidden="true" />
                    <AlertTitle>
                        {t('ficha.cambiosTrasPresentarTitulo')}
                    </AlertTitle>
                    <AlertDescription>
                        {t('ficha.cambiosTrasPresentar')}
                    </AlertDescription>
                </Alert>
            ) : null}
        </header>
    );
}
