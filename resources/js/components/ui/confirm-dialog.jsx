import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

/**
 * Modal xác nhận thay cho window.confirm — dùng trực tiếp hoặc qua useConfirm().
 */
export function ConfirmDialog({
    open,
    onOpenChange,
    title = 'Xác nhận',
    description,
    confirmLabel = 'Xác nhận',
    cancelLabel = 'Huỷ',
    variant = 'default',
    onConfirm,
}) {
    const handleConfirm = () => {
        onConfirm?.();
        onOpenChange?.(false);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md" showClose={false}>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    {description && <DialogDescription>{description}</DialogDescription>}
                </DialogHeader>
                <DialogFooter className="gap-2 sm:gap-0">
                    <Button type="button" variant="outline" onClick={() => onOpenChange?.(false)}>
                        {cancelLabel}
                    </Button>
                    <Button
                        type="button"
                        variant={variant === 'destructive' ? 'destructive' : 'default'}
                        onClick={handleConfirm}
                    >
                        {confirmLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
