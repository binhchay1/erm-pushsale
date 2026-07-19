import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useT } from '@/providers/I18nProvider';

/** Confirm dialog — use directly or via useConfirm(). */
export function ConfirmDialog({
    open,
    onOpenChange,
    title,
    description,
    confirmLabel,
    cancelLabel,
    variant = 'default',
    onConfirm,
}) {
    const t = useT();

    const handleConfirm = () => {
        onConfirm?.();
        onOpenChange?.(false);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md" showClose={false}>
                <DialogHeader>
                    <DialogTitle>{title ?? t('confirm_dialog.title')}</DialogTitle>
                    {description && <DialogDescription>{description}</DialogDescription>}
                </DialogHeader>
                <DialogFooter className="gap-2 sm:gap-0">
                    <Button type="button" variant="outline" onClick={() => onOpenChange?.(false)}>
                        {cancelLabel ?? t('confirm_dialog.cancel_label')}
                    </Button>
                    <Button
                        type="button"
                        variant={variant === 'destructive' ? 'destructive' : 'default'}
                        onClick={handleConfirm}
                    >
                        {confirmLabel ?? t('common.confirm')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
