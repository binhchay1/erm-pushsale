import { Monitor, Moon, Palette, Sun } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { useTheme } from '@/providers/ThemeProvider';
import { useT } from '@/providers/I18nProvider';

const cycle = ['light', 'dark', 'system'];

export function ThemeToggle() {
    const { theme, appearance, setTheme, setAppearance, themes } = useTheme();
    const t = useT();
    const themeIds = Object.keys(themes ?? {});

    const nextAppearance = () => {
        const idx = cycle.indexOf(appearance);
        const next = cycle[(idx + 1) % cycle.length];
        setAppearance(next);
    };

    const cycleTheme = () => {
        const idx = themeIds.indexOf(theme);
        const nextId = themeIds[(idx + 1) % themeIds.length] ?? 'brand';
        setTheme(nextId);
    };

    const Icon =
        appearance === 'dark' ? Moon : appearance === 'light' ? Sun : Monitor;

    return (
        <div className="flex items-center gap-1">
            <Button
                type="button"
                variant="ghost"
                size="icon-sm"
                onClick={cycleTheme}
                title={t('common.theme_color')}
            >
                <Palette className="size-4" />
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="icon-sm"
                onClick={nextAppearance}
                title={t('common.appearance')}
            >
                <Icon className="size-4" />
            </Button>
        </div>
    );
}
