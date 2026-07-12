import { Link, usePage } from '@inertiajs/react';

import { UserMenu } from '@/components/layout/UserMenu';

function brandTitle(brand) {
    const configured = String(brand?.admin_name || 'TTGROUP2.ADMIN').trim();
    const normalized = configured.includes('.') ? configured : `${configured}.ADMIN`;

    return normalized.replace(/\s+/g, '').toUpperCase();
}

export function AppHeader({ onToggleSidebar }) {
    const { auth, brand } = usePage().props;

    return (
        <header className="main-header pushsale-main-header">
            <div className="pushsale-header-brand">
                <button
                    type="button"
                    className="sidebar-toggle pushsale-sidebar-toggle"
                    onClick={onToggleSidebar}
                    aria-label="Thu gọn hoặc mở menu"
                >
                    <i className="fa fa-bars" aria-hidden="true" />
                </button>
                <Link href="/" className="logo pushsale-logo" title={brandTitle(brand)}>
                    {brandTitle(brand)}
                </Link>
            </div>

            <div className="pushsale-header-spacer" />

            <div className="pushsale-header-tools">
                <span className="pushsale-security-score">Điểm bảo mật: 1/18</span>
                <Link
                    href="/notifications"
                    className="pushsale-header-icon"
                    aria-label="Thông báo"
                    title="Thông báo"
                >
                    <i className="fa fa-bullhorn" aria-hidden="true" />
                </Link>
                <UserMenu variant="pushsale" />
            </div>
        </header>
    );
}
