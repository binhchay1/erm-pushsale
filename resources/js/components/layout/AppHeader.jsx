import { Link, usePage } from '@inertiajs/react';

import { LanguageToggle } from '@/components/layout/LanguageToggle';
import { NotificationBell } from '@/components/layout/NotificationBell';
import { PageInfoButton } from '@/components/layout/PageInfoButton';
import { ShopSwitcher } from '@/components/layout/ShopSwitcher';
import { UserMenu } from '@/components/layout/UserMenu';

function currentUserTitle(user, brand) {
    const name = String(
        user?.name
            || user?.display_name
            || user?.full_name
            || user?.email
            || brand?.admin_name
            || 'Admin'
    ).trim();

    return name || 'Admin';
}

function dashboardForRole(role) {
    return {
        admin: '/admin/dashboard',
        sales: '/sales/dashboard',
        marketing: '/marketing/dashboard',
        warehouse: '/warehouse/dashboard',
        accounting: '/accounting/dashboard',
        allocator: '/allocator/dashboard',
    }[role] ?? '/';
}

export function AppHeader({ onToggleSidebar }) {
    const { brand, auth } = usePage().props;
    const currentUserName = currentUserTitle(auth?.user, brand);
    const dashboardUrl = dashboardForRole(auth?.user?.role);

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
                <Link href={dashboardUrl} className="logo pushsale-logo" title={currentUserName}>
                    {currentUserName}
                </Link>
            </div>

            <div className="pushsale-header-spacer" />

            <div className="pushsale-header-tools">
                <PageInfoButton className="is-header" />
                <LanguageToggle pushsaleStyle />
                <NotificationBell pushsaleStyle />
                <span className="pushsale-header-icon" aria-hidden="true" title="Thông báo hệ thống">
                    <i className="fa fa-bullhorn" />
                </span>
                <ShopSwitcher />
                <UserMenu variant="pushsale" />
            </div>
        </header>
    );
}
