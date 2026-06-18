/** @typedef {{ label: string, description?: string, primary: string, primary_foreground: string, chart?: string[] }} ThemeConfig */

/**
 * Apply theme preset to document (CSS variables).
 * @param {string} themeId
 * @param {Record<string, ThemeConfig>} themesFromServer
 */
export function applyTheme(themeId, themesFromServer) {
    const theme = themesFromServer?.[themeId] ?? themesFromServer?.brand;
    if (!theme) return;

    const root = document.documentElement;
    root.dataset.theme = themeId;
    root.style.setProperty('--primary', theme.primary);
    root.style.setProperty('--primary-foreground', theme.primary_foreground);
    root.style.setProperty('--sidebar-primary', theme.primary);
    root.style.setProperty('--ring', theme.primary);

    if (theme.chart?.length) {
        root.style.setProperty('--chart-1', theme.chart[0]);
        root.style.setProperty('--chart-2', theme.chart[1] ?? theme.chart[0]);
        root.style.setProperty('--chart-3', theme.chart[2] ?? theme.chart[0]);
    }
}

/**
 * @param {'light' | 'dark' | 'system'} appearance
 */
export function applyAppearance(appearance) {
    const root = document.documentElement;
    root.dataset.appearance = appearance;

    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    const isDark =
        appearance === 'dark' || (appearance === 'system' && prefersDark);

    root.classList.toggle('dark', isDark);
}
