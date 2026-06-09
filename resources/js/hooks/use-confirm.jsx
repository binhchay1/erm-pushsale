import { useCallback, useState } from 'react';

import { ConfirmDialog } from '@/components/ui/confirm-dialog';

const CLOSED = {
    open: false,
    title: 'Xác nhận',
    description: '',
    confirmLabel: 'Xác nhận',
    cancelLabel: 'Huỷ',
    variant: 'default',
    resolve: null,
};

export function useConfirm() {
    const [state, setState] = useState(CLOSED);

    const close = useCallback((result) => {
        setState((current) => {
            current.resolve?.(result);
            return CLOSED;
        });
    }, []);

    const ask = useCallback((options = {}) => {
        return new Promise((resolve) => {
            setState({
                open: true,
                title: options.title ?? 'Xác nhận',
                description: options.description ?? '',
                confirmLabel: options.confirmLabel ?? 'Xác nhận',
                cancelLabel: options.cancelLabel ?? 'Huỷ',
                variant: options.variant ?? 'default',
                resolve,
            });
        });
    }, []);

    const ConfirmDialogPortal = () => (
        <ConfirmDialog
            open={state.open}
            onOpenChange={(open) => !open && close(false)}
            title={state.title}
            description={state.description}
            confirmLabel={state.confirmLabel}
            cancelLabel={state.cancelLabel}
            variant={state.variant}
            onConfirm={() => close(true)}
        />
    );

    return { ask, ConfirmDialogPortal };
}
