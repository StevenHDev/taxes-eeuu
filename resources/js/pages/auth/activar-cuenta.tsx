import { Form, Head, setLayoutProps } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { request } from '@/routes/password';

type Props = {
    /** false si la cuenta ya tiene contraseña (el enlace ya se usó). */
    vigente: boolean;
    /** La misma URL firmada: el formulario se envía ahí. */
    accion: string;
    passwordRules: string;
};

/**
 * Primera entrada desde el enlace que manda el agente por WhatsApp (F2.4):
 * el cliente crea su contraseña y pasa directo a firmar el consentimiento.
 * A propósito no muestra ningún dato de la cuenta (ni el correo).
 */
export default function ActivarCuenta({
    vigente,
    accion,
    passwordRules,
}: Props) {
    const { t } = useTranslation();

    setLayoutProps({
        title: t('auth.activarCuenta.layoutTitle'),
        description: vigente
            ? t('auth.activarCuenta.layoutDescription')
            : t('auth.activarCuenta.usadoDescription'),
    });

    if (!vigente) {
        return (
            <>
                <Head title={t('auth.activarCuenta.headTitle')} />
                <div className="grid gap-3 text-center text-sm">
                    <TextLink href={login()}>
                        {t('auth.activarCuenta.irAlInicio')}
                    </TextLink>
                    <TextLink href={request()}>
                        {t('auth.activarCuenta.olvide')}
                    </TextLink>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title={t('auth.activarCuenta.headTitle')} />

            <Form
                action={accion}
                method="post"
                resetOnSuccess={['password', 'password_confirmation']}
            >
                {({ processing, errors }) => (
                    <div className="grid gap-6">
                        <InputError message={errors.enlace} />

                        <div className="grid gap-2">
                            <Label htmlFor="password">
                                {t('auth.activarCuenta.passwordLabel')}
                            </Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                autoComplete="new-password"
                                autoFocus
                                passwordrules={passwordRules}
                                aria-invalid={Boolean(errors.password)}
                            />
                            <InputError message={errors.password} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password_confirmation">
                                {t('auth.activarCuenta.confirmLabel')}
                            </Label>
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                autoComplete="new-password"
                                passwordrules={passwordRules}
                                aria-invalid={Boolean(
                                    errors.password_confirmation,
                                )}
                            />
                            <InputError
                                message={errors.password_confirmation}
                            />
                        </div>

                        <Button
                            type="submit"
                            className="w-full"
                            disabled={processing}
                        >
                            {processing ? <Spinner aria-hidden="true" /> : null}
                            {t('auth.activarCuenta.submit')}
                        </Button>

                        <p className="text-center text-xs text-muted-foreground">
                            {t('auth.activarCuenta.vence')}
                        </p>
                    </div>
                )}
            </Form>
        </>
    );
}
