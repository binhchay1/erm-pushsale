import { router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';

import { Button } from '@/components/ui/button';

export function DeleteRowButton({ url, label, confirmMessage }) {
    const remove = () => {
        const message = confirmMessage ?? `Xóa "${label}"?`;
        if (!window.confirm(message)) return;
        router.delete(url, { preserveScroll: true });
    };

    return (
        <Button
            type="button"
            variant="outline"
            size="icon-sm"
            className="text-destructive"
            onClick={remove}
            title="Xóa"
        >
            <Trash2 className="size-4" />
        </Button>
    );
}
