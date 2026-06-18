import { useCallback, useState } from 'react';

import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { useT } from '@/providers/I18nProvider';

export function useConfirm() {
    const t = useT();
    const [state, setState] = useState({
        open: false,
        title: '',
        description: '',
        confirmLabel: '',
        cancelLabel: '',
        variant: 'default',
        resolve: null,
    });

    const close = useCallback((result) => {
        setState((current) => {
            current.resolve?.(result);
            return {
                open: false,
                title: '',
                description: '',
                confirmLabel: '',
                cancelLabel: '',
                variant: 'default',
                resolve: null,
            };
        });
    }, []);

    const ask = useCallback(
        (options = {}) => {
            return new Promise((resolve) => {
                setState({
                    open: true,
                    title: options.title ?? t('confirm_dialog.title'),
                    description: options.description ?? '',
                    confirmLabel: options.confirmLabel ?? t('common.confirm'),
                    cancelLabel: options.cancelLabel ?? t('confirm_dialog.cancel_label'),
                    variant: options.variant ?? 'default',
                    resolve,
                });
            });
        },
        [t],
    );

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
