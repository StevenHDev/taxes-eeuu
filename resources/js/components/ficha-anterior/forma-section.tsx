import { Check } from 'lucide-react';
import { useId } from 'react';
import { useTranslation } from 'react-i18next';
import { CampoRow } from '@/components/ficha-anterior/campo-row';
import { CategoriaGroup } from '@/components/ficha-anterior/categoria-group';
import {
    categoriaDe,
    codigoForma,
    descriptorForma,
    ESTADO_FONDO,
    ORDEN_CATEGORIAS_TRANSVERSAL,
    TRANSVERSAL,
} from '@/components/ficha-anterior/constantes';
import {
    Table,
    TableBody,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { CampoCliente, ClienteForma } from '@/types';

export function FormaSection({
    clienteId,
    taxYear,
    forma,
    label,
    info,
    campos,
    faltantes,
}: {
    clienteId: number;
    taxYear: number;
    forma: string;
    label: string;
    info: ClienteForma | undefined;
    campos: CampoCliente[];
    // Campos del catálogo para esta forma que el cliente todavía no tiene
    // cargados (ni siquiera como fila pendiente) — no están en `campos`
    // porque no existe CampoCliente hasta que llega un primer valor. Sin
    // esto, "recibidos" se medía contra sí mismo: un 1040 con un solo campo
    // cargado mostraba «1/1», aunque el catálogo real tenga cinco.
    faltantes: number;
}) {
    const { t } = useTranslation();
    const tituloId = useId();
    const recibidos = campos.filter((c) => c.estado === 'recibido').length;
    const total = campos.length + faltantes;

    // El catálogo transversal (61 campos posibles) es el único lo bastante
    // grande para sentirse "lleno de información" en una tabla plana — se
    // agrupa en subcategorías temáticas plegables (ver
    // CATEGORIA_TRANSVERSAL_POR_CAMPO). El resto de formas (1040, Schedule C,
    // etc.) tienen catálogos acotados que ya se leen bien tal cual.
    const porCategoria =
        forma === TRANSVERSAL
            ? (() => {
                  const grupos = new Map<string, CampoCliente[]>();

                  for (const campo of campos) {
                      const categoria = categoriaDe(campo.campo);
                      const lista = grupos.get(categoria);

                      if (lista) {
                          lista.push(campo);
                      } else {
                          grupos.set(categoria, [campo]);
                      }
                  }

                  return ORDEN_CATEGORIAS_TRANSVERSAL.map((categoria) => ({
                      categoria,
                      campos: grupos.get(categoria) ?? [],
                  })).filter((grupo) => grupo.campos.length > 0);
              })()
            : null;

    return (
        <section aria-labelledby={tituloId}>
            <header className="flex flex-wrap items-end justify-between gap-x-6 gap-y-3 border-b pb-2.5">
                <div className="min-w-0">
                    <p className="font-mono text-micro text-muted-foreground uppercase">
                        {codigoForma(forma)}
                    </p>
                    <h2
                        id={tituloId}
                        className="text-title font-semibold text-foreground"
                    >
                        {descriptorForma(label)}
                    </h2>
                </div>

                <div className="flex items-center gap-3">
                    {/*
                     * Mini-mapa: un segmento por campo del catálogo, con la
                     * misma rampa que los rieles de abajo — incluyendo los que
                     * todavía no se cargaron (sin fila propia en `campos`),
                     * pintados como pendiente. Muestra la forma de lo que
                     * falta, no solo cuánto — dos huecos al final no es lo
                     * mismo que dos huecos salteados. El texto de al lado lo
                     * dice para quien no puede verlo.
                     */}
                    <div
                        aria-hidden
                        className="flex w-20 gap-px overflow-hidden rounded-full sm:w-28"
                    >
                        {campos.map((c) => (
                            <span
                                key={`${c.forma}-${c.campo}`}
                                className={`h-1.5 flex-1 ${ESTADO_FONDO[c.estado]}`}
                            />
                        ))}
                        {Array.from({ length: faltantes }).map((_, i) => (
                            <span
                                key={`faltante-${i}`}
                                className={`h-1.5 flex-1 ${ESTADO_FONDO.pendiente}`}
                            />
                        ))}
                    </div>

                    <span className="font-mono text-micro text-muted-foreground tabular-nums">
                        {t('clienteShow.section.received', {
                            recibidos,
                            total,
                        })}
                    </span>

                    {info?.revisado_en ? (
                        <span className="inline-flex items-center gap-1.5 font-mono text-micro text-foreground uppercase">
                            <Check className="size-3 shrink-0" aria-hidden />
                            {t('clienteShow.reviewed')}
                        </span>
                    ) : null}
                </div>
            </header>

            {porCategoria ? (
                <div className="space-y-2">
                    {porCategoria.map(
                        ({ categoria, campos: camposDeCategoria }) => (
                            <CategoriaGroup
                                key={categoria}
                                clienteId={clienteId}
                                taxYear={taxYear}
                                categoria={categoria}
                                campos={camposDeCategoria}
                                formaLabel={label}
                            />
                        ),
                    )}
                </div>
            ) : (
                <Table>
                    {/*
                     * El header de <TableHeader> de fábrica es un bloque navy
                     * sólido — pensado para una tabla suelta, no para repetirse
                     * una vez por forma. Acá se apaga a una fila utilitaria (borde
                     * inferior, sin relleno): el navy queda para el riel de
                     * estado, que es el único lugar donde este color debe hablar
                     * dentro de la sección.
                     */}
                    <TableHeader className="bg-transparent [&_th]:text-muted-foreground [&_tr]:border-border">
                        <TableRow className="hover:bg-transparent">
                            <TableHead className="font-mono text-micro uppercase">
                                {t('clienteShow.table.field')}
                            </TableHead>
                            <TableHead className="font-mono text-micro uppercase">
                                {t('clienteShow.table.value')}
                            </TableHead>
                            <TableHead className="font-mono text-micro uppercase">
                                {t('clienteShow.table.status')}
                            </TableHead>
                            <TableHead className="text-right font-mono text-micro uppercase">
                                {t('common.actions')}
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {campos.map((campo) => (
                            <CampoRow
                                key={`${campo.forma}-${campo.campo}`}
                                clienteId={clienteId}
                                taxYear={taxYear}
                                campo={campo}
                                formaLabel={label}
                            />
                        ))}
                    </TableBody>
                </Table>
            )}
        </section>
    );
}
