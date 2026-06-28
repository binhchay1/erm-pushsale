import { Globe2 } from 'lucide-react';

import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useI18n } from '@/providers/I18nProvider';
import { usePage } from '@inertiajs/react';

export function LanguageToggle() {
    const { locale, setLocale } = useI18n();
    const { locales: localeMeta } = usePage().props;

    const options = [
        { id: 'vi', label: localeMeta?.vi?.label ?? 'Tiếng Việt', short: localeMeta?.vi?.short ?? 'VI' },
        { id: 'en', label: localeMeta?.en?.label ?? 'English', short: localeMeta?.en?.short ?? 'EN' },
    ];

    const current = options.find((o) => o.id === locale) ?? options[0];

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="gap-1.5 px-2 font-medium"
                    title={current.label}
                >
                    <Globe2 className="size-4 shrink-0" />
                    <span className="text-xs">{current.short}</span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="min-w-36">
                {options.map((opt) => (
                    <DropdownMenuItem
                        key={opt.id}
                        className={locale === opt.id ? 'bg-accent font-medium' : ''}
                        onClick={() => setLocale(opt.id)}
                    >
                        {opt.label}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
