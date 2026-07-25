import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';
import en from './locales/en.json';
import es from './locales/es.json';

export const SUPPORTED_LANGUAGES = ['en', 'es'] as const;
export type Language = (typeof SUPPORTED_LANGUAGES)[number];

// El proyecto arranca en inglés por defecto; el español es el idioma alterno.
export const DEFAULT_LANGUAGE: Language = 'en';
export const LANGUAGE_STORAGE_KEY = 'language';

export function isLanguage(
    value: string | null | undefined,
): value is Language {
    return value === 'en' || value === 'es';
}

function readCookie(name: string): string | null {
    if (typeof document === 'undefined') {
        return null;
    }

    const match = document.cookie.match(
        new RegExp('(?:^|; )' + name + '=([^;]*)'),
    );

    return match ? decodeURIComponent(match[1]) : null;
}

/**
 * Idioma inicial: preferencia guardada (localStorage o cookie) o, si no hay,
 * inglés por defecto.
 */
export function getInitialLanguage(): Language {
    if (typeof window === 'undefined') {
        return DEFAULT_LANGUAGE;
    }

    const stored =
        localStorage.getItem(LANGUAGE_STORAGE_KEY) ??
        readCookie(LANGUAGE_STORAGE_KEY);

    return isLanguage(stored) ? stored : DEFAULT_LANGUAGE;
}

i18n.use(initReactI18next).init({
    resources: {
        en: { translation: en },
        es: { translation: es },
    },
    lng: getInitialLanguage(),
    fallbackLng: DEFAULT_LANGUAGE,
    interpolation: { escapeValue: false },
    react: { useSuspense: false },
});

if (typeof document !== 'undefined') {
    document.documentElement.lang = i18n.language;
}

export default i18n;
