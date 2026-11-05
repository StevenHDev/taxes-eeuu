import { Link } from '@inertiajs/react';
import {
    Bell,
    CircleCheck,
    CircleHelp,
    FileUp,
    MessagesSquare,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { fechaRelativa } from '@/components/panel/formato';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { AvisoPanel, TipoAviso } from '@/types/panel';

const ICONOS: Record<TipoAviso, LucideIcon> = {
    listo_para_preparar: CircleCheck,
    termino_entrevista: MessagesSquare,
    subio_documentos: FileUp,
    respondio_consulta: CircleHelp,
};

export function Avisos({ avisos }: { avisos: AvisoPanel[] }) {
    const { t, i18n } = useTranslation();

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2 text-sm">
                    <Bell aria-hidden="true" className="size-4" />
                    {t('panel.avisos.titulo')}
                </CardTitle>
            </CardHeader>
            <CardContent>
                {avisos.length === 0 ? (
                    <p className="py-4 text-sm text-muted-foreground">
                        {t('panel.avisos.vacio')}
                    </p>
                ) : (
                    <ul className="-mx-2 max-h-80 divide-y overflow-y-auto">
                        {avisos.map((a, i) => {
                            const Icono = ICONOS[a.tipo] ?? Bell;

                            return (
                                <li key={`${a.cliente_id}-${a.creado_en}-${i}`}>
                                    <Link
                                        href={a.url}
                                        className="flex min-h-11 items-center gap-3 rounded-lg px-2 py-2 transition-colors hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none active:bg-accent/70"
                                    >
                                        <Icono
                                            aria-hidden="true"
                                            className="size-4 shrink-0 text-primary"
                                        />
                                        <span className="min-w-0 flex-1 text-sm">
                                            {a.texto}
                                        </span>
                                        <span className="shrink-0 text-xs text-muted-foreground">
                                            {fechaRelativa(
                                                a.creado_en,
                                                i18n.language,
                                            )}
                                        </span>
                                    </Link>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}
