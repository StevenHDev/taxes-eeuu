import { Link } from '@inertiajs/react';
import { ArrowRight, Users } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useInitials } from '@/hooks/use-initials';
import { index as clientesIndex, show as clienteShow } from '@/routes/clientes';

export function UltimosClientes({
    clientes,
}: {
    clientes: { id: number; name: string }[];
}) {
    const { t } = useTranslation();
    const getInitials = useInitials();

    return (
        <Card>
            <CardHeader className="flex-row items-center justify-between space-y-0">
                <CardTitle className="flex items-center gap-2 text-sm">
                    <Users aria-hidden="true" className="size-4" />
                    {t('panel.ultimos.titulo')}
                </CardTitle>
                <Link
                    href={clientesIndex()}
                    className="inline-flex min-h-11 items-center gap-1 px-1 text-xs font-medium text-primary hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    {t('panel.ultimos.verTodos')}
                    <ArrowRight aria-hidden="true" className="size-3" />
                </Link>
            </CardHeader>
            <CardContent>
                {clientes.length === 0 ? (
                    <p className="py-4 text-sm text-muted-foreground">
                        {t('panel.ultimos.vacio')}
                    </p>
                ) : (
                    <ul className="-mx-2">
                        {clientes.map((c) => (
                            <li key={c.id}>
                                <Link
                                    href={clienteShow(c.id)}
                                    className="flex min-h-11 items-center gap-3 rounded-lg px-2 py-2 transition-colors hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none active:bg-accent/70"
                                >
                                    <Avatar className="size-8">
                                        <AvatarFallback className="bg-secondary text-xs text-secondary-foreground">
                                            {getInitials(c.name)}
                                        </AvatarFallback>
                                    </Avatar>
                                    <span className="flex-1 truncate text-sm">
                                        {c.name}
                                    </span>
                                    <ArrowRight
                                        aria-hidden="true"
                                        className="size-4 shrink-0 text-muted-foreground"
                                    />
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}
