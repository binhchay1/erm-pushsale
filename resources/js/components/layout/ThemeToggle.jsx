import { Monitor, Moon, Palette, Sun } from 'lucide-react';
import { usePage } from '@inertiajs/react';

import { Button } from '@/components/ui/button';
import { useTheme, useThemePreview } from '@/providers/ThemeProvider';
import { applyAppearance } from '@/lib/themes';

const cycle = ['light', 'dark', 'system'];

export function ThemeToggle() {
    const { appearance, setAppearance } = useTheme();
    const preview = useThemePreview();
    const { themes, preferences } = usePage().props;
    const themeIds = Object.keys(themes ?? {});

    const nextAppearance = () => {
        const idx = cycle.indexOf(appearance);
        const next = cycle[(idx + 1) % cycle.length];
        setAppearance(next);
        applyAppearance(next);
    };

    const cycleTheme = () => {
        const current = preferences?.theme ?? 'brand';
        const idx = themeIds.indexOf(current);
        const nextId = themeIds[(idx + 1) % themeIds.length] ?? 'brand';
        preview(nextId);
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
                title="Đổi màu theme"
            >
                <Palette className="size-4" />
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="icon-sm"
                onClick={nextAppearance}
                title="Sáng / Tối / Hệ thống"
            >
                <Icon className="size-4" />
            </Button>
        </div>
    );
}
