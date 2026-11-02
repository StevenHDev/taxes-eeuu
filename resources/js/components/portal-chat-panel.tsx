import { useForm } from '@inertiajs/react';
import { Send } from 'lucide-react';
import { useEffect, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { send } from '@/routes/portal/chat';
import type { PortalMensaje } from '@/types/agente';

/**
 * Panel del chat de dudas del portal — usado standalone en portal/chat.tsx y
 * al lado de la entrevista (chat-dudas). Solo texto: los documentos se suben
 * en la entrevista (F2.6).
 */
export function PortalChatPanel({ mensajes }: { mensajes: PortalMensaje[] }) {
    const { t } = useTranslation();
    const form = useForm<{ contenido: string }>({ contenido: '' });
    const finalRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        finalRef.current?.scrollIntoView({ block: 'end' });
    }, [mensajes.length]);

    const enviar = () => {
        if (form.data.contenido.trim() === '') {
            return;
        }

        form.post(send.url(), {
            preserveScroll: true,
            onSuccess: () => form.setData('contenido', ''),
        });
    };

    return (
        <div className="flex h-full min-h-0 flex-col">
            <div className="min-h-0 flex-1 space-y-3 overflow-y-auto rounded-lg border p-4">
                {mensajes.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        {t('portalChat.empty')}
                    </p>
                )}

                {mensajes.map((mensaje) => (
                    <div
                        key={mensaje.id}
                        className={`flex ${mensaje.rol === 'cliente' ? 'justify-end' : 'justify-start'}`}
                    >
                        <div
                            className={`max-w-[80%] rounded-lg px-3 py-2 text-sm whitespace-pre-wrap ${
                                mensaje.rol === 'cliente'
                                    ? 'bg-primary text-primary-foreground'
                                    : 'bg-muted text-foreground'
                            }`}
                        >
                            {mensaje.contenido}
                        </div>
                    </div>
                ))}
                <div ref={finalRef} />
            </div>

            {form.errors.contenido && (
                <p className="mt-2 text-sm text-destructive">
                    {form.errors.contenido}
                </p>
            )}

            <div className="mt-3 flex items-end gap-2">
                <Textarea
                    id="portal_chat_contenido"
                    value={form.data.contenido}
                    onChange={(e) => form.setData('contenido', e.target.value)}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter' && !e.shiftKey) {
                            e.preventDefault();
                            enviar();
                        }
                    }}
                    placeholder={t('portalChat.placeholder')}
                    rows={1}
                    className="resize-none"
                />

                <Button
                    type="button"
                    onClick={enviar}
                    disabled={form.processing}
                >
                    <Send className="size-4" />
                    {form.processing
                        ? t('portalChat.sending')
                        : t('portalChat.send')}
                </Button>
            </div>
        </div>
    );
}
