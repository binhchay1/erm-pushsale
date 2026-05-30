import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';

import {
    getStoredAppearance,
    getStoredTheme,
    resolveAppearance,
    resolveTheme,
    setStoredAppearance,
    setStoredTheme,
} from '@/lib/themeStorage';
import { applyAppearance, applyTheme } from '@/lib/themes';

const ThemeContext = createContext(null);

export function ThemeProvider({ children, preferences, themes }) {
    const [theme, setThemeState] = useState(() => resolveTheme(preferences, themes));
    const [appearance, setAppearanceState] = useState(() => resolveAppearance(preferences));
    const seededFromServer = useRef(false);

    const setTheme = useCallback(
        (nextTheme) => {
            setThemeState(nextTheme);
            setStoredTheme(nextTheme);
            applyTheme(nextTheme, themes);
        },
        [themes]
    );

    const setAppearance = useCallback((nextAppearance) => {
        setAppearanceState(nextAppearance);
        setStoredAppearance(nextAppearance);
        applyAppearance(nextAppearance);
    }, []);

    useEffect(() => {
        applyTheme(theme, themes);
    }, [theme, themes]);

    useEffect(() => {
        applyAppearance(appearance);

        const mq = window.matchMedia('(prefers-color-scheme: dark)');
        const handler = () => applyAppearance(appearance);
        mq.addEventListener('change', handler);

        return () => mq.removeEventListener('change', handler);
    }, [appearance]);

    useEffect(() => {
        if (seededFromServer.current) {
            return;
        }

        seededFromServer.current = true;

        if (!getStoredTheme() && preferences?.theme) {
            setStoredTheme(preferences.theme);
        }

        if (!getStoredAppearance() && preferences?.appearance) {
            setStoredAppearance(preferences.appearance);
        }
    }, [preferences?.theme, preferences?.appearance]);

    const value = useMemo(
        () => ({
            theme,
            appearance,
            setTheme,
            setAppearance,
            themes,
            applyLocal: (nextTheme, nextAppearance) => {
                if (nextTheme) {
                    setTheme(nextTheme);
                }
                if (nextAppearance) {
                    setAppearance(nextAppearance);
                }
            },
        }),
        [theme, appearance, themes, setTheme, setAppearance]
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
    const { themes } = useTheme();
    return useCallback((themeId) => applyTheme(themeId, themes), [themes]);
}
