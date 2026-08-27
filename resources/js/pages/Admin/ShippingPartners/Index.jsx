import { Head, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';

import { ShippingPartnerCard } from '@/components/shipping/ShippingPartnerCard';
import { PageHeader } from '@/components/layout/PageHeader';
import AppLayout from '@/layouts/AppLayout';
import { useT } from '@/providers/I18nProvider';

const providerOrder = [
    'netship',
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

function providerLabel(t, provider) {
    const key = provider?.provider ?? provider?.value;
    const translated = key ? t(`shipping.partners_page.names.${key}`) : '';
    if (translated && !translated.startsWith('shipping.partners_page.names.')) {
        return translated;
    }
    return provider?.label ?? key ?? '';
}

function ShippingDefaultPanel({ providers = [], defaultConfig = {} }) {
    const t = useT();

    const providerOptions = useMemo(() => {
        const selectable = providers.filter((provider) => provider.selectable !== false && !provider.is_gateway);
        const indexed = new Map(selectable.map((provider) => [provider.provider, provider]));
        const ordered = ['manual', ...providerOrder.filter((key) => key !== 'netship')]
            .map((key) => indexed.get(key))
            .filter(Boolean);
        const fallback = selectable.filter((provider) => !ordered.some((item) => item.provider === provider.provider));

        return [...ordered, ...fallback].map((provider) => ({
            value: provider.provider,
            label: providerLabel(t, provider),
            services: provider.services ?? [],
        }));
    }, [providers, t]);

    const form = useForm({
        provider: defaultConfig.provider ?? 'manual',
        method: defaultConfig.method ?? 'manual',
    });

    const selectedProvider = providerOptions.find((provider) => provider.value === form.data.provider) ?? providerOptions[0];
    const serviceOptions = selectedProvider?.services?.length
        ? selectedProvider.services
        : [{
            code: form.data.provider === 'manual' ? 'manual' : 'standard',
            label: form.data.provider === 'manual' ? t('shipping.partners_page.manual_shipping') : t('shipping.partners_page.standard'),
        }];

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
            onError: (errors) => toast.error(Object.values(errors)[0] ?? t('shipping.partners_page.save_default_failed')),
        });
    };

    return (
        <section className="pssp-section pssp-default-section">
            <div className="pu-caption mrl15">{t('shipping.partners_page.default_section')}</div>
            <form className="pssp-default-form ibody" onSubmit={submit}>
                <div className="pssp-default-row">
                    <label>{t('shipping.partners_page.default_method')} <span className="text-red">(*)</span></label>
                    <select value={form.data.provider} onChange={onProviderChange}>
                        {providerOptions.map((provider) => (
                            <option key={provider.value} value={provider.value}>{provider.label}</option>
                        ))}
                    </select>
                </div>
                <div className="pssp-default-row">
                    <label>{t('shipping.partners_page.default_service')} <span className="text-red">(*)</span></label>
                    <select value={form.data.method ?? ''} onChange={(event) => form.setData('method', event.target.value)}>
                        {serviceOptions.map((service) => (
                            <option key={service.code ?? service.value} value={service.code ?? service.value}>
                                {service.label}
                            </option>
                        ))}
                    </select>
                    <button type="submit" disabled={form.processing} className="btn btn-sm btn-primary pssp-save-default">
                        <i className="fa fa-save" /> {t('shipping.partners_page.save')}
                    </button>
                </div>
            </form>
        </section>
    );
}

export default function ShippingPartnersIndex({ providers = [], defaultConfig = {} }) {
    const t = useT();
    const orderedProviders = providerOrder
        .map((key) => providers.find((provider) => provider.provider === key))
        .filter(Boolean);
    const fallbackProviders = providers.filter((provider) => !providerOrder.includes(provider.provider) && provider.provider !== 'manual');
    const allProviders = [...orderedProviders, ...fallbackProviders];
    const [activeKey, setActiveKey] = useState(
        allProviders.find((provider) => provider.provider === 'netship')?.provider
            ?? allProviders[0]?.provider
            ?? providers.find((provider) => provider.provider !== 'manual')?.provider,
    );
    const active = allProviders.find((item) => item.provider === activeKey) ?? allProviders[0];

    return (
        <AppLayout activeMenuCode="1.4">
            <Head title={t('shipping.partners_page.title')} />
            <section className="pssp-page ps-legacy-page">
                <PageHeader title={t('shipping.partners_page.title')} pageCode="1.4" className="pssp-header-wrap" />

                <div className="box-body pssp-page-body">
                    <ShippingDefaultPanel providers={providers} defaultConfig={defaultConfig} />

                    <section className="pssp-section pssp-config-section">
                        <div className="pu-caption mrl15">{t('shipping.partners_page.config_section')}</div>
                        <div className="pssp-config-body ibody">
                            <aside
                                className="pssp-provider-menu nav nav-tabs chon-kn-container"
                                role="tablist"
                                aria-label={t('shipping.partners_page.carrier_list_aria')}
                            >
                                {allProviders.map((provider) => (
                                    <button
                                        key={provider.provider}
                                        type="button"
                                        className={`btn-xem-kn tab-${provider.provider}${provider.provider === active?.provider ? ' active' : ''}`}
                                        onClick={() => setActiveKey(provider.provider)}
                                    >
                                        {providerLabel(t, provider)}
                                        {provider.is_gateway ? ' ★' : ''}
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
