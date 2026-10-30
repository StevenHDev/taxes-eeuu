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
 * Idioma inicial: preferencia guardada (localStorage o cookie); si no hay, el
 * del servidor (atributo `lang` de la página, ver AplicarIdioma), para que
 * la interfaz y lo que arma el servidor arranquen en el mismo idioma.
 */
export function getInitialLanguage(): Language {
    if (typeof window === 'undefined') {
        return DEFAULT_LANGUAGE;
    }

    const stored =
        localStorage.getItem(LANGUAGE_STORAGE_KEY) ??
        readCookie(LANGUAGE_STORAGE_KEY);

    if (isLanguage(stored)) {
        return stored;
    }

    const delServidor = document.documentElement.lang.slice(0, 2);

    return isLanguage(delServidor) ? delServidor : DEFAULT_LANGUAGE;
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
