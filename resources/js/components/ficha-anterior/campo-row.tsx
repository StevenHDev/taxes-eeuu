import { AlertTriangle } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { DocumentoViewerDialog } from '@/components/documento-viewer-dialog';
import {
    ESTADO_ICONO,
    ESTADO_RIEL,
    ESTADO_TINTA,
} from '@/components/ficha-anterior/constantes';
import { DuplicadoBadge } from '@/components/ficha-anterior/duplicado-badge';
import { HistorialDialog } from '@/components/ficha-anterior/historial-dialog';
import { ValorCampo } from '@/components/ficha-anterior/valor-campo';
import { TableCell, TableRow } from '@/components/ui/table';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { humanizarClave } from '@/lib/humanizar-clave';
import type { CampoCliente } from '@/types';

function EstadoTag({ estado }: { estado: CampoCliente['estado'] }) {
    const { t } = useTranslation();
    const Icono = ESTADO_ICONO[estado];

    return (
        <span
            className={`inline-flex items-center gap-1.5 font-mono text-micro uppercase ${ESTADO_TINTA[estado]}`}
        >
            <Icono className="size-3 shrink-0" aria-hidden />
            {t(`clienteShow.fieldState.${estado}`)}
        </span>
    );
}

// Los campos únicos por cliente se guardan bajo una de estas dos pseudo-formas;
// ninguna es una forma fiscal. TRANSVERSAL es la identidad del cliente (SSN,
// cónyuge, dependientes, estado civil, w2, 1099-NEC, 1095-A); DOCUMENTOS_EXTRA

// la tabla plana (formas que no son el catálogo transversal) como dentro de
// cada CategoriaGroup (subgrupo plegable del catálogo transversal).
export function CampoRow({
    clienteId,
    taxYear,
    campo,
    formaLabel,
}: {
    clienteId: number;
    taxYear: number;
    campo: CampoCliente;
    formaLabel: string;
}) {
    return (
        <TableRow>
            <TableCell
                className={`border-l-[3px] align-top font-medium ${ESTADO_RIEL[campo.estado]}`}
            >
                {humanizarClave(campo.campo)}
            </TableCell>
            <TableCell className="max-w-sm align-top text-sm">
                {campo.documento ? (
                    <div className="flex flex-col items-start gap-1">
                        <DocumentoViewerDialog documento={campo.documento} />
                        <DuplicadoBadge duplicado={campo.documento.duplicado} />
                    </div>
                ) : (
                    <ValorCampo
                        clienteId={clienteId}
                        taxYear={taxYear}
                        campo={campo}
                        formaLabel={formaLabel}
                    />
                )}
            </TableCell>
            <TableCell className="align-top">
                <div className="flex items-center gap-1.5">
                    <EstadoTag estado={campo.estado} />
                    {campo.advertencia && (
                        <Tooltip>
                            <TooltipTrigger>
                                <AlertTriangle className="size-4 text-amber-500" />
                            </TooltipTrigger>
                            <TooltipContent>{campo.advertencia}</TooltipContent>
                        </Tooltip>
                    )}
                </div>
            </TableCell>
            <TableCell className="text-right align-top">
                <HistorialDialog
                    clienteId={clienteId}
                    taxYear={taxYear}
                    campo={campo}
                />
            </TableCell>
        </TableRow>
    );
}
