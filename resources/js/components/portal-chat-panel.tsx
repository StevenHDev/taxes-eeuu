import { useForm } from '@inertiajs/react';
import { Paperclip, Send } from 'lucide-react';
import { useEffect, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { send } from '@/routes/portal/chat';
import type { PortalMensaje } from '@/types/agente';

/**
 * Panel de chat del portal seguro — usado standalone en portal/chat.tsx y
 * embebido al lado del formulario en portal/formulario.tsx (ahí, el mismo
 * endpoint responde en modo "solo dudas": ver
 * AgenteConversacionalService::responder(), $canalPortal). El componente en
 * sí no sabe ni le importa en cuál de los dos contextos está.
 */
export function PortalChatPanel({ mensajes }: { mensajes: PortalMensaje[] }) {
    const { t } = useTranslation();
    const form = useForm<{ contenido: string; archivo: File | null }>({
        contenido: '',
        archivo: null,
    });
    const fileInputRef = useRef<HTMLInputElement>(null);
    const finalRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        finalRef.current?.scrollIntoView({ block: 'end' });
    }, [mensajes.length]);

    const enviar = () => {
        if (form.data.contenido.trim() === '' && !form.data.archivo) {
            return;
        }

        form.post(send.url(), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.setData({ contenido: '', archivo: null });

                if (fileInputRef.current) {
                    fileInputRef.current.value = '';
                }
            },
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
                <input
                    ref={fileInputRef}
                    id="portal_chat_archivo"
                    type="file"
                    className="hidden"
                    onChange={(e) =>
                        form.setData('archivo', e.target.files?.[0] ?? null)
                    }
                />
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    title={t('portalChat.attach')}
                    onClick={() => fileInputRef.current?.click()}
                >
                    <Paperclip className="size-4" />
                </Button>

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

            {form.data.archivo && (
                <p className="mt-1 text-xs text-muted-foreground">
                    {form.data.archivo.name}
                </p>
            )}
        </div>
    );
}
