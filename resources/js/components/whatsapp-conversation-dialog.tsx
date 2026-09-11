import type { FormEvent, ReactNode } from 'react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import { conversacionWhatsapp } from '@/routes/clientes';
import {
    devolverControl as devolverControlWhatsapp,
    enviar as enviarMensajeWhatsapp,
    tomarControl as tomarControlWhatsapp,
} from '@/routes/clientes/whatsapp';
import type { ControlWhatsapp, MensajeWhatsapp } from '@/types';

/**
 * Diálogo de conversación de WhatsApp de un cliente puntual — usado tanto en
 * el detalle del cliente (trigger de texto) como en el listado (ícono),
 * ver `trigger`. Solo se muestra cuando el cliente tiene teléfono (quien lo
 * usa decide si renderizarlo o no).
 */
export function WhatsappConversationDialog({
    clienteId,
    clienteName,
    trigger,
}: {
    clienteId: number;
    clienteName: string;
    trigger?: ReactNode;
}) {
    const { t, i18n } = useTranslation();
    const [open, setOpen] = useState(false);
    const [mensajes, setMensajes] = useState<MensajeWhatsapp[] | null>(null);
    const [control, setControl] = useState<ControlWhatsapp | null>(null);
    const [error, setError] = useState(false);
    const [cambiandoControl, setCambiandoControl] = useState(false);
    const [textoManual, setTextoManual] = useState('');
    const [enviando, setEnviando] = useState(false);

    const csrfToken = () =>
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? '';

    const formatDate = (iso: string | null): string => {
        if (!iso) {
            return '';
        }

        const fecha = new Date(iso);

        if (Number.isNaN(fecha.getTime())) {
            return iso;
        }

        // La conversación es con clientes en Colombia: se muestra siempre en
        // esa zona horaria, sin importar dónde esté el navegador de quien mira.
        return new Intl.DateTimeFormat(i18n.language, {
            dateStyle: 'medium',
            timeStyle: 'short',
            timeZone: 'America/Bogota',
        }).format(fecha);
    };

    const load = async () => {
        setError(false);
        setMensajes(null);

        try {
            const response = await fetch(
                conversacionWhatsapp({ cliente: clienteId }).url,
                { headers: { Accept: 'application/json' } },
            );

            if (!response.ok) {
                throw new Error('request_failed');
            }

            const json = await response.json();
            setMensajes(json.mensajes ?? []);
            setControl(json.control ?? null);
        } catch {
            setError(true);
            setMensajes([]);
        }
    };

    // Refresco silencioso mientras el diálogo está abierto: sin esto, la
    // conversación solo se actualiza al reabrir el diálogo. No usa `load()`
    // tal cual porque esa limpia `mensajes` antes de traer los nuevos (buen
    // spinner en la carga inicial, parpadeo feo cada 5s) y no debe tapar una
    // conversación ya cargada con el estado de error por un solo tick fallido
    // (la red de un momento a otro, no algo que el usuario deba ver).
    useEffect(() => {
        if (!open) {
            return;
        }

        const intervalo = setInterval(async () => {
            try {
                const response = await fetch(
                    conversacionWhatsapp({ cliente: clienteId }).url,
                    { headers: { Accept: 'application/json' } },
                );

                if (!response.ok) {
                    return;
                }

                const json = await response.json();
                setMensajes(json.mensajes ?? []);
                setControl(json.control ?? null);
            } catch {
                // Silencioso a propósito — ver comentario arriba.
            }
        }, 5000);

        return () => clearInterval(intervalo);
    }, [open, clienteId]);

    const cambiarControl = async (
        accion: typeof tomarControlWhatsapp | typeof devolverControlWhatsapp,
    ) => {
        setCambiandoControl(true);

        try {
            const response = await fetch(accion({ cliente: clienteId }).url, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
            });

            if (!response.ok) {
                return;
            }

            const json = await response.json();
            setControl(json.control ?? null);
        } finally {
            setCambiandoControl(false);
        }
    };

    const enviarMensaje = async (e: FormEvent) => {
        e.preventDefault();

        const mensaje = textoManual.trim();

        if (!mensaje || enviando) {
            return;
        }

        setEnviando(true);

        try {
            const response = await fetch(
                enviarMensajeWhatsapp({ cliente: clienteId }).url,
                {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                    body: JSON.stringify({ mensaje }),
                },
            );

            if (!response.ok) {
                return;
            }

            const json = await response.json();
            setMensajes((prev) => [...(prev ?? []), json.mensaje]);
            setTextoManual('');
        } finally {
            setEnviando(false);
        }
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(nuevoOpen) => {
                setOpen(nuevoOpen);

                if (nuevoOpen) {
                    load();
                }
            }}
        >
            <DialogTrigger asChild>
                {trigger ?? (
                    <Button variant="secondary">
                        {t('clienteShow.whatsapp.trigger')}
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent className="flex max-h-[85vh] flex-col sm:max-w-lg">
                <DialogTitle>
                    {t('clienteShow.whatsapp.title', { name: clienteName })}
                </DialogTitle>
                {control && (
                    <div className="flex items-center justify-between gap-2 rounded-md border bg-muted/40 px-3 py-2 text-sm">
                        <span className="text-muted-foreground">
                            {control.estado === 'humano'
                                ? t('clienteShow.whatsapp.control.humano', {
                                      name:
                                          control.tomado_por ??
                                          t(
                                              'clienteShow.whatsapp.control.alguien',
                                          ),
                                  })
                                : t('clienteShow.whatsapp.control.agente')}
                        </span>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={cambiandoControl}
                            onClick={() =>
                                cambiarControl(
                                    control.estado === 'humano'
                                        ? devolverControlWhatsapp
                                        : tomarControlWhatsapp,
                                )
                            }
                        >
                            {control.estado === 'humano'
                                ? t('clienteShow.whatsapp.control.devolver')
                                : t('clienteShow.whatsapp.control.tomar')}
                        </Button>
                    </div>
                )}
                <div className="flex-1 space-y-3 overflow-y-auto">
                    {mensajes === null && !error && (
                        <p className="text-sm text-muted-foreground">
                            {t('common.loading')}
                        </p>
                    )}
                    {error && (
                        <p className="text-sm text-destructive">
                            {t('clienteShow.whatsapp.error')}
                        </p>
                    )}
                    {mensajes?.length === 0 && !error && (
                        <p className="text-sm text-muted-foreground">
                            {t('clienteShow.whatsapp.empty')}
                        </p>
                    )}
                    {mensajes?.map((mensaje, i) => {
                        const esHumano = mensaje.role === 'human';
                        const esPreparador = mensaje.role === 'preparador';

                        return (
                            <div
                                key={i}
                                className={`flex ${esHumano ? 'justify-end' : 'justify-start'}`}
                            >
                                <div
                                    className={`max-w-[80%] rounded-lg px-3 py-2 text-sm ${
                                        esHumano
                                            ? 'bg-primary text-primary-foreground'
                                            : esPreparador
                                              ? 'bg-accent text-accent-foreground'
                                              : 'bg-muted text-foreground'
                                    }`}
                                >
                                    <div className="mb-1 text-xs opacity-70">
                                        {t(
                                            `clienteShow.whatsapp.role.${mensaje.role}`,
                                        )}
                                        {mensaje.created_at
                                            ? ` · ${formatDate(mensaje.created_at)}`
                                            : ''}
                                    </div>
                                    <div className="whitespace-pre-wrap">
                                        {mensaje.content}
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                </div>
                {control?.estado === 'humano' && (
                    <form
                        onSubmit={enviarMensaje}
                        className="flex items-end gap-2 border-t pt-3"
                    >
                        <Textarea
                            value={textoManual}
                            onChange={(e) => setTextoManual(e.target.value)}
                            placeholder={t(
                                'clienteShow.whatsapp.manualPlaceholder',
                            )}
                            className="min-h-16 flex-1 resize-none"
                        />
                        <Button
                            type="submit"
                            disabled={enviando || !textoManual.trim()}
                        >
                            {t('clienteShow.whatsapp.send')}
                        </Button>
                    </form>
                )}
            </DialogContent>
        </Dialog>
    );
}
