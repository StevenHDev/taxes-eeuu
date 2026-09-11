import { Head, router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import { dashboard } from '@/routes';
import {
    borrador as guardarBorradorRoute,
    index as promptsIndex,
    publicar as publicarRoute,
} from '@/routes/agente/prompts';
import type { FasePrompt } from '@/types';

export default function AgentePrompts({
    fases,
    versionVigente,
    versionBorrador,
    hayBorrador,
}: {
    fases: FasePrompt[];
    versionVigente: number | null;
    versionBorrador: number;
    hayBorrador: boolean;
}) {
    const { t } = useTranslation();
    const [confirmando, setConfirmando] = useState(false);
    const [publicando, setPublicando] = useState(false);

    const form = useForm<{ fases: Record<string, string> }>({
        fases: Object.fromEntries(fases.map((f) => [f.fase, f.contenido])),
    });

    const guardar = (e: FormEvent) => {
        e.preventDefault();
        form.post(guardarBorradorRoute().url, { preserveScroll: true });
    };

    const publicar = () => {
        setPublicando(true);

        router.post(
            publicarRoute().url,
            {},
            {
                preserveScroll: true,
                onFinish: () => {
                    setPublicando(false);
                    setConfirmando(false);
                },
            },
        );
    };

    return (
        <>
            <Head title={t('agente.prompts.title')} />

            <div className="flex flex-col gap-1">
                <h1 className="text-xl font-semibold">
                    {t('agente.prompts.title')}
                </h1>
                <p className="text-sm text-muted-foreground">
                    {t('agente.prompts.subtitle')}
                </p>
            </div>

            <div className="flex flex-wrap items-center gap-2">
                <Badge variant="secondary">
                    {t('agente.prompts.versionVigente', {
                        version: versionVigente ?? '—',
                    })}
                </Badge>
                {hayBorrador && (
                    <Badge variant="outline">
                        {t('agente.prompts.borradorPendiente', {
                            version: versionBorrador,
                        })}
                    </Badge>
                )}
            </div>

            <form onSubmit={guardar} className="space-y-6">
                {fases.map((fase) => (
                    <div key={fase.fase} className="space-y-2">
                        <label className="text-sm font-medium text-foreground">
                            {fase.label}
                        </label>
                        <Textarea
                            value={form.data.fases[fase.fase] ?? ''}
                            onChange={(e) =>
                                form.setData('fases', {
                                    ...form.data.fases,
                                    [fase.fase]: e.target.value,
                                })
                            }
                            className="min-h-64 font-mono text-xs"
                        />
                    </div>
                ))}

                <div className="flex items-center gap-2">
                    <Button type="submit" disabled={form.processing}>
                        {t('agente.prompts.guardarBorrador')}
                    </Button>

                    <Dialog open={confirmando} onOpenChange={setConfirmando}>
                        <DialogTrigger asChild>
                            <Button
                                type="button"
                                variant="destructive"
                                disabled={!hayBorrador}
                            >
                                {t('agente.prompts.publicar')}
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogTitle>
                                {t('agente.prompts.confirmarPublicar.title')}
                            </DialogTitle>
                            <p className="text-sm text-muted-foreground">
                                {t(
                                    'agente.prompts.confirmarPublicar.description',
                                    { version: versionBorrador },
                                )}
                            </p>
                            <div className="flex justify-end gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setConfirmando(false)}
                                >
                                    {t('common.cancel')}
                                </Button>
                                <Button
                                    type="button"
                                    variant="destructive"
                                    onClick={publicar}
                                    disabled={publicando}
                                >
                                    {t(
                                        'agente.prompts.confirmarPublicar.confirm',
                                    )}
                                </Button>
                            </div>
                        </DialogContent>
                    </Dialog>
                </div>
            </form>
        </>
    );
}

AgentePrompts.layout = {
    breadcrumbs: [
        { title: 'nav.dashboard', href: dashboard() },
        { title: 'agente.nav.prompts', href: promptsIndex() },
    ],
};
