import { Form, Head, usePage } from '@inertiajs/react';
import { ShieldAlert } from 'lucide-react';
import { useRef } from 'react';
import { useTranslation } from 'react-i18next';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import type { Props as ManagePasskeysProps } from '@/components/manage-passkeys';
import ManagePasskeys from '@/components/manage-passkeys';
import type { Props as ManageTwoFactorProps } from '@/components/manage-two-factor';
import ManageTwoFactor from '@/components/manage-two-factor';
import PasswordInput from '@/components/password-input';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/security';
import type { Auth } from '@/types';

type Props = {
    passwordRules: string;
} & ManagePasskeysProps &
    ManageTwoFactorProps;

export default function Security(props: Props) {
    const { t } = useTranslation();
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);
    const { debeConfigurarMfa } = usePage<{ auth: Auth }>().props.auth;

    // F0.1: si al personal le falta el MFA, la verificación en dos pasos y
    // las passkeys van primero, porque es lo único que puede hacer aquí.
    const verificacion = (
        <>
            <ManageTwoFactor
                canManageTwoFactor={props.canManageTwoFactor}
                requiresConfirmation={props.requiresConfirmation}
                twoFactorEnabled={props.twoFactorEnabled}
            />

            <ManagePasskeys
                canManagePasskeys={props.canManagePasskeys}
                passkeys={props.passkeys}
            />
        </>
    );

    return (
        <>
            <Head title={t('settings.security.pageTitle')} />

            <h1 className="sr-only">{t('settings.security.pageTitle')}</h1>

            {debeConfigurarMfa ? (
                <Alert className="mb-6 border-amber-500/50 bg-amber-50 text-amber-950 dark:bg-amber-950/40 dark:text-amber-50">
                    <ShieldAlert aria-hidden="true" />
                    <AlertTitle>
                        {t('settings.security.mfaRequired.title')}
                    </AlertTitle>
                    <AlertDescription className="text-amber-900 dark:text-amber-100">
                        {t('settings.security.mfaRequired.description')}
                    </AlertDescription>
                </Alert>
            ) : null}

            {debeConfigurarMfa ? verificacion : null}

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={t('settings.security.updatePasswordTitle')}
                    description={t(
                        'settings.security.updatePasswordDescription',
                    )}
                />

                <Form
                    {...SecurityController.update.form()}
                    options={{
                        preserveScroll: true,
                    }}
                    resetOnError={[
                        'password',
                        'password_confirmation',
                        'current_password',
                    ]}
                    resetOnSuccess
                    onError={(errors) => {
                        if (errors.password) {
                            passwordInput.current?.focus();
                        }

                        if (errors.current_password) {
                            currentPasswordInput.current?.focus();
                        }
                    }}
                    className="space-y-6"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="current_password">
                                    {t(
                                        'settings.security.currentPasswordLabel',
                                    )}
                                </Label>

                                <PasswordInput
                                    id="current_password"
                                    ref={currentPasswordInput}
                                    name="current_password"
                                    className="mt-1 block w-full"
                                    autoComplete="current-password"
                                    placeholder={t(
                                        'settings.security.currentPasswordPlaceholder',
                                    )}
                                />

                                <InputError message={errors.current_password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">
                                    {t('settings.security.newPasswordLabel')}
                                </Label>

                                <PasswordInput
                                    id="password"
                                    ref={passwordInput}
                                    name="password"
                                    className="mt-1 block w-full"
                                    autoComplete="new-password"
                                    placeholder={t(
                                        'settings.security.newPasswordPlaceholder',
                                    )}
                                    passwordrules={props.passwordRules}
                                />

                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">
                                    {t(
                                        'settings.security.confirmPasswordLabel',
                                    )}
                                </Label>

                                <PasswordInput
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    className="mt-1 block w-full"
                                    autoComplete="new-password"
                                    placeholder={t(
                                        'settings.security.confirmPasswordPlaceholder',
                                    )}
                                    passwordrules={props.passwordRules}
                                />

                                <InputError
                                    message={errors.password_confirmation}
                                />
                            </div>

                            <div className="flex items-center gap-4">
                                <Button
                                    disabled={processing}
                                    data-test="update-password-button"
                                >
                                    {t('common.save')}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>

            {debeConfigurarMfa ? null : verificacion}
        </>
    );
}

Security.layout = {
    breadcrumbs: [
        {
            title: 'settings.security.pageTitle',
            href: edit(),
        },
    ],
};
