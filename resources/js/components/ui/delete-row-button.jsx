import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';

export function DeleteRowButton({ url, label, confirmMessage }) {
    const [open, setOpen] = useState(false);
    const message = confirmMessage ?? `Xóa "${label}"?`;

    const remove = () => {
        router.delete(url, { preserveScroll: true });
    };

    return (
        <>
            <Button
                type="button"
                variant="outline"
                size="icon-sm"
                className="text-destructive"
                onClick={() => setOpen(true)}
                title="Xóa"
            >
                <Trash2 className="size-4" />
            </Button>

            <ConfirmDialog
                open={open}
                onOpenChange={setOpen}
                title="Xác nhận xóa"
                description={message}
                confirmLabel="Xóa"
                variant="destructive"
                onConfirm={remove}
            />
        </>
    );
}
