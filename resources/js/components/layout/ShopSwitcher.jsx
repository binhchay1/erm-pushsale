import { router, usePage } from '@inertiajs/react';

import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useT } from '@/providers/I18nProvider';

export function ShopSwitcher({ pushsaleStyle = false }) {
    const t = useT();
    const { shops = [], current_shop: currentShop, auth } = usePage().props;
    const list = Array.isArray(shops) && shops.length
        ? shops
        : (auth?.user?.shops ?? []);
    const current = currentShop ?? auth?.user?.current_shop ?? null;

    if (!list.length) {
        return null;
    }

    const switchTo = (shopId) => {
        if (current?.id === shopId) return;
        router.post('/shop/current', { shop_id: shopId, remember_default: true }, {
            preserveScroll: true,
        });
    };

    if (pushsaleStyle) {
        return (
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <button
                        type="button"
                        className="pushsale-language-trigger"
                        title={t('shops.switcher_title')}
                        aria-label={t('shops.switcher_title')}
                    >
                        <i className="fa fa-store" aria-hidden="true" />
                        <span>{current?.name ?? t('shops.switcher_placeholder')}</span>
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" sideOffset={0} className="pushsale-language-dropdown">
                    {list.map((shop) => (
                        <DropdownMenuItem
                            key={shop.id}
                            className={`pushsale-language-dropdown-item ${current?.id === shop.id ? 'is-active' : ''}`}
                            onClick={() => switchTo(shop.id)}
                        >
                            <i className={`fa ${current?.id === shop.id ? 'fa-check' : 'fa-circle-o'}`} aria-hidden="true" />
                            <span>{shop.name}</span>
                        </DropdownMenuItem>
                    ))}
                </DropdownMenuContent>
            </DropdownMenu>
        );
    }

    return null;
}
