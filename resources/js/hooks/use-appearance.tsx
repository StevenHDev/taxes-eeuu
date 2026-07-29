// La aplicación quedó fijada en **modo claro**. Este hook conserva su API previa
// (tipos y firmas) para no tener que tocar a sus consumidores (sonner, el modal de
// 2FA, etc.), pero siempre resuelve a 'light' y `updateAppearance` es un no-op.

export type ResolvedAppearance = 'light' | 'dark';
export type Appearance = ResolvedAppearance | 'system';

export type UseAppearanceReturn = {
    readonly appearance: Appearance;
    readonly resolvedAppearance: ResolvedAppearance;
    readonly updateAppearance: (mode: Appearance) => void;
};

const applyLight = (): void => {
    if (typeof document === 'undefined') {
        return;
    }

    document.documentElement.classList.remove('dark');
    document.documentElement.style.colorScheme = 'light';
};

export function initializeTheme(): void {
    applyLight();
}

export function useAppearance(): UseAppearanceReturn {
    return {
        appearance: 'light',
        resolvedAppearance: 'light',
        updateAppearance: () => {},
    } as const;
}
