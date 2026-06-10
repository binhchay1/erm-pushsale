import { Component } from 'react';

import { ErrorShell } from '@/components/errors/ErrorShell';
import { Button } from '@/components/ui/button';
import { RefreshCw } from 'lucide-react';

/**
 * Bắt lỗi React — hiển thị cùng giao diện với trang lỗi HTTP.
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
                    <Button type="button" onClick={() => window.location.reload()}>
                        <RefreshCw className="size-4" />
                        Tải lại trang
                    </Button>
                    <Button type="button" variant="outline" onClick={() => window.history.back()}>
                        Quay lại
                    </Button>
                </ErrorShell>
            );
        }

        return this.props.children;
    }
}
