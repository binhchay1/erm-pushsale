import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Save, Truck } from 'lucide-react';
import { toast } from 'sonner';

import { ShippingPartnerCard } from '@/components/shipping/ShippingPartnerCard';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout';

export default function ShippingPartnersIndex({ providers = [], defaultConfig = {} }) {
    const [activeKey, setActiveKey] = useState(defaultConfig.provider ?? providers[0]?.provider);
    const active = providers.find((item) => item.provider === activeKey) ?? providers[0];
    const defaultForm = useForm({
        provider: defaultConfig.provider ?? 'manual',
        method: defaultConfig.method ?? 'standard',
    });
    const serviceOptions = useMemo(
        () => providers.find((item) => item.provider === defaultForm.data.provider)?.services ?? [],
        [providers, defaultForm.data.provider],
    );

    const saveDefault = (event) => {
        event.preventDefault();
        defaultForm.put('/admin/shipping-default', {
            preserveScroll: true,
            onSuccess: () => toast.success('Đã lưu đơn vị giao hàng mặc định.'),
            onError: (errors) => toast.error(Object.values(errors)[0] ?? 'Không thể lưu cấu hình.'),
        });
    };

    return (
        <AppLayout activeMenuCode="1.4">
            <Head title="Cấu hình giao vận" />

            <section className="ps-shipping-config-page">
                <header className="ps-shipping-titlebar">
                    <h1><Truck size={18} /> Cấu hình giao vận</h1>
                </header>

                <form className="ps-shipping-default-box" onSubmit={saveDefault}>
                    <h2>ĐƠN VỊ GIAO HÀNG MẶC ĐỊNH</h2>
                    <div className="ps-shipping-default-grid">
                        <label>Phương thức giao hàng mặc định <b>(*)</b></label>
                        <select
                            value={defaultForm.data.provider}
                            onChange={(event) => {
                                const provider = event.target.value;
                                const firstService = providers.find((item) => item.provider === provider)?.services?.[0]?.code ?? 'standard';
                                defaultForm.setData({ provider, method: firstService });
                                setActiveKey(provider);
                            }}
                        >
                            {providers.map((item) => <option key={item.provider} value={item.provider}>{item.label}</option>)}
                        </select>
                        <span />

                        <label>Giao hàng bằng mặc định <b>(*)</b></label>
                        <select value={defaultForm.data.method} onChange={(event) => defaultForm.setData('method', event.target.value)}>
                            {(serviceOptions.length ? serviceOptions : [{ code: 'standard', label: 'Tiêu chuẩn' }]).map((service) => (
                                <option key={service.code} value={service.code}>{service.label}</option>
                            ))}
                        </select>
                        <Button type="submit" size="sm" disabled={defaultForm.processing}>
                            <Save size={14} /> Lưu
                        </Button>
                    </div>
                </form>

                <div className="ps-shipping-config-box">
                    <h2>CẤU HÌNH GIAO HÀNG</h2>
                    <div className="ps-shipping-config-layout">
                        <aside className="ps-shipping-provider-list">
                            {providers.map((provider) => (
                                <button
                                    key={provider.provider}
                                    type="button"
                                    className={provider.provider === active?.provider ? 'active' : ''}
                                    onClick={() => setActiveKey(provider.provider)}
                                >
                                    <span>{provider.label}</span>
                                    <i className={provider.is_configured ? 'ready' : provider.is_enabled ? 'warning' : ''} />
                                </button>
                            ))}
                        </aside>

                        <main className="ps-shipping-provider-panel">
                            {active ? <ShippingPartnerCard key={active.provider} provider={active} /> : (
                                <p>Chưa có đơn vị giao hàng.</p>
                            )}
                        </main>
                    </div>
                </div>
            </section>
        </AppLayout>
    );
}
