import * as React from 'react';

import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { cn } from '@/lib/utils';

const dialogWidths = {
    sm: '520px',
    md: '800px',
    lg: '1120px',
    xl: '1500px',
    full: '1728px',
};

function clampWidth(width) {
    if (!width) return 'min(800px, calc(100vw - 48px))';
    const raw = String(width);
    if (raw.includes('min(') || raw.includes('calc(') || raw.includes('vw')) return raw;
    return `min(${raw}, calc(100vw - 48px))`;
}

/**
 * Canonical project dialog.
 *
 * The project now uses Radix Dialog for every React dialog/dialog.  This wrapper
 * keeps the Pushsale/AdminLTE visual contract while centralising portal,
 * focus-trap, escape/outside-close, z-index and slide-down animation in one
 * implementation.  Do not create page-local `.ps-dialog-backdrop` dialogs for new
 * UI; use PushsaleDialog or DialogContent through this contract.
 */
export function PushsaleDialog({
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
    contentProps = {},
}) {
    const dialogWidth = width ?? dialogWidths[size] ?? dialogWidths.md;
    const fallbackTitle = title || description || 'Dialog';

    return (
        <Dialog open={Boolean(open)} onOpenChange={onOpenChange}>
            <DialogContent
                className={cn('ps-dialog-surface', className)}
                showClose={showClose}
                aria-describedby={description ? undefined : undefined}
                {...contentProps}
                style={{
                    ...contentProps.style,
                    '--ps-dialog-width': dialogWidth,
                    width: clampWidth(dialogWidth),
                    maxWidth: 'calc(100vw - 48px)',
                    maxHeight: 'calc(100dvh - 48px)',
                }}
            >
                <DialogHeader className={cn('ps-dialog-header', !title && !description ? 'sr-only' : '', headerClassName)}>
                    <DialogTitle className="ps-dialog-title">{fallbackTitle}</DialogTitle>
                    {description ? <DialogDescription className="ps-dialog-description">{description}</DialogDescription> : null}
                </DialogHeader>
                <div ref={bodyRef} className={cn('ps-dialog-body', bodyClassName)}>
                    {children}
                </div>
                {footer ? <DialogFooter className={cn('ps-dialog-footer', footerClassName)}>{footer}</DialogFooter> : null}
            </DialogContent>
        </Dialog>
    );
}
