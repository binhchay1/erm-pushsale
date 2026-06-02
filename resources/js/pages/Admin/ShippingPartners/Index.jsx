import { Head } from '@inertiajs/react';
import { Truck } from 'lucide-react';

import { PageHeader } from '@/components/layout/PageHeader';
import { ShippingPartnerCard } from '@/components/shipping/ShippingPartnerCard';
import AppLayout from '@/layouts/AppLayout';

export default function ShippingPartnersIndex({ providers }) {
    return (
        <AppLayout>
            <Head title="API vận chuyển" />

            <div className="space-y-6">
                <PageHeader
                    icon={Truck}
                    title="Cấu hình API đơn vị vận chuyển"
                    description="Nhập key kết nối Viettel Post, GHN, GHTK... để sẵn sàng tạo vận đơn tự động."
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
