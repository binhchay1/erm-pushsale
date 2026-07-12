import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/providers/I18nProvider';
import { usePage } from '@inertiajs/react';
import { Globe2 } from 'lucide-react';

export function LanguageToggle({ pushsaleStyle = false }) {
    const { locale, setLocale } = useI18n();
    const { locales: localeMeta } = usePage().props;

    const options = [
        { id: 'vi', label: localeMeta?.vi?.label ?? 'Tiếng Việt', short: localeMeta?.vi?.short ?? 'VI' },
        { id: 'en', label: localeMeta?.en?.label ?? 'English', short: localeMeta?.en?.short ?? 'EN' },
    ];
    const current = options.find((option) => option.id === locale) ?? options[0];

    if (pushsaleStyle) {
        return (
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <button type="button" className="pushsale-language-trigger" title={current.label} aria-label={current.label}>
                        <i className="fa fa-globe" aria-hidden="true" />
                        <span>{current.short}</span>
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" sideOffset={0} className="pushsale-language-dropdown">
                    {options.map((option) => (
                        <DropdownMenuItem
                            key={option.id}
                            className={`pushsale-language-dropdown-item ${locale === option.id ? 'is-active' : ''}`}
                            onClick={() => setLocale(option.id)}
                        >
                            <i className={`fa ${locale === option.id ? 'fa-check' : 'fa-circle-o'}`} aria-hidden="true" />
                            <span>{option.label}</span>
                        </DropdownMenuItem>
                    ))}
                </DropdownMenuContent>
            </DropdownMenu>
        );
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button type="button" variant="ghost" size="sm" className="gap-1.5 px-2 font-medium" title={current.label}>
                    <Globe2 className="size-4 shrink-0" />
                    <span className="text-xs">{current.short}</span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="min-w-36">
                {options.map((option) => (
                    <DropdownMenuItem
                        key={option.id}
                        className={locale === option.id ? 'bg-accent font-medium' : ''}
                        onClick={() => setLocale(option.id)}
                    >
                        {option.label}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
