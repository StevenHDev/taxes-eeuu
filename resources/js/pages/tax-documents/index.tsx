import { Form, Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import TaxDocumentController from '@/actions/App/Http/Controllers/TaxDocumentController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    create,
    download,
    edit,
    index,
    revealSsn,
} from '@/routes/tax-documents';
import type {
    Paginated,
    TaxDocument,
    TaxDocumentClient,
    TaxDocumentTypeOption,
} from '@/types';

function RevealSsnButton({ taxDocument }: { taxDocument: TaxDocument }) {
    const [revealed, setRevealed] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);
    const [needsConfirmation, setNeedsConfirmation] = useState(false);

    async function handleReveal() {
        setLoading(true);
        setNeedsConfirmation(false);

        try {
            const xsrfToken = decodeURIComponent(
                window.document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
            );

            const response = await fetch(revealSsn.url(taxDocument.id), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': xsrfToken,
                },
            });

            if (response.status === 423) {
                setNeedsConfirmation(true);

                return;
            }

            if (!response.ok) {
                return;
            }

            const data = await response.json();
            setRevealed(data.ssn_itin);
        } finally {
            setLoading(false);
        }
    }

    if (revealed) {
        return <span className="font-mono">{revealed}</span>;
    }

    return (
        <div className="flex items-center gap-2">
            <span className="font-mono">{taxDocument.ssn_itin_masked}</span>
            <Button
                type="button"
                variant="ghost"
                size="sm"
                disabled={loading}
                onClick={handleReveal}
            >
                Revelar
            </Button>
            {needsConfirmation && (
                <span className="text-xs text-muted-foreground">
                    Confirma tu contraseña en{' '}
                    <Link href="/settings/security" className="underline">
                        Configuración de seguridad
                    </Link>{' '}
                    e inténtalo de nuevo.
                </span>
            )}
        </div>
    );
}

export default function Index({
    documents,
    types,
    clients,
    filters,
}: {
    documents: Paginated<TaxDocument>;
    types: TaxDocumentTypeOption[];
    clients: TaxDocumentClient[];
    filters: { type?: string; fiscal_year?: string };
}) {
    function applyFilters(
        next: Partial<{ type: string; fiscal_year: string }>,
    ) {
        router.get(
            index.url(),
            { ...filters, ...next },
            { preserveState: true, replace: true },
        );
    }

    return (
        <>
            <Head title="Documentos fiscales" />

            <div className="space-y-6 px-4 py-6">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Documentos fiscales"
                        description="Información y comprobantes para preparar la declaración."
                    />

                    <Button asChild>
                        <Link href={create()}>Agregar documento</Link>
                    </Button>
                </div>

                <div className="flex flex-wrap items-center gap-4">
                    <Select
                        value={filters.type ?? 'all'}
                        onValueChange={(value) =>
                            applyFilters({
                                type: value === 'all' ? '' : value,
                            })
                        }
                    >
                        <SelectTrigger className="w-56">
                            <SelectValue placeholder="Todos los tipos" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Todos los tipos</SelectItem>
                            {types.map((option) => (
                                <SelectItem
                                    key={option.value}
                                    value={option.value}
                                >
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="overflow-hidden rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Tipo</TableHead>
                                <TableHead>Año fiscal</TableHead>
                                <TableHead>Título</TableHead>
                                {clients.length > 0 && (
                                    <TableHead>Cliente</TableHead>
                                )}
                                <TableHead>SSN/ITIN</TableHead>
                                <TableHead>Archivo</TableHead>
                                <TableHead className="text-right">
                                    Acciones
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {documents.data.map((doc) => (
                                <TableRow key={doc.id}>
                                    <TableCell>
                                        <Badge variant="secondary">
                                            {doc.type_label}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        {doc.fiscal_year ?? '—'}
                                    </TableCell>
                                    <TableCell>{doc.title}</TableCell>
                                    {clients.length > 0 && (
                                        <TableCell>
                                            {doc.user?.name ?? '—'}
                                        </TableCell>
                                    )}
                                    <TableCell>
                                        {doc.ssn_itin_masked ? (
                                            <RevealSsnButton
                                                taxDocument={doc}
                                            />
                                        ) : (
                                            '—'
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        {doc.file_original_name ? (
                                            <a
                                                href={download.url(doc.id)}
                                                className="underline"
                                            >
                                                {doc.file_original_name}
                                            </a>
                                        ) : (
                                            '—'
                                        )}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <div className="flex justify-end gap-2">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                asChild
                                            >
                                                <Link href={edit(doc.id)}>
                                                    Editar
                                                </Link>
                                            </Button>

                                            <Dialog>
                                                <DialogTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className="text-red-600"
                                                    >
                                                        Eliminar
                                                    </Button>
                                                </DialogTrigger>
                                                <DialogContent>
                                                    <DialogTitle>
                                                        ¿Eliminar este
                                                        documento?
                                                    </DialogTitle>
                                                    <DialogDescription>
                                                        Esta acción no se puede
                                                        deshacer.
                                                    </DialogDescription>
                                                    <DialogFooter className="gap-2">
                                                        <DialogClose asChild>
                                                            <Button variant="secondary">
                                                                Cancelar
                                                            </Button>
                                                        </DialogClose>
                                                        <Form
                                                            {...TaxDocumentController.destroy.form(
                                                                doc.id,
                                                            )}
                                                        >
                                                            {({
                                                                processing,
                                                            }) => (
                                                                <Button
                                                                    variant="destructive"
                                                                    disabled={
                                                                        processing
                                                                    }
                                                                    asChild
                                                                >
                                                                    <button type="submit">
                                                                        Eliminar
                                                                    </button>
                                                                </Button>
                                                            )}
                                                        </Form>
                                                    </DialogFooter>
                                                </DialogContent>
                                            </Dialog>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}

                            {documents.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={clients.length > 0 ? 7 : 6}
                                        className="text-center text-muted-foreground"
                                    >
                                        No hay documentos todavía.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>

                {(documents.prev_page_url || documents.next_page_url) && (
                    <div className="flex items-center justify-between">
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={!documents.prev_page_url}
                            asChild={!!documents.prev_page_url}
                        >
                            {documents.prev_page_url ? (
                                <Link href={documents.prev_page_url}>
                                    Anterior
                                </Link>
                            ) : (
                                <span>Anterior</span>
                            )}
                        </Button>
                        <span className="text-sm text-muted-foreground">
                            Página {documents.current_page} de{' '}
                            {documents.last_page}
                        </span>
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={!documents.next_page_url}
                            asChild={!!documents.next_page_url}
                        >
                            {documents.next_page_url ? (
                                <Link href={documents.next_page_url}>
                                    Siguiente
                                </Link>
                            ) : (
                                <span>Siguiente</span>
                            )}
                        </Button>
                    </div>
                )}
            </div>
        </>
    );
}

Index.layout = {
    breadcrumbs: [{ title: 'Documentos fiscales', href: index() }],
};
