import { Head, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';

import { ShippingPartnerCard } from '@/components/shipping/ShippingPartnerCard';
import { PageHeader } from '@/components/layout/PageHeader';
import AppLayout from '@/layouts/AppLayout';

const providerOrder = [
    'vnpost',
    'viettel_post',
    'ghtk',
    'ghn',
    'jnt',
    'ems',
    'supership',
    'best',
    'boxme',
    'chimcat',
    'ship60',
    'holaship',
    'ahamove',
    'ninjavan',
    'spx',
];

const providerLabels = {
    manual: 'Thủ công',
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
    tiktok_logistics: 'TikTok',
    shopee_logistics: 'Shopee',
    aggregator: 'Đối tác trung gian',
};

function providerLabel(provider) {
    return providerLabels[provider?.provider] ?? provider?.label ?? provider?.value ?? '';
}

function ShippingDefaultPanel({ providers = [], defaultConfig = {} }) {
    const providerOptions = useMemo(() => {
        const indexed = new Map(providers.map((provider) => [provider.provider, provider]));
        const ordered = ['manual', ...providerOrder]
            .map((key) => indexed.get(key))
            .filter(Boolean);
        const fallback = providers.filter((provider) => !ordered.some((item) => item.provider === provider.provider));

        return [...ordered, ...fallback].map((provider) => ({
            value: provider.provider,
            label: providerLabel(provider),
            services: provider.services ?? [],
        }));
    }, [providers]);

    const form = useForm({
        provider: defaultConfig.provider ?? 'manual',
        method: defaultConfig.method ?? 'manual',
    });

    const selectedProvider = providerOptions.find((provider) => provider.value === form.data.provider) ?? providerOptions[0];
    const serviceOptions = selectedProvider?.services?.length
        ? selectedProvider.services
        : [{ code: form.data.provider === 'manual' ? 'manual' : 'standard', label: form.data.provider === 'manual' ? 'Giao hàng thủ công' : 'Tiêu chuẩn' }];

    const onProviderChange = (event) => {
        const provider = event.target.value;
        const nextProvider = providerOptions.find((item) => item.value === provider);
        const nextService = nextProvider?.services?.[0]?.code ?? (provider === 'manual' ? 'manual' : 'standard');
        form.setData({ provider, method: nextService });
    };

    const submit = (event) => {
        event.preventDefault();
        form.put('/admin/shipping-default', {
            preserveScroll: true,
            onSuccess: () => toast.success('Đã lưu đơn vị giao hàng mặc định.'),
            onError: (errors) => toast.error(Object.values(errors)[0] ?? 'Không thể lưu cấu hình mặc định.'),
        });
    };

    return (
        <section className="pssp-section pssp-default-section">
            <div className="pu-caption mrl15">Đơn vị giao hàng mặc định</div>
            <form className="pssp-default-form ibody" onSubmit={submit}>
                <div className="pssp-default-row">
                    <label>Phương thức giao hàng mặc định <span className="text-red">(*)</span></label>
                    <select value={form.data.provider} onChange={onProviderChange}>
                        {providerOptions.map((provider) => (
                            <option key={provider.value} value={provider.value}>{provider.label}</option>
                        ))}
                    </select>
                </div>
                <div className="pssp-default-row">
                    <label>Giao hàng bằng mặc định <span className="text-red">(*)</span></label>
                    <select value={form.data.method ?? ''} onChange={(event) => form.setData('method', event.target.value)}>
                        {serviceOptions.map((service) => (
                            <option key={service.code ?? service.value} value={service.code ?? service.value}>{service.label}</option>
                        ))}
                    </select>
                    <button type="submit" disabled={form.processing} className="btn btn-sm btn-primary pssp-save-default">
                        <i className="fa fa-save" /> Lưu
                    </button>
                </div>
            </form>
        </section>
    );
}

export default function ShippingPartnersIndex({ providers = [], defaultConfig = {} }) {
    const orderedProviders = providerOrder
        .map((key) => providers.find((provider) => provider.provider === key))
        .filter(Boolean);
    const fallbackProviders = providers.filter((provider) => !providerOrder.includes(provider.provider) && provider.provider !== 'manual');
    const allProviders = [...orderedProviders, ...fallbackProviders];
    const [activeKey, setActiveKey] = useState(allProviders[0]?.provider ?? providers.find((provider) => provider.provider !== 'manual')?.provider);
    const active = allProviders.find((item) => item.provider === activeKey) ?? allProviders[0];

    return (
        <AppLayout activeMenuCode="1.4">
            <Head title="Cấu hình giao vận" />
            <section className="pssp-page ps-legacy-page">
                <PageHeader title="Cấu hình giao vận" pageCode="1.4" className="pssp-header-wrap" />

                <div className="box-body pssp-page-body">
                    <ShippingDefaultPanel providers={providers} defaultConfig={defaultConfig} />

                    <section className="pssp-section pssp-config-section">
                        <div className="pu-caption mrl15">Cấu hình giao hàng</div>
                        <div className="pssp-config-body ibody">
                            <aside className="pssp-provider-menu nav nav-tabs chon-kn-container" role="tablist" aria-label="Đơn vị giao hàng">
                                {allProviders.map((provider) => (
                                    <button
                                        key={provider.provider}
                                        type="button"
                                        className={`btn-xem-kn tab-${provider.provider}${provider.provider === active?.provider ? ' active' : ''}`}
                                        onClick={() => setActiveKey(provider.provider)}
                                    >
                                        {providerLabel(provider)}
                                    </button>
                                ))}
                            </aside>
                            <main className="pssp-panel tab-content">
                                {active && <ShippingPartnerCard key={active.provider} provider={active} />}
                            </main>
                        </div>
                    </section>
                </div>
            </section>
        </AppLayout>
    );
}
