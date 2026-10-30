import { router } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { destroy as clienteArchivar } from '@/routes/clientes';
import {
    destroy as usuarioArchivar,
    entrevistaCatalogo as usuarioEntrevistaCatalogo,
    forceDestroy as usuarioEliminarDefinitivo,
    prueba as usuarioMarcarPrueba,
    restore as usuarioRestaurar,
} from '@/routes/usuarios';

/**
 * Acciones de cuenta de F0.3: un usuario real solo se archiva (el IRS exige
 * conservar sus datos 3 años); solo un usuario marcado como prueba se elimina
 * definitivamente. Solo las ve el administrador.
 */
type Usuario = {
    id: number;
    name: string;
    phone: string | null;
    es_prueba: boolean;
};

// En la tabla de Usuarios los botones van en tamaño compacto.
type TamanoBoton = 'default' | 'sm';

export function MarcarPruebaButton({
    usuario,
    size = 'default',
}: {
    usuario: Usuario;
    size?: TamanoBoton;
}) {
    const { t } = useTranslation();

    return (
        <Button
            variant="outline"
            size={size}
            onClick={() =>
                router.patch(
                    usuarioMarcarPrueba(usuario.id).url,
                    { es_prueba: !usuario.es_prueba },
                    { preserveScroll: true },
                )
            }
        >
            {usuario.es_prueba
                ? t('cuentaUsuario.quitarPrueba')
                : t('cuentaUsuario.marcarPrueba')}
        </Button>
    );
}

/**
 * Bandera de F2.2: pasa a un cliente a la entrevista nueva sobre el
 * catálogo fiscal o lo devuelve al formulario actual. Hasta el cambio de
 * flujo (2.10) se usa solo con clientes de prueba.
 */
export function EntrevistaCatalogoButton({
    usuario,
    size = 'default',
}: {
    usuario: { id: number; name: string; entrevista_catalogo: boolean };
    size?: TamanoBoton;
}) {
    const { t } = useTranslation();
    const [enviando, setEnviando] = useState(false);
    const etiqueta = usuario.entrevista_catalogo
        ? t('cuentaUsuario.entrevistaCatalogo.quitar')
        : t('cuentaUsuario.entrevistaCatalogo.activar');

    return (
        <Button
            variant="outline"
            size={size}
            disabled={enviando}
            aria-label={`${etiqueta}: ${usuario.name}`}
            onClick={() =>
                router.patch(
                    usuarioEntrevistaCatalogo(usuario.id).url,
                    { entrevista_catalogo: !usuario.entrevista_catalogo },
                    {
                        preserveScroll: true,
                        onStart: () => setEnviando(true),
                        onFinish: () => setEnviando(false),
                    },
                )
            }
        >
            {enviando ? <Spinner aria-hidden="true" /> : null}
            {etiqueta}
        </Button>
    );
}

/**
 * `url` permite archivar desde la ficha del cliente (clientes.destroy, que
 * vuelve a la lista) o desde Usuarios (usuarios.destroy, que se queda ahí).
 */
export function RestaurarUsuarioButton({ usuario }: { usuario: Usuario }) {
    const { t } = useTranslation();

    return (
        <Button
            variant="outline"
            size="sm"
            onClick={() =>
                router.patch(
                    usuarioRestaurar(usuario.id).url,
                    {},
                    { preserveScroll: true },
                )
            }
        >
            {t('cuentaUsuario.restaurar')}
        </Button>
    );
}

export function ArchivarUsuarioDialog({
    usuario,
    url = usuarioArchivar(usuario.id).url,
    size = 'default',
}: {
    usuario: Usuario;
    url?: string;
    size?: TamanoBoton;
}) {
    const { t } = useTranslation();

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="destructive" size={size}>
                    {t('cuentaUsuario.archivar.trigger')}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>
                    {t('cuentaUsuario.archivar.title', { name: usuario.name })}
                </DialogTitle>
                <DialogDescription>
                    {t('cuentaUsuario.archivar.description')}
                </DialogDescription>
                <DialogFooter>
                    <Button
                        variant="destructive"
                        onClick={() =>
                            router.delete(url, { preserveScroll: true })
                        }
                    >
                        {t('cuentaUsuario.archivar.confirm')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export function EliminarDefinitivoDialog({
    usuario,
    size = 'default',
}: {
    usuario: Usuario;
    size?: TamanoBoton;
}) {
    const { t } = useTranslation();
    const [eliminarConversacion, setEliminarConversacion] = useState(false);

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="destructive" size={size}>
                    {t('cuentaUsuario.eliminarDefinitivo.trigger')}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>
                    {t('cuentaUsuario.eliminarDefinitivo.title', {
                        name: usuario.name,
                    })}
                </DialogTitle>
                <DialogDescription>
                    {t('cuentaUsuario.eliminarDefinitivo.description')}
                </DialogDescription>

                {usuario.phone ? (
                    <div className="flex items-start gap-2 rounded-md border border-amber-500/40 bg-amber-500/10 p-3">
                        <Checkbox
                            id={`eliminar-conversacion-${usuario.id}`}
                            checked={eliminarConversacion}
                            onCheckedChange={(v) =>
                                setEliminarConversacion(v === true)
                            }
                        />
                        <Label
                            htmlFor={`eliminar-conversacion-${usuario.id}`}
                            className="text-sm font-normal"
                        >
                            {t(
                                'cuentaUsuario.eliminarDefinitivo.alsoDeleteConversation',
                            )}
                        </Label>
                    </div>
                ) : null}

                <DialogFooter>
                    <Button
                        variant="destructive"
                        onClick={() =>
                            router.delete(
                                usuarioEliminarDefinitivo(usuario.id).url,
                                {
                                    data: {
                                        eliminar_conversacion_whatsapp:
                                            eliminarConversacion,
                                    },
                                },
                            )
                        }
                    >
                        {t('cuentaUsuario.eliminarDefinitivo.confirm')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

/**
 * Bloque completo para la ficha del cliente: marcar/quitar prueba y, según
 * la marca, archivar o eliminar definitivamente.
 */
export function ClienteCuentaAcciones({ cliente }: { cliente: Usuario }) {
    return (
        <>
            <MarcarPruebaButton usuario={cliente} />
            {cliente.es_prueba ? (
                <EliminarDefinitivoDialog usuario={cliente} />
            ) : (
                <ArchivarUsuarioDialog
                    usuario={cliente}
                    url={clienteArchivar(cliente.id).url}
                />
            )}
        </>
    );
}
