import { Link, usePage } from '@inertiajs/react';
import { Megaphone } from 'lucide-react';

import { NotificationBell } from '@/components/layout/NotificationBell';
import { UserMenu } from '@/components/layout/UserMenu';
import { useSidebar } from '@/components/ui/sidebar';
import { useT } from '@/providers/I18nProvider';

function brandTitle(auth, brand) {
    const company = auth.user?.company?.name;
    const short = company || brand?.short || brand?.name || 'SaleOps';

    return `${short}.ADMIN`.toUpperCase();
}

export function AppHeader() {
    const { auth, brand } = usePage().props;
    const { toggleSidebar } = useSidebar();
    const t = useT();

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
                <NotificationBell pushsaleStyle />

                <Link href="/notifications" className="nav-item-btn" title={t('notifications.title')}>
                    <Megaphone className="size-[18px]" strokeWidth={2} />
                </Link>

                <UserMenu variant="header" />
            </div>
        </header>
    );
}
