import { usePage } from '@inertiajs/react';
import { Check } from 'lucide-react';

import { useTheme } from '@/providers/ThemeProvider';
import { useT } from '@/providers/I18nProvider';
import { cn } from '@/lib/utils';

export function ThemeSettings({ value }) {
    const { themes } = usePage().props;
    const { setTheme } = useTheme();

    const entries = Object.entries(themes ?? {});

    return (
        <div className="grid gap-3 sm:grid-cols-2">
            {entries.map(([id, theme]) => (
                <button
                    key={id}
                    type="button"
                    onClick={() => setTheme(id)}
                    className={cn(
                        'relative flex flex-col items-start rounded-xl border p-4 text-left transition-all hover:border-primary/50 hover:shadow-sm',
                        value === id
                            ? 'border-primary bg-primary/5 ring-2 ring-primary/20'
                            : 'border-border bg-card'
                    )}
                >
                    <div className="mb-3 flex w-full gap-1.5">
                        {(theme.chart ?? ['#3b82f6']).map((c) => (
                            <span
                                key={c}
                                className="h-8 flex-1 rounded-md"
                                style={{ background: c }}
                            />
                        ))}
                    </div>
                    <span className="font-medium text-foreground">{theme.label}</span>
                    <span className="text-xs text-muted-foreground">{theme.description}</span>
                    {value === id && (
                        <Check className="absolute top-3 right-3 size-4 text-primary" />
                    )}
                </button>
            ))}
        </div>
    );
}

export function AppearanceSettings({ value }) {
    const t = useT();
    const { setAppearance } = useTheme();
    const options = [
        { id: 'light', label: t('common.appearance_light') },
        { id: 'dark', label: t('common.appearance_dark') },
        { id: 'system', label: t('common.appearance_system') },
    ];

    return (
        <div className="flex flex-wrap gap-2">
            {options.map((opt) => (
                <button
                    key={opt.id}
                    type="button"
                    onClick={() => setAppearance(opt.id)}
                    className={cn(
                        'rounded-lg border px-4 py-2 text-sm font-medium transition-colors',
                        value === opt.id
                            ? 'border-primary bg-primary text-primary-foreground'
                            : 'border-border bg-background hover:bg-muted'
                    )}
                >
                    {opt.label}
                </button>
            ))}
        </div>
    );
}
