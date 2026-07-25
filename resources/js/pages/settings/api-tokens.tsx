import { Form, Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import ApiTokenController from '@/actions/App/Http/Controllers/Settings/ApiTokenController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { index as apiDocsIndex } from '@/routes/api-docs';
import { index as apiTokensIndex } from '@/routes/api-tokens';
import type { ApiToken, ApiTokenAbilityOption } from '@/types';

export default function ApiTokens({
    tokens,
    abilities,
}: {
    tokens: ApiToken[];
    abilities: ApiTokenAbilityOption[];
}) {
    const { t } = useTranslation();
    const [justCreatedToken, setJustCreatedToken] = useState<string | null>(
        null,
    );

    useEffect(() => {
        return router.on('flash', (event) => {
            const flash = (
                event as CustomEvent<{ flash?: Record<string, unknown> }>
            ).detail?.flash;
            const token = flash?.apiToken;

            if (typeof token === 'string') {
                setJustCreatedToken(token);
            }
        });
    }, []);

    return (
        <>
            <Head title={t('settings.apiTokens.pageTitle')} />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={t('settings.apiTokens.title')}
                    description={t('settings.apiTokens.description')}
                />

                <Link
                    href={apiDocsIndex()}
                    className="text-sm text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                >
                    {t('settings.apiTokens.viewDocs')}
                </Link>

                {justCreatedToken && (
                    <div className="space-y-2 rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-900 dark:bg-green-950">
                        <p className="text-sm font-medium">
                            {t('settings.apiTokens.copyTokenNotice')}
                        </p>
                        <code className="block overflow-x-auto rounded bg-background p-2 text-xs">
                            {justCreatedToken}
                        </code>
                        <Button
                            variant="secondary"
                            size="sm"
                            onClick={() => setJustCreatedToken(null)}
                        >
                            {t('settings.apiTokens.gotIt')}
                        </Button>
                    </div>
                )}

                <Form
                    {...ApiTokenController.store.form()}
                    resetOnSuccess
                    className="max-w-xl space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">
                                    {t('settings.apiTokens.nameLabel')}
                                </Label>
                                <Input
                                    id="name"
                                    name="name"
                                    required
                                    placeholder={t(
                                        'settings.apiTokens.namePlaceholder',
                                    )}
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label>
                                    {t('settings.apiTokens.permissionsLabel')}
                                </Label>
                                {abilities.map((ability) => (
                                    <label
                                        key={ability.value}
                                        className="flex items-center gap-2 text-sm"
                                    >
                                        <input
                                            type="checkbox"
                                            name="abilities[]"
                                            value={ability.value}
                                            defaultChecked={
                                                ability.value !==
                                                'tax-documents:reveal-ssn'
                                            }
                                            className="size-4 rounded border-input"
                                        />
                                        {ability.label}
                                    </label>
                                ))}
                                <InputError message={errors.abilities} />
                            </div>

                            <Button disabled={processing}>
                                {t('settings.apiTokens.createToken')}
                            </Button>
                        </>
                    )}
                </Form>

                <div className="overflow-hidden rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>
                                    {t('settings.apiTokens.nameColumn')}
                                </TableHead>
                                <TableHead>
                                    {t('settings.apiTokens.permissionsColumn')}
                                </TableHead>
                                <TableHead>
                                    {t('settings.apiTokens.lastUsedColumn')}
                                </TableHead>
                                <TableHead className="text-right">
                                    {t('common.actions')}
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {tokens.map((token) => (
                                <TableRow key={token.id}>
                                    <TableCell>{token.name}</TableCell>
                                    <TableCell>
                                        <div className="flex flex-wrap gap-1">
                                            {token.abilities.map((ability) => (
                                                <Badge
                                                    key={ability}
                                                    variant="secondary"
                                                >
                                                    {ability}
                                                </Badge>
                                            ))}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        {token.last_used_at ??
                                            t('settings.apiTokens.never')}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Dialog>
                                            <DialogTrigger asChild>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    className="text-red-600"
                                                >
                                                    {t(
                                                        'settings.apiTokens.revoke',
                                                    )}
                                                </Button>
                                            </DialogTrigger>
                                            <DialogContent>
                                                <DialogTitle>
                                                    {t(
                                                        'settings.apiTokens.revokeConfirmTitle',
                                                    )}
                                                </DialogTitle>
                                                <DialogDescription>
                                                    {t(
                                                        'settings.apiTokens.revokeConfirmDescription',
                                                    )}
                                                </DialogDescription>
                                                <DialogFooter className="gap-2">
                                                    <DialogClose asChild>
                                                        <Button variant="secondary">
                                                            {t('common.cancel')}
                                                        </Button>
                                                    </DialogClose>
                                                    <Form
                                                        {...ApiTokenController.destroy.form(
                                                            token.id,
                                                        )}
                                                    >
                                                        {({ processing }) => (
                                                            <Button
                                                                variant="destructive"
                                                                disabled={
                                                                    processing
                                                                }
                                                                asChild
                                                            >
                                                                <button type="submit">
                                                                    {t(
                                                                        'settings.apiTokens.revoke',
                                                                    )}
                                                                </button>
                                                            </Button>
                                                        )}
                                                    </Form>
                                                </DialogFooter>
                                            </DialogContent>
                                        </Dialog>
                                    </TableCell>
                                </TableRow>
                            ))}

                            {tokens.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={4}
                                        className="text-center text-muted-foreground"
                                    >
                                        {t('settings.apiTokens.emptyState')}
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>
            </div>
        </>
    );
}

ApiTokens.layout = {
    breadcrumbs: [
        {
            title: 'settings.apiTokens.pageTitle',
            href: apiTokensIndex(),
        },
    ],
};
