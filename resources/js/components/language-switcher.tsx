import { Check } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useLanguage } from '@/hooks/use-language';
import type { Language } from '@/i18n';
import { cn } from '@/lib/utils';

const LANGUAGE_LABELS: Record<Language, string> = {
    en: 'English',
    es: 'Español',
};

// App de impuestos de EE.UU.: inglés → bandera de EE.UU., español → España.
const LANGUAGE_FLAGS: Record<Language, string> = {
    en: '🇺🇸',
    es: '🇪🇸',
};

export function LanguageSwitcher() {
    const { t } = useTranslation();
    const { language, setLanguage, languages } = useLanguage();

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="h-9 w-9"
                    aria-label={t('language.change')}
                    title={t('language.change')}
                >
                    <span className="text-lg leading-none">
                        {LANGUAGE_FLAGS[language]}
                    </span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                {languages.map((lng) => (
                    <DropdownMenuItem
                        key={lng}
                        onClick={() => setLanguage(lng)}
                        className="cursor-pointer"
                    >
                        <Check
                            className={cn(
                                'mr-2 size-4',
                                language === lng ? 'opacity-100' : 'opacity-0',
                            )}
                        />
                        <span className="mr-2 text-base leading-none">
                            {LANGUAGE_FLAGS[lng]}
                        </span>
                        {LANGUAGE_LABELS[lng]}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
