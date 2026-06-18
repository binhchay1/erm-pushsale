import { router } from '@inertiajs/react';
import { Phone } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { useT } from '@/providers/I18nProvider';

export function OperationCallButton({ order }) {
    const t = useT();

    if (!order?.canCall) {
        return null;
    }

    const phone = String(order.customerPhone ?? '').replace(/\s+/g, '');

    const onCall = () => {
        router.post(
            `/sales/orders/${order.id}/call`,
            {},
            {
                preserveScroll: true,
            }
        );
    };

    return (
        <Button asChild size="sm" variant="outline" className="gap-1">
            <a href={`tel:${phone}`} onClick={onCall}>
                <Phone className="size-3.5" />
                {t('operations.call_btn')}
            </a>
        </Button>
    );
}
