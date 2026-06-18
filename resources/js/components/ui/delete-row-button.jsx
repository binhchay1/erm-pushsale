import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { useT } from '@/providers/I18nProvider';

export function DeleteRowButton({ url, label, confirmMessage }) {
    const t = useT();
    const [open, setOpen] = useState(false);
    const message = confirmMessage ?? t('confirm_dialog.delete_item', { label });

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
                title={t('common.delete')}
            >
                <Trash2 className="size-4" />
            </Button>

            <ConfirmDialog
                open={open}
                onOpenChange={setOpen}
                title={t('confirm_dialog.delete_title')}
                description={message}
                confirmLabel={t('confirm_dialog.confirm_delete')}
                variant="destructive"
                onConfirm={remove}
            />
        </>
    );
}
