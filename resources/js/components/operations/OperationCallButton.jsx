import { router } from '@inertiajs/react';
import { Phone } from 'lucide-react';

import { Button } from '@/components/ui/button';

export function OperationCallButton({ order }) {
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
                Gọi
            </a>
        </Button>
    );
}
