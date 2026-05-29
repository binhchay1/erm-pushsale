import { Component } from 'react';

/**
 * Hiển thị lỗi React trên màn hình thay vì trang trắng.
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
            const message =
                this.state.error?.message ?? String(this.state.error ?? 'Unknown error');

            return (
                <div className="min-h-svh bg-red-50 p-6 text-red-950 dark:bg-red-950/40 dark:text-red-100">
                    <div className="mx-auto max-w-2xl rounded-lg border border-red-200 bg-white p-6 shadow-sm dark:border-red-800 dark:bg-red-950">
                        <h1 className="text-lg font-bold">Lỗi giao diện (JavaScript)</h1>
                        <p className="mt-2 text-sm text-red-800 dark:text-red-200">
                            Trang không render được. Chi tiết bên dưới — mở DevTools (F12) → Console
                            để xem stack đầy đủ.
                        </p>
                        <pre className="mt-4 max-h-96 overflow-auto rounded-md bg-red-50 p-3 text-xs whitespace-pre-wrap break-words dark:bg-red-900/50">
                            {message}
                            {this.state.error?.stack ? `\n\n${this.state.error.stack}` : ''}
                        </pre>
                        <button
                            type="button"
                            className="mt-4 rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700"
                            onClick={() => window.location.reload()}
                        >
                            Tải lại trang
                        </button>
                    </div>
                </div>
            );
        }

        return this.props.children;
    }
}
