import { Component } from 'react';
import { router } from '@inertiajs/react';
import { RefreshCw } from 'lucide-react';

import { ErrorShell } from '@/components/errors/ErrorShell';
import { Button } from '@/components/ui/button';
import { useT } from '@/providers/I18nProvider';

function ClientErrorActions({ onRecover }) {
    const t = useT();

    return (
        <>
            <Button type="button" onClick={() => window.location.reload()}>
                <RefreshCw className="size-4" />
                {t('common.refresh')}
            </Button>
            <Button
                type="button"
                variant="outline"
                onClick={() => {
                    onRecover?.();
                    if (window.history.length > 1) {
                        window.history.back();
                        return;
                    }
                    window.location.assign('/admin/dashboard');
                }}
            >
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

    componentDidMount() {
        this.removeNavigateListener = router.on('navigate', () => {
            if (this.state.error) this.setState({ error: null });
        });
        this.removeFinishListener = router.on('finish', () => {
            if (this.state.error) this.setState({ error: null });
        });
    }

    componentWillUnmount() {
        this.removeNavigateListener?.();
        this.removeFinishListener?.();
    }

    componentDidCatch(error, info) {
        console.error('[SaleOps] React error:', error, info);
    }

    recover = () => {
        this.setState({ error: null });
    };

    render() {
        if (this.state.error) {
            return (
                <ErrorShell
                    status="client"
                >
                    <ClientErrorActions onRecover={this.recover} />
                </ErrorShell>
            );
        }

        return this.props.children;
    }
}
