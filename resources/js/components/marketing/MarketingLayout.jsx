import { Link, usePage } from '@inertiajs/react';
import { LayoutDashboard, Menu, X } from 'lucide-react';
import { useState } from 'react';

import { LanguageToggle } from '@/components/layout/LanguageToggle';
import { LocaleSync } from '@/components/layout/LocaleSync';
import { Button } from '@/components/ui/button';
import { useT } from '@/providers/I18nProvider';
import { cn } from '@/lib/utils';

const NAV = [
    { href: '/features', key: 'marketing.nav_features' },
    { href: '/solutions', key: 'marketing.nav_solutions' },
    { href: '/about', key: 'marketing.nav_about' },
    { href: '/contact', key: 'marketing.nav_contact' },
];

function BrandMark({ name }) {
    return (
        <Link href="/" className="flex items-center gap-2">
            <div className="flex size-9 items-center justify-center rounded-lg bg-primary text-primary-foreground shadow-sm">
                <LayoutDashboard className="size-5" />
            </div>
            <span className="text-lg font-bold tracking-tight">{name}</span>
        </Link>
    );
}

export function MarketingLayout({ children }) {
    const page = usePage();
    const { brand, auth } = page.props;
    const currentUrl = page.url;
    const t = useT();
    const name = brand?.name ?? 'ERM SaleOps';
    const isAuthed = !!auth?.user;
    const [open, setOpen] = useState(false);

    return (
        <div className="flex min-h-screen flex-col bg-background text-foreground">
            <LocaleSync />

            <header className="sticky top-0 z-40 border-b border-border/60 bg-background/80 backdrop-blur supports-[backdrop-filter]:bg-background/60">
                <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3.5">
                    <BrandMark name={name} />

                    <nav className="hidden items-center gap-1 md:flex">
                        {NAV.map((item) => {
                            const active = currentUrl.startsWith(item.href);
                            return (
                                <Link
                                    key={item.href}
                                    href={item.href}
                                    className={cn(
                                        'rounded-md px-3 py-2 text-sm font-medium transition-colors',
                                        active ? 'text-primary' : 'text-muted-foreground hover:text-foreground',
                                    )}
                                >
                                    {t(item.key)}
                                </Link>
                            );
                        })}
                    </nav>

                    <div className="hidden items-center gap-2 md:flex">
                        <LanguageToggle />
                        <Button asChild>
                            <Link href={isAuthed ? '/' : '/login'}>
                                {isAuthed ? t('marketing.go_to_app') : t('marketing.sign_in')}
                            </Link>
                        </Button>
                    </div>

                    <div className="flex items-center gap-1 md:hidden">
                        <LanguageToggle />
                        <Button
                            variant="ghost"
                            size="icon"
                            onClick={() => setOpen((v) => !v)}
                            aria-label="Menu"
                        >
                            {open ? <X className="size-5" /> : <Menu className="size-5" />}
                        </Button>
                    </div>
                </div>

                {open && (
                    <div className="border-t border-border/60 bg-background md:hidden">
                        <nav className="mx-auto flex max-w-6xl flex-col gap-1 px-4 py-3">
                            {NAV.map((item) => (
                                <Link
                                    key={item.href}
                                    href={item.href}
                                    onClick={() => setOpen(false)}
                                    className="rounded-md px-3 py-2 text-sm font-medium text-muted-foreground hover:bg-muted hover:text-foreground"
                                >
                                    {t(item.key)}
                                </Link>
                            ))}
                            <Button asChild className="mt-2">
                                <Link href={isAuthed ? '/' : '/login'}>
                                    {isAuthed ? t('marketing.go_to_app') : t('marketing.sign_in')}
                                </Link>
                            </Button>
                        </nav>
                    </div>
                )}
            </header>

            <main className="flex-1">{children}</main>

            <footer className="border-t border-border/60 bg-muted/30">
                <div className="mx-auto grid max-w-6xl gap-8 px-4 py-12 sm:grid-cols-2 lg:grid-cols-4">
                    <div className="space-y-3">
                        <BrandMark name={name} />
                        <p className="max-w-xs text-sm text-muted-foreground">{t('marketing.footer_built')}</p>
                    </div>

                    <div>
                        <h3 className="text-sm font-semibold">{t('marketing.footer_product')}</h3>
                        <ul className="mt-3 space-y-2 text-sm text-muted-foreground">
                            <li><Link href="/features" className="hover:text-foreground">{t('marketing.nav_features')}</Link></li>
                            <li><Link href="/solutions" className="hover:text-foreground">{t('marketing.nav_solutions')}</Link></li>
                            <li><Link href="/login" className="hover:text-foreground">{t('marketing.sign_in')}</Link></li>
                        </ul>
                    </div>

                    <div>
                        <h3 className="text-sm font-semibold">{t('marketing.footer_company')}</h3>
                        <ul className="mt-3 space-y-2 text-sm text-muted-foreground">
                            <li><Link href="/about" className="hover:text-foreground">{t('marketing.nav_about')}</Link></li>
                            <li><Link href="/contact" className="hover:text-foreground">{t('marketing.nav_contact')}</Link></li>
                        </ul>
                    </div>

                    <div>
                        <h3 className="text-sm font-semibold">{t('marketing.footer_get_in_touch')}</h3>
                        <ul className="mt-3 space-y-2 text-sm text-muted-foreground">
                            <li>{t('marketing.contact_email_value')}</li>
                            <li>{t('marketing.contact_hours_value')}</li>
                        </ul>
                    </div>
                </div>

                <div className="border-t border-border/60 py-5 text-center text-xs text-muted-foreground">
                    © {new Date().getFullYear()} {name}. {t('marketing.footer_rights')}
                </div>
            </footer>
        </div>
    );
}
