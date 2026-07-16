import * as React from 'react';

import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { cn } from '@/lib/utils';

const modalWidths = {
    sm: '520px',
    md: '800px',
    lg: '1120px',
    xl: '1500px',
    full: '1728px',
};

/**
 * Shared modal contract for the internal ERM shell.
 * Only the body scrolls; width is clamped to the current viewport by CSS.
 */
export function PushsaleModal({
    open,
    onOpenChange,
    title,
    description,
    size = 'md',
    width,
    children,
    footer,
    bodyRef,
    className,
    bodyClassName,
    headerClassName,
    footerClassName,
    showClose = true,
}) {
    const modalWidth = width ?? modalWidths[size] ?? modalWidths.md;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                showClose={showClose}
                className={cn('ps-modal-surface', className)}
                style={{ '--ps-modal-width': modalWidth }}
            >
                <div className={cn('ps-modal-header', headerClassName)}>
                    <DialogTitle>{title}</DialogTitle>
                    {description ? <DialogDescription>{description}</DialogDescription> : null}
                </div>
                <div ref={bodyRef} className={cn('ps-modal-body', bodyClassName)}>
                    {children}
                </div>
                {footer ? <div className={cn('ps-modal-footer', footerClassName)}>{footer}</div> : null}
            </DialogContent>
        </Dialog>
    );
}
