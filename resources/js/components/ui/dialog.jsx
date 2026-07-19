import * as React from 'react';
import { Dialog as DialogPrimitive } from 'radix-ui';

import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import { XIcon } from 'lucide-react';

const MODAL_Z_INDEX = 2147483000;

function Dialog(props) {
    return <DialogPrimitive.Root data-slot="dialog" {...props} />;
}

function DialogTrigger(props) {
    return <DialogPrimitive.Trigger data-slot="dialog-trigger" {...props} />;
}

function DialogPortal(props) {
    return <DialogPrimitive.Portal data-slot="dialog-portal" {...props} />;
}

function DialogClose(props) {
    return <DialogPrimitive.Close data-slot="dialog-close" {...props} />;
}

function DialogOverlay({ className, style, ...props }) {
    return (
        <DialogPrimitive.Overlay
            data-slot="dialog-overlay"
            className={cn('ps-dialog-overlay fixed inset-0 bg-black/40', className)}
            style={{ zIndex: MODAL_Z_INDEX, ...style }}
            {...props}
        />
    );
}

function DialogContent({ className, children, showClose = true, style, ...props }) {
    return (
        <DialogPortal>
            <DialogOverlay />
            <DialogPrimitive.Content
                data-slot="dialog-content"
                className={cn('ps-dialog-content fixed left-1/2 top-1/2 grid w-full max-w-lg gap-4 rounded bg-popover p-6 shadow-lg', className)}
                style={{ zIndex: MODAL_Z_INDEX + 1, ...style }}
                {...props}
            >
                {children}
                {showClose && (
                    <DialogPrimitive.Close asChild>
                        <Button variant="ghost" className="pushsale-dialog-close absolute top-3 right-3" size="icon-sm">
                            <XIcon />
                            <span className="sr-only">Close</span>
                        </Button>
                    </DialogPrimitive.Close>
                )}
            </DialogPrimitive.Content>
        </DialogPortal>
    );
}

function DialogHeader({ className, ...props }) {
    return <div data-slot="dialog-header" className={cn('flex flex-col gap-1.5', className)} {...props} />;
}

function DialogFooter({ className, ...props }) {
    return <div data-slot="dialog-footer" className={cn('flex flex-col-reverse gap-2 sm:flex-row sm:justify-end', className)} {...props} />;
}

function DialogTitle({ className, ...props }) {
    return (
        <DialogPrimitive.Title
            data-slot="dialog-title"
            className={cn('text-lg font-semibold leading-none', className)}
            {...props}
        />
    );
}

function DialogDescription({ className, ...props }) {
    return (
        <DialogPrimitive.Description
            data-slot="dialog-description"
            className={cn('text-sm text-muted-foreground', className)}
            {...props}
        />
    );
}

export {
    Dialog,
    DialogTrigger,
    DialogClose,
    DialogContent,
    DialogHeader,
    DialogFooter,
    DialogTitle,
    DialogDescription,
};
