import { Head, Link, router } from '@inertiajs/react';
import { Trash2, UserPlus } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { MarcoEntrevista } from '@/components/entrevista/marco-entrevista';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { persona as rutaPersona } from '@/routes/portal/entrevista';
import { destroy, store } from '@/routes/portal/entrevista/personas';
import type { PortalMensaje } from '@/types/agente';

type Persona = {
    id: number;
    orden: number;
    etiqueta: string;
    total: number;
    respondidas: number;
};

/**
 * Pantalla 4 de la entrevista (F2.5): las personas que vivieron con el
 * cliente o que mantuvo. Cada una se responde en su propio paso; quitarla
 * la archiva.
 */
export default function PersonasEntrevista({
    personas,
    anterior,
    siguiente,
    mensajes,
}: {
    taxYear: number;
    personas: Persona[];
    anterior: string;
    siguiente: string;
    mensajes?: PortalMensaje[];
}) {
    const { t } = useTranslation();
    const [agregando, setAgregando] = useState(false);
    const [quitar, setQuitar] = useState<Persona | null>(null);
    const [quitando, setQuitando] = useState(false);

    const agregar = () =>
        router.post(
            store().url,
            {},
            {
                onStart: () => setAgregando(true),
                onFinish: () => setAgregando(false),
            },
        );

    const confirmarQuitar = () => {
        if (!quitar) {
            return;
        }

        router.delete(destroy({ persona: quitar.id }).url, {
            preserveScroll: true,
            onStart: () => setQuitando(true),
            onFinish: () => {
                setQuitando(false);
                setQuitar(null);
            },
        });
    };

    return (
        <>
            <Head title={t('entrevista.personas.titulo')} />

            <MarcoEntrevista
                pantalla={4}
                titulo={t('entrevista.personas.titulo')}
                descripcion={t('entrevista.personas.descripcion')}
                anterior={anterior}
                mensajes={mensajes}
                accion={
                    <Button asChild className="w-full sm:w-auto">
                        <Link href={siguiente} prefetch>
                            {t('entrevista.continuar')}
                        </Link>
                    </Button>
                }
            >
                {personas.length === 0 ? (
                    <p className="rounded-lg border border-dashed border-border p-4 text-sm text-muted-foreground">
                        {t('entrevista.personas.vacio')}
                    </p>
                ) : (
                    <ul className="space-y-2">
                        {personas.map((p) => {
                            const completa =
                                p.total > 0 && p.respondidas >= p.total;

                            return (
                                <li
                                    key={p.id}
                                    className="flex items-center gap-3 rounded-lg border border-border bg-card p-3"
                                >
                                    <div className="min-w-0 flex-1 space-y-1">
                                        <p className="text-sm font-medium">
                                            {t('entrevista.personas.nombre', {
                                                orden: p.orden,
                                            })}
                                        </p>
                                        <Badge
                                            variant={
                                                completa
                                                    ? 'secondary'
                                                    : 'outline'
                                            }
                                        >
                                            {completa
                                                ? t(
                                                      'entrevista.estado.completa',
                                                  )
                                                : t(
                                                      'entrevista.personas.avance',
                                                      {
                                                          respondidas:
                                                              p.respondidas,
                                                          total: p.total,
                                                      },
                                                  )}
                                        </Badge>
                                    </div>
                                    <Button variant="outline" size="sm" asChild>
                                        <Link
                                            href={rutaPersona({
                                                persona: p.id,
                                            })}
                                        >
                                            {completa
                                                ? t(
                                                      'entrevista.personas.revisar',
                                                  )
                                                : t(
                                                      'entrevista.personas.responder',
                                                  )}
                                        </Link>
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t(
                                            'entrevista.personas.quitarA',
                                            {
                                                etiqueta: t(
                                                    'entrevista.personas.nombre',
                                                    { orden: p.orden },
                                                ),
                                            },
                                        )}
                                        onClick={() => setQuitar(p)}
                                    >
                                        <Trash2 aria-hidden="true" />
                                    </Button>
                                </li>
                            );
                        })}
                    </ul>
                )}

                <Button
                    variant="outline"
                    onClick={agregar}
                    disabled={agregando}
                    className="w-full"
                >
                    {agregando ? (
                        <Spinner aria-hidden="true" />
                    ) : (
                        <UserPlus aria-hidden="true" />
                    )}
                    {t('entrevista.personas.agregar')}
                </Button>
            </MarcoEntrevista>

            <Dialog
                open={quitar !== null}
                onOpenChange={(abierto) => !abierto && setQuitar(null)}
            >
                <DialogContent>
                    <DialogTitle>
                        {t('entrevista.personas.quitarTitulo', {
                            etiqueta: quitar
                                ? t('entrevista.personas.nombre', {
                                      orden: quitar.orden,
                                  })
                                : '',
                        })}
                    </DialogTitle>
                    <DialogDescription>
                        {t('entrevista.personas.quitarDescripcion')}
                    </DialogDescription>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="ghost">
                                {t('common.cancel')}
                            </Button>
                        </DialogClose>
                        <Button
                            variant="destructive"
                            onClick={confirmarQuitar}
                            disabled={quitando}
                        >
                            {quitando ? <Spinner aria-hidden="true" /> : null}
                            {t('entrevista.personas.quitar')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
