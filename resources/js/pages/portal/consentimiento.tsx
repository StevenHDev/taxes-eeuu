import { Form, Head } from '@inertiajs/react';
import { TriangleAlert } from 'lucide-react';
import { useId, useState } from 'react';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/portal/consentimiento';

type Props = {
    texto: {
        version: number;
        titulo: string;
        /** Markdown del repositorio convertido en el servidor (sin HTML de entrada). */
        html: string;
        provisional: boolean;
    };
    taxYear: number;
    nombreSugerido: string;
};

/**
 * Consentimiento §7216 (F2.4): una pantalla de lectura con el texto, la
 * casilla "Leí y acepto", el nombre escrito como firma y un solo botón
 * principal. El botón queda deshabilitado hasta marcar la casilla y
 * escribir el nombre, y el motivo se lee junto a él (no solo por el color).
 */
export default function Consentimiento({
    texto,
    taxYear,
    nombreSugerido,
}: Props) {
    const { t } = useTranslation();
    const [acepto, setAcepto] = useState(false);
    const [nombre, setNombre] = useState('');
    const motivoId = useId();

    const listo = acepto && nombre.trim().length >= 3;
    const motivo = !acepto
        ? t('portalConsentimiento.faltaCasilla')
        : !listo
          ? t('portalConsentimiento.faltaNombre')
          : null;

    return (
        <>
            <Head title={texto.titulo} />

            <div className="mx-auto w-full max-w-3xl space-y-6 p-4 sm:p-6">
                <header className="space-y-1">
                    <p className="text-sm text-muted-foreground">
                        {t('portalConsentimiento.paso', { anio: taxYear })}
                    </p>
                    <h1 className="font-display text-display text-foreground">
                        {texto.titulo}
                    </h1>
                </header>

                {texto.provisional ? (
                    <div
                        role="note"
                        className="flex items-start gap-2 rounded-md border border-amber-500/40 bg-amber-500/10 p-3 text-sm text-foreground"
                    >
                        <TriangleAlert
                            className="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400"
                            aria-hidden="true"
                        />
                        {t('portalConsentimiento.provisional')}
                    </div>
                ) : null}

                <article
                    className="prose max-w-prose prose-neutral dark:prose-invert prose-headings:font-display"
                    dangerouslySetInnerHTML={{ __html: texto.html }}
                />

                <Form
                    {...store.form()}
                    transform={(data) => ({
                        ...data,
                        acepto,
                        version: texto.version,
                    })}
                    className="max-w-prose space-y-5 rounded-lg border border-border bg-card p-4 sm:p-5"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="flex items-start gap-3">
                                <Checkbox
                                    id="acepto"
                                    checked={acepto}
                                    onCheckedChange={(v) =>
                                        setAcepto(v === true)
                                    }
                                    aria-invalid={Boolean(errors.acepto)}
                                    className="mt-0.5"
                                />
                                <Label
                                    htmlFor="acepto"
                                    className="text-sm leading-snug font-normal"
                                >
                                    {t('portalConsentimiento.acepto')}
                                </Label>
                            </div>
                            <InputError message={errors.acepto} />

                            <div className="grid gap-2">
                                <Label htmlFor="nombre_escrito">
                                    {t('portalConsentimiento.nombreLabel')}
                                </Label>
                                <Input
                                    id="nombre_escrito"
                                    name="nombre_escrito"
                                    autoComplete="name"
                                    value={nombre}
                                    onChange={(e) => setNombre(e.target.value)}
                                    placeholder={nombreSugerido}
                                    aria-describedby="nombre-ayuda"
                                    aria-invalid={Boolean(
                                        errors.nombre_escrito,
                                    )}
                                />
                                <p
                                    id="nombre-ayuda"
                                    className="text-xs text-muted-foreground"
                                >
                                    {t('portalConsentimiento.nombreAyuda')}
                                </p>
                                <InputError message={errors.nombre_escrito} />
                            </div>

                            <InputError message={errors.version} />

                            <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                                <Button
                                    type="submit"
                                    disabled={!listo || processing}
                                    aria-describedby={
                                        motivo ? motivoId : undefined
                                    }
                                    className="w-full sm:w-auto"
                                >
                                    {processing ? (
                                        <Spinner aria-hidden="true" />
                                    ) : null}
                                    {t('portalConsentimiento.firmar')}
                                </Button>
                                {motivo ? (
                                    <p
                                        id={motivoId}
                                        className="text-sm text-muted-foreground"
                                    >
                                        {motivo}
                                    </p>
                                ) : null}
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
