export const THEME_STORAGE_KEY = 'saleops-theme';
export const APPEARANCE_STORAGE_KEY = 'saleops-appearance';

const VALID_APPEARANCES = ['light', 'dark', 'system'];

export function getStoredTheme() {
    try {
        return localStorage.getItem(THEME_STORAGE_KEY);
    } catch {
        return null;
    }
}

export function getStoredAppearance() {
    try {
        const value = localStorage.getItem(APPEARANCE_STORAGE_KEY);
        return VALID_APPEARANCES.includes(value) ? value : null;
    } catch {
        return null;
    }
}

export function setStoredTheme(theme) {
    try {
        localStorage.setItem(THEME_STORAGE_KEY, theme);
    } catch {
        /* ignore */
    }
}

export function setStoredAppearance(appearance) {
    try {
        localStorage.setItem(APPEARANCE_STORAGE_KEY, appearance);
    } catch {
        /* ignore */
    }
}

export function resolveTheme(preferences, themes) {
    const stored = getStoredTheme();
    if (stored && themes?.[stored]) {
        return stored;
    }

    if (preferences?.theme && themes?.[preferences.theme]) {
        return preferences.theme;
    }

    return 'brand';
}

export function resolveAppearance(preferences) {
    const stored = getStoredAppearance();
    if (stored) {
        return stored;
    }

    return preferences?.appearance ?? 'system';
}
