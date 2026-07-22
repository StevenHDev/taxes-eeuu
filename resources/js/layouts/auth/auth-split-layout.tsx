import { Link } from '@inertiajs/react';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSplitLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="relative grid min-h-svh lg:grid-cols-2">
            <div className="relative hidden flex-col justify-between overflow-hidden bg-primary p-10 text-primary-foreground lg:flex">
                <div
                    aria-hidden
                    className="absolute -top-24 -right-24 size-72 rounded-full border border-white/10"
                />
                <div
                    aria-hidden
                    className="absolute top-16 -right-10 size-40 rounded-full border border-white/10"
                />
                <div
                    aria-hidden
                    className="absolute -bottom-16 -left-16 size-64 rotate-12 rounded-3xl border border-white/10"
                />

                <Link href={home()} className="relative z-10">
                    <img
                        src="/images/logo-mark.png"
                        alt="Global Tax Services"
                        className="h-11 w-auto brightness-0 invert"
                    />
                </Link>

                <div className="relative z-10 max-w-sm space-y-2">
                    <p className="text-2xl font-medium">
                        Recolección de datos para declaraciones de impuestos.
                    </p>
                    <p className="text-sm text-primary-foreground/70">
                        Un lugar centralizado para reunir la información de
                        cada cliente, campo a campo, y llevarla lista para la
                        preparación de su declaración.
                    </p>
                </div>
            </div>

            <div className="flex flex-col justify-center px-6 py-12 sm:px-12 lg:px-16">
                <div className="mx-auto w-full max-w-sm space-y-8">
                    <Link
                        href={home()}
                        className="flex justify-center lg:hidden"
                    >
                        <img
                            src="/images/logo.png"
                            alt="Global Tax Services"
                            className="h-10 w-auto"
                        />
                    </Link>

                    <div className="space-y-2 text-center lg:text-left">
                        <h1 className="text-xl font-semibold text-foreground">
                            {title}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {description}
                        </p>
                    </div>

                    {children}
                </div>
            </div>
        </div>
    );
}
