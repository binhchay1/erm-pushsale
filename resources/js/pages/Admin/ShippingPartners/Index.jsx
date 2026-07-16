import { Head } from '@inertiajs/react';
import { useState } from 'react';

import { ShippingPartnerCard } from '@/components/shipping/ShippingPartnerCard';
import AppLayout from '@/layouts/AppLayout';

const providerOrder = ['vnpost', 'viettel_post', 'ghtk', 'ghn', 'jnt', 'ems', 'supership', 'best', 'boxme', 'chimcat', 'ship60', 'holaship', 'ahamove', 'ninjavan', 'spx', 'aggregator'];
const providerLabels = {
    vnpost: 'VN Post',
    viettel_post: 'Viettel Post',
    ghtk: 'Giao hàng tiết kiệm',
    ghn: 'Giao hàng nhanh',
    jnt: 'J&T',
    ems: 'EMS',
    supership: 'SuperShip',
    best: 'Best',
    boxme: 'BoxMe',
    chimcat: 'Chim Cắt',
    ship60: 'Ship60',
    holaship: 'HolaShip',
    ahamove: 'AhaMove',
    ninjavan: 'NinjaVan',
    spx: 'SPX Express',
    aggregator: 'Đối tác trung gian',
};

export default function ShippingPartnersIndex({ providers = [] }) {
    const orderedProviders = providerOrder
        .map((key) => providers.find((provider) => provider.provider === key))
        .filter(Boolean);
    const fallbackProviders = providers.filter((provider) => !providerOrder.includes(provider.provider) && provider.provider !== 'manual');
    const allProviders = [...orderedProviders, ...fallbackProviders];
    const [activeKey, setActiveKey] = useState(allProviders[0]?.provider ?? providers[0]?.provider);
    const active = allProviders.find((item) => item.provider === activeKey) ?? allProviders[0];

    return (
        <AppLayout activeMenuCode="1.4">
            <Head title="Cấu hình giao hàng" />
            <section className="pssp-page">
                <div className="pssp-box">
                    <h2>CẤU HÌNH GIAO HÀNG</h2>
                    <div className="pssp-layout">
                        <aside className="pssp-provider-menu">
                            {allProviders.map((provider) => (
                                <button
                                    key={provider.provider}
                                    type="button"
                                    className={provider.provider === active?.provider ? 'active' : ''}
                                    onClick={() => setActiveKey(provider.provider)}
                                >
                                    {providerLabels[provider.provider] ?? provider.label}
                                </button>
                            ))}
                        </aside>
                        <main className="pssp-panel">
                            {active && <ShippingPartnerCard key={active.provider} provider={active} />}
                        </main>
                    </div>
                </div>
            </section>
        </AppLayout>
    );
}
