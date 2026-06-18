import { Component } from 'react';
import { RefreshCw } from 'lucide-react';

import { ErrorShell } from '@/components/errors/ErrorShell';
import { Button } from '@/components/ui/button';
import { useT } from '@/providers/I18nProvider';

function ClientErrorActions() {
    const t = useT();

    return (
        <>
            <Button type="button" onClick={() => window.location.reload()}>
                <RefreshCw className="size-4" />
                {t('common.refresh')}
            </Button>
            <Button type="button" variant="outline" onClick={() => window.history.back()}>
                {t('common.back')}
            </Button>
        </>
    );
}

/**
 * Catch React errors — same layout as HTTP error pages.
 */
export class ErrorBoundary extends Component {
    state = { error: null };

    static getDerivedStateFromError(error) {
        return { error };
    }

    componentDidCatch(error, info) {
        console.error('[SaleOps] React error:', error, info);
    }

    render() {
        if (this.state.error) {
            const message = this.state.error?.message ?? String(this.state.error ?? 'Unknown error');
            const stack = this.state.error?.stack ?? '';

            return (
                <ErrorShell
                    status="client"
                    detail={import.meta.env.DEV ? `${message}${stack ? `\n\n${stack}` : ''}` : message}
                >
                    <ClientErrorActions />
                </ErrorShell>
            );
        }

        return this.props.children;
    }
}
