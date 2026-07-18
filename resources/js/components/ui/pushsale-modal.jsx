import * as React from 'react';
import { createPortal } from 'react-dom';

import {
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

function clampWidth(width) {
    return `min(${width}, calc(100vw - 48px))`;
}

/**
 * Shared Pushsale modal contract.
 *
 * This component intentionally owns the viewport overlay instead of relying on
 * page-level wrappers. Several legacy pages place dialogs inside scrollable or
 * transformed containers; CSS fixed positioning inside those containers drifts
 * left/top. Rendering through a body portal with inline layout keeps every
 * customer/report/warehouse modal centered in the browser viewport.
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

    React.useEffect(() => {
        if (!open) return undefined;
        const onKeyDown = (event) => {
            if (event.key === 'Escape') onOpenChange?.(false);
        };
        document.addEventListener('keydown', onKeyDown);
        return () => document.removeEventListener('keydown', onKeyDown);
    }, [open, onOpenChange]);

    if (!open || typeof document === 'undefined') {
        return null;
    }

    const close = () => onOpenChange?.(false);

    return createPortal(
        <div
            data-slot="dialog-overlay"
            className="ps-modal-viewport"
            style={{
                position: 'fixed',
                inset: 0,
                zIndex: 2147483000,
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                width: '100vw',
                height: '100dvh',
                padding: 24,
                boxSizing: 'border-box',
                overflow: 'auto',
                background: 'rgba(0, 0, 0, .45)',
            }}
            onMouseDown={(event) => {
                if (event.target === event.currentTarget) close();
            }}
        >
            <section
                data-slot="dialog-content"
                role="dialog"
                aria-modal="true"
                className={cn('ps-modal-surface', className)}
                style={{
                    '--ps-modal-width': modalWidth,
                    position: 'relative',
                    display: 'flex',
                    flexDirection: 'column',
                    width: clampWidth(modalWidth),
                    maxWidth: 'calc(100vw - 48px)',
                    maxHeight: 'calc(100dvh - 48px)',
                    minWidth: 0,
                    margin: 'auto',
                    overflow: 'hidden',
                    border: '1px solid rgba(0, 0, 0, .24)',
                    borderRadius: 4,
                    background: '#fff',
                    color: '#111',
                    boxShadow: '0 8px 30px rgba(0, 0, 0, .38)',
                }}
                onMouseDown={(event) => event.stopPropagation()}
            >
                <div className={cn('ps-modal-header', headerClassName)}>
                    <DialogTitle>{title}</DialogTitle>
                    {description ? <DialogDescription>{description}</DialogDescription> : null}
                </div>
                {showClose ? (
                    <button type="button" className="pushsale-dialog-close" onClick={close} aria-label="Đóng">
                        <span aria-hidden="true">×</span>
                    </button>
                ) : null}
                <div ref={bodyRef} className={cn('ps-modal-body', bodyClassName)}>
                    {children}
                </div>
                {footer ? <div className={cn('ps-modal-footer', footerClassName)}>{footer}</div> : null}
            </section>
        </div>,
        document.body,
    );
}
