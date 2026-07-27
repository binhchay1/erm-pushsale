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

/** Confirm / alert dialog — use via useConfirm(). */
export function ConfirmDialog({
    open,
    onOpenChange,
    title,
    description,
    confirmLabel,
    cancelLabel,
    variant = 'default',
    mode = 'confirm',
    onConfirm,
}) {
    const t = useT();
    const isAlert = mode === 'alert';

    const handleConfirm = () => {
        onConfirm?.();
        onOpenChange?.(false);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md ps-confirm-dialog-surface" showClose={false}>
                <DialogHeader>
                    <DialogTitle>{title ?? t('confirm_dialog.title')}</DialogTitle>
                    {description ? <DialogDescription>{description}</DialogDescription> : null}
                </DialogHeader>
                <DialogFooter className="gap-2 sm:gap-0">
                    {!isAlert ? (
                        <Button type="button" variant="outline" onClick={() => onOpenChange?.(false)}>
                            {cancelLabel ?? t('confirm_dialog.cancel_label')}
                        </Button>
                    ) : null}
                    <Button
                        type="button"
                        variant={variant === 'destructive' ? 'destructive' : 'default'}
                        onClick={handleConfirm}
                    >
                        {confirmLabel ?? (isAlert ? t('common.confirm') : t('common.confirm'))}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
