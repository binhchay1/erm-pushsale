import { createContext, useCallback, useContext, useMemo, useState } from 'react';

import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { useT } from '@/providers/I18nProvider';

const ConfirmContext = createContext(null);

function createIdleState() {
    return {
        open: false,
        mode: 'confirm',
        title: '',
        description: '',
        confirmLabel: '',
        cancelLabel: '',
        variant: 'default',
        resolve: null,
    };
}

export function ConfirmProvider({ children }) {
    const t = useT();
    const [state, setState] = useState(createIdleState);

    const close = useCallback((result) => {
        setState((current) => {
            current.resolve?.(result);
            return createIdleState();
        });
    }, []);

    const ask = useCallback(
        (options = {}) => new Promise((resolve) => {
            const variant = options.variant ?? 'default';
            const description = String(
                options.description
                ?? options.message
                ?? (variant === 'destructive' ? t('confirm_dialog.delete_desc') : ''),
            ).trim();

            setState({
                open: true,
                mode: 'confirm',
                title: options.title ?? (variant === 'destructive' ? t('confirm_dialog.delete_title') : t('confirm_dialog.title')),
                description,
                confirmLabel: options.confirmLabel
                    ?? (variant === 'destructive' ? t('confirm_dialog.confirm_delete') : t('common.confirm')),
                cancelLabel: options.cancelLabel ?? t('confirm_dialog.cancel_label'),
                variant,
                resolve,
            });
        }),
        [t],
    );

    const alert = useCallback(
        (options = {}) => new Promise((resolve) => {
            const description = typeof options === 'string'
                ? options
                : String(options.description ?? options.message ?? '').trim();
            const title = typeof options === 'string'
                ? t('confirm_dialog.title')
                : (options.title ?? t('confirm_dialog.title'));
            const confirmLabel = typeof options === 'string'
                ? t('common.confirm')
                : (options.confirmLabel ?? t('common.confirm'));

            setState({
                open: true,
                mode: 'alert',
                title,
                description: description || t('confirm_dialog.delete_related_desc'),
                confirmLabel,
                cancelLabel: '',
                variant: typeof options === 'string' ? 'default' : (options.variant ?? 'default'),
                resolve,
            });
        }),
        [t],
    );

    const value = useMemo(() => ({
        ask,
        alert,
        // Kept for pages that still render <ConfirmDialogPortal />; provider owns the dialog.
        ConfirmDialogPortal: () => null,
    }), [ask, alert]);

    return (
        <ConfirmContext.Provider value={value}>
            {children}
            <ConfirmDialog
                open={state.open}
                mode={state.mode}
                onOpenChange={(open) => !open && close(false)}
                title={state.title}
                description={state.description}
                confirmLabel={state.confirmLabel}
                cancelLabel={state.cancelLabel}
                variant={state.variant}
                onConfirm={() => close(true)}
            />
        </ConfirmContext.Provider>
    );
}

export function useConfirm() {
    const context = useContext(ConfirmContext);
    if (!context) {
        throw new Error('useConfirm must be used within ConfirmProvider');
    }
    return context;
}
