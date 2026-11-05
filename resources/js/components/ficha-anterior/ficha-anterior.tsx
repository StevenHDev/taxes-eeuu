import { useTranslation } from 'react-i18next';
import type { DerivationLog } from '@/components/derivation-logs-panel';
import {
    DOCUMENTOS_EXTRA,
    TRANSVERSAL,
} from '@/components/ficha-anterior/constantes';
import { FormaSection } from '@/components/ficha-anterior/forma-section';
import type {
    CampoCliente,
    CatalogoDisponibleItem,
    ClienteForma,
    Determinacion,
} from '@/types';

/** Lo que entrega `FichaAnterior::para()` (solo lectura). */
export type AnteriorFicha = {
    catalogoDisponible: CatalogoDisponibleItem[];
    formas: ClienteForma[];
    campos: CampoCliente[];
    determinaciones: Determinacion[];
    derivationLogs: DerivationLog[];
};

/** Las secciones por forma del formulario anterior, de solo lectura. */
export function FormasAnteriores({
    clienteId,
    taxYear,
    anterior,
}: {
    clienteId: number;
    taxYear: number;
    anterior: AnteriorFicha;
}) {
    const { t } = useTranslation();
    const { formas, campos, catalogoDisponible } = anterior;

    const formaLabel = (forma: string) => {
        if (forma === TRANSVERSAL) {
            return t('clienteShow.transversalLabel');
        }

        if (forma === DOCUMENTOS_EXTRA) {
            return t('clienteShow.documentosExtraLabel');
        }

        return formas.find((f) => f.forma === forma)?.forma_label ?? forma;
    };

    // Agrupado por forma, preservando el orden por campo que ya trae el servidor.
    const porForma = new Map<string, CampoCliente[]>();

    for (const campo of campos) {
        const lista = porForma.get(campo.forma);

        if (lista) {
            lista.push(campo);
        } else {
            porForma.set(campo.forma, [campo]);
        }
    }

    // Campos del catálogo que el cliente todavía no tiene cargados: son el
    // resto del total real de cada forma.
    const faltantesPorForma = new Map<string, number>();

    for (const item of catalogoDisponible) {
        faltantesPorForma.set(
            item.forma,
            (faltantesPorForma.get(item.forma) ?? 0) + 1,
        );
    }

    const infoPorForma = new Map<string, ClienteForma>();

    for (const f of formas) {
        infoPorForma.set(f.forma, f);
    }

    // Identidad primero, luego los documentos extra, luego las formas en el
    // orden del servidor y al final cualquier forma con campos no declarada.
    const declaradas = [
        TRANSVERSAL,
        DOCUMENTOS_EXTRA,
        ...formas.map((f) => f.forma),
    ];
    const secciones = [
        ...declaradas,
        ...[...porForma.keys()].filter((f) => !declaradas.includes(f)),
    ].filter((forma) => porForma.has(forma));

    if (secciones.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                {t('clienteShow.table.empty')}
            </p>
        );
    }

    return (
        <div className="space-y-10">
            {secciones.map((forma) => (
                <FormaSection
                    key={forma}
                    clienteId={clienteId}
                    taxYear={taxYear}
                    forma={forma}
                    label={formaLabel(forma)}
                    info={infoPorForma.get(forma)}
                    campos={porForma.get(forma) ?? []}
                    faltantes={faltantesPorForma.get(forma) ?? 0}
                />
            ))}
        </div>
    );
}
