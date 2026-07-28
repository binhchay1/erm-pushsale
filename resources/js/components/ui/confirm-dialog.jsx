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
    message,
    confirmLabel,
    cancelLabel,
    variant = 'default',
    mode = 'confirm',
    onConfirm,
}) {
    const t = useT();
    const isAlert = mode === 'alert';
    const isDanger = variant === 'destructive';

    // Global Pushsale CSS hides [data-slot='dialog-description']; put copy in the body.
    const bodyText = String(description ?? message ?? '').trim()
        || (isDanger ? t('confirm_dialog.delete_desc') : '');

    const handleConfirm = () => {
        onConfirm?.();
        onOpenChange?.(false);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md ps-confirm-dialog-surface ps-confirm-dialog" showClose={false}>
                <DialogHeader>
                    <DialogTitle>{title ?? t('confirm_dialog.title')}</DialogTitle>
                    <DialogDescription className="sr-only">
                        {bodyText || title || t('confirm_dialog.title')}
                    </DialogDescription>
                </DialogHeader>
                <div className="ps-confirm-body">
                    <div className={`ps-confirm-action ps-confirm-${isDanger ? 'danger' : 'default'}`}>
                        <span className="ps-confirm-icon" aria-hidden="true">
                            <i className={`fa ${isDanger ? 'fa-exclamation-triangle' : 'fa-question-circle'}`} />
                        </span>
                        <div className="ps-confirm-copy">
                            {bodyText || t('confirm_dialog.delete_related_desc')}
                        </div>
                    </div>
                </div>
                <DialogFooter className="gap-2 sm:gap-0">
                    {!isAlert ? (
                        <Button type="button" variant="outline" onClick={() => onOpenChange?.(false)}>
                            {cancelLabel ?? t('confirm_dialog.cancel_label')}
                        </Button>
                    ) : null}
                    <Button
                        type="button"
                        variant={isDanger ? 'destructive' : 'default'}
                        onClick={handleConfirm}
                    >
                        {confirmLabel ?? t('common.confirm')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
