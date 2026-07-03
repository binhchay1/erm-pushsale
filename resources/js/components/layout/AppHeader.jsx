import { Link, usePage } from '@inertiajs/react';
import { Globe2, Megaphone } from 'lucide-react';

import { NotificationBell } from '@/components/layout/NotificationBell';
import { UserMenu } from '@/components/layout/UserMenu';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useSidebar } from '@/components/ui/sidebar';
import { useI18n, useT } from '@/providers/I18nProvider';

function brandTitle(auth, brand) {
    const company = auth.user?.company?.name;
    const short = company || brand?.short || brand?.name || 'SaleOps';

    return `${short}.ADMIN`.toUpperCase();
}

export function AppHeader() {
    const { auth, brand, locales: localeMeta } = usePage().props;
    const { toggleSidebar } = useSidebar();
    const { locale, setLocale } = useI18n();
    const t = useT();

    const languages = [
        { id: 'vi', label: localeMeta?.vi?.label ?? 'Tiếng Việt', short: localeMeta?.vi?.short ?? 'VI' },
        { id: 'en', label: localeMeta?.en?.label ?? 'English', short: localeMeta?.en?.short ?? 'EN' },
    ];
    const currentLanguage = languages.find((l) => l.id === locale) ?? languages[0];

    return (
        <header className="main-header">
            <button
                type="button"
                className="sidebar-toggle"
                onClick={toggleSidebar}
                aria-label="Toggle navigation"
            >
                <span className="sr-only">Toggle navigation</span>
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" strokeWidth="2.25" aria-hidden>
                    <path d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>

            <Link href="/" className="logo">
                {brandTitle(auth, brand)}
            </Link>

            <div className="header-ticker" title={brand?.tagline}>
                {brand?.tagline || t('dashboard.sidebar.admin_footer')}
            </div>

            <div className="navbar-custom-menu">
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <button
                            type="button"
                            className="nav-item-btn nav-item-lang"
                            title={currentLanguage.label}
                            aria-label={t('common.language') || currentLanguage.label}
                        >
                            <Globe2 className="size-[18px]" strokeWidth={2} />
                            <span className="nav-item-lang-code">{currentLanguage.short}</span>
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" className="min-w-36">
                        {languages.map((lang) => (
                            <DropdownMenuItem
                                key={lang.id}
                                className={locale === lang.id ? 'bg-accent font-medium' : ''}
                                onClick={() => setLocale(lang.id)}
                            >
                                {lang.label}
                            </DropdownMenuItem>
                        ))}
                    </DropdownMenuContent>
                </DropdownMenu>

                <NotificationBell pushsaleStyle />

                <Link href="/notifications" className="nav-item-btn" title={t('notifications.title')}>
                    <Megaphone className="size-[18px]" strokeWidth={2} />
                </Link>

                <UserMenu variant="header" />
            </div>
        </header>
    );
}
