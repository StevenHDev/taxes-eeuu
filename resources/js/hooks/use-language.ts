import { useTranslation } from 'react-i18next';
import { LANGUAGE_STORAGE_KEY, SUPPORTED_LANGUAGES } from '@/i18n';
import type { Language } from '@/i18n';

function setCookie(name: string, value: string, days = 365): void {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;
    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
}

/**
 * Idioma actual + setter que persiste (localStorage para el cliente, cookie para
 * el servidor/SSR) y actualiza el atributo lang del documento.
 */
export function useLanguage() {
    const { i18n } = useTranslation();
    const language = (i18n.language as Language) ?? 'en';

    const setLanguage = (next: Language): void => {
        i18n.changeLanguage(next);

        if (typeof window !== 'undefined') {
            localStorage.setItem(LANGUAGE_STORAGE_KEY, next);
            setCookie(LANGUAGE_STORAGE_KEY, next);
        }

        if (typeof document !== 'undefined') {
            document.documentElement.lang = next;
        }
    };

    return { language, setLanguage, languages: SUPPORTED_LANGUAGES } as const;
}
