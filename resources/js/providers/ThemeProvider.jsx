import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { usePage } from '@inertiajs/react';

import { applyAppearance, applyTheme } from '@/lib/themes';

const ThemeContext = createContext(null);

export function ThemeProvider({ children }) {
    const { preferences, themes } = usePage().props;
    const [theme, setTheme] = useState(preferences?.theme ?? 'brand');
    const [appearance, setAppearance] = useState(preferences?.appearance ?? 'system');

    useEffect(() => {
        if (preferences?.theme) setTheme(preferences.theme);
        if (preferences?.appearance) setAppearance(preferences.appearance);
    }, [preferences?.theme, preferences?.appearance]);

    useEffect(() => {
        applyTheme(theme, themes);
    }, [theme, themes]);

    useEffect(() => {
        applyAppearance(appearance);
        try {
            localStorage.setItem('saleops-appearance', appearance);
        } catch {
            /* ignore */
        }
        const mq = window.matchMedia('(prefers-color-scheme: dark)');
        const handler = () => applyAppearance(appearance);
        mq.addEventListener('change', handler);
        return () => mq.removeEventListener('change', handler);
    }, [appearance]);

    const value = useMemo(
        () => ({
            theme,
            appearance,
            setTheme,
            setAppearance,
            applyLocal: (nextTheme, nextAppearance) => {
                if (nextTheme) {
                    setTheme(nextTheme);
                    applyTheme(nextTheme, themes);
                }
                if (nextAppearance) {
                    setAppearance(nextAppearance);
                    applyAppearance(nextAppearance);
                }
            },
        }),
        [theme, appearance, themes]
    );

    return <ThemeContext.Provider value={value}>{children}</ThemeContext.Provider>;
}

export function useTheme() {
    const ctx = useContext(ThemeContext);
    if (!ctx) {
        throw new Error('useTheme must be used within ThemeProvider');
    }
    return ctx;
}

/** Preview theme without persisting */
export function useThemePreview() {
    const { themes } = usePage().props;
    return useCallback((themeId) => applyTheme(themeId, themes), [themes]);
}
