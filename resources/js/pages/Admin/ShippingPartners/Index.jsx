import { Head } from '@inertiajs/react';
import { Truck } from 'lucide-react';

import { PageHeader } from '@/components/layout/PageHeader';
import { ShippingPartnerCard } from '@/components/shipping/ShippingPartnerCard';
import AppLayout from '@/layouts/AppLayout';
import { useT } from '@/providers/I18nProvider';

export default function ShippingPartnersIndex({ providers }) {
    const t = useT();

    return (
        <AppLayout>
            <Head title={t('shipping.title')} />

            <div className="space-y-6">
                <PageHeader
                    icon={Truck}
                    title={t('shipping.title')}
                    description={t('shipping.partners_desc')}
                />

                <div className="grid gap-6 xl:grid-cols-2">
                    {providers.map((provider) => (
                        <ShippingPartnerCard key={provider.provider} provider={provider} />
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
