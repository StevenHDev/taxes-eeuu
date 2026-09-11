import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { dashboard } from '@/routes';
import {
    index as toolsIndex,
    update as updateTool,
} from '@/routes/agente/tools';
import type { FaseTools } from '@/types';

export default function AgenteTools({ fases }: { fases: FaseTools[] }) {
    const { t } = useTranslation();
    const [enCurso, setEnCurso] = useState<string | null>(null);

    const togglear = (fase: string, nombre: string, activo: boolean) => {
        const clave = `${fase}:${nombre}`;
        setEnCurso(clave);

        router.patch(
            updateTool().url,
            { fase, tool_name: nombre, activo },
            { preserveScroll: true, onFinish: () => setEnCurso(null) },
        );
    };

    return (
        <>
            <Head title={t('agente.tools.title')} />

            <div className="flex flex-col gap-1">
                <h1 className="text-xl font-semibold">
                    {t('agente.tools.title')}
                </h1>
                <p className="text-sm text-muted-foreground">
                    {t('agente.tools.subtitle')}
                </p>
            </div>

            <div className="space-y-8">
                {fases.map((fase) => (
                    <div key={fase.fase} className="space-y-2">
                        <h2 className="text-sm font-medium text-foreground">
                            {fase.label}
                        </h2>

                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>
                                        {t('agente.tools.columns.tool')}
                                    </TableHead>
                                    <TableHead>
                                        {t('agente.tools.columns.description')}
                                    </TableHead>
                                    <TableHead className="w-24 text-center">
                                        {t('agente.tools.columns.active')}
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {fase.tools.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={3}
                                            className="text-center text-muted-foreground"
                                        >
                                            {t('agente.tools.empty')}
                                        </TableCell>
                                    </TableRow>
                                )}

                                {fase.tools.map((tool) => {
                                    const clave = `${fase.fase}:${tool.nombre}`;

                                    return (
                                        <TableRow key={clave}>
                                            <TableCell className="font-mono text-xs">
                                                {tool.nombre}
                                            </TableCell>
                                            <TableCell className="text-sm text-muted-foreground">
                                                {tool.descripcion}
                                            </TableCell>
                                            <TableCell className="text-center">
                                                <div className="flex items-center justify-center gap-2">
                                                    <Checkbox
                                                        id={clave}
                                                        checked={tool.activo}
                                                        disabled={
                                                            enCurso === clave
                                                        }
                                                        onCheckedChange={(v) =>
                                                            togglear(
                                                                fase.fase,
                                                                tool.nombre,
                                                                v === true,
                                                            )
                                                        }
                                                    />
                                                    <Label
                                                        htmlFor={clave}
                                                        className="sr-only"
                                                    >
                                                        {t(
                                                            'agente.tools.columns.active',
                                                        )}
                                                    </Label>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}
                            </TableBody>
                        </Table>
                    </div>
                ))}
            </div>
        </>
    );
}

AgenteTools.layout = {
    breadcrumbs: [
        { title: 'nav.dashboard', href: dashboard() },
        { title: 'agente.nav.tools', href: toolsIndex() },
    ],
};
