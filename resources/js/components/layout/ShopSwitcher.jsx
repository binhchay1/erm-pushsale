import { Link, router, usePage } from '@inertiajs/react';

import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useT } from '@/providers/I18nProvider';

export function ShopSwitcher() {
    const t = useT();
    const { shops = [], current_shop: currentShop, auth } = usePage().props;
    const list = Array.isArray(shops) && shops.length
        ? shops
        : (auth?.user?.shops ?? []);
    const current = currentShop ?? auth?.user?.current_shop ?? null;
    const isAdmin = auth?.user?.role === 'admin' || auth?.user?.is_owner || auth?.user?.is_platform_admin;

    const switchTo = (shopId) => {
        if (current?.id === shopId) return;
        router.post('/shop/current', { shop_id: shopId, remember_default: true }, {
            preserveScroll: true,
            only: ['auth', 'shops', 'current_shop', 'flash'],
            onSuccess: () => router.reload({ preserveScroll: true }),
        });
    };

    // Chưa có cửa hàng: hiện CTA rõ (admin) hoặc nhãn trống (staff).
    if (!list.length) {
        if (!isAdmin) {
            return (
                <span className="pushsale-shop-switcher is-empty" title={t('shops.empty_state_title')}>
                    <i className="fa fa-store" aria-hidden="true" />
                    <span className="pushsale-shop-switcher__label">{t('shops.empty_state_staff')}</span>
                </span>
            );
        }

        return (
            <Link
                href="/admin/shops"
                className="pushsale-shop-switcher is-empty"
                title={t('shops.empty_state_title')}
            >
                <i className="fa fa-store" aria-hidden="true" />
                <span className="pushsale-shop-switcher__label">{t('shops.empty_state_admin')}</span>
            </Link>
        );
    }

    const label = current?.name ?? list[0]?.name ?? t('shops.switcher_placeholder');

    if (list.length === 1) {
        return (
            <span className="pushsale-shop-switcher is-single" title={t('shops.switcher_title')}>
                <i className="fa fa-store" aria-hidden="true" />
                <span className="pushsale-shop-switcher__label">{label}</span>
            </span>
        );
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    className="pushsale-shop-switcher"
                    title={t('shops.switcher_title')}
                    aria-label={t('shops.switcher_title')}
                >
                    <i className="fa fa-store" aria-hidden="true" />
                    <span className="pushsale-shop-switcher__label">{label}</span>
                    <i className="fa fa-caret-down pushsale-shop-switcher__caret" aria-hidden="true" />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" sideOffset={4} className="pushsale-shop-switcher-dropdown">
                {list.map((shop) => (
                    <DropdownMenuItem
                        key={shop.id}
                        className={`pushsale-shop-switcher-item ${current?.id === shop.id ? 'is-active' : ''}`}
                        onClick={() => switchTo(shop.id)}
                    >
                        <i className={`fa ${current?.id === shop.id ? 'fa-check' : 'fa-circle-o'}`} aria-hidden="true" />
                        <span>{shop.name}</span>
                    </DropdownMenuItem>
                ))}
                {isAdmin && (
                    <DropdownMenuItem asChild className="pushsale-shop-switcher-item is-manage">
                        <Link href="/admin/shops">
                            <i className="fa fa-cog" aria-hidden="true" />
                            <span>{t('shops.manage_link')}</span>
                        </Link>
                    </DropdownMenuItem>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
