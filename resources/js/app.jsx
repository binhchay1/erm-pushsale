import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { Toaster } from 'sonner';

import { ErrorBoundary } from '@/components/ErrorBoundary';
import { ConfirmProvider } from '@/hooks/use-confirm';
import { I18nProvider } from '@/providers/I18nProvider';
import { ThemeProvider } from '@/providers/ThemeProvider';

const pages = import.meta.glob('./pages/**/*.jsx');

if (typeof window !== 'undefined') {
    window.addEventListener('vite:preloadError', (event) => {
        event.preventDefault();
        window.location.reload();
    });
}

function missingPageComponent(name) {
    return {
        default: function MissingPage() {
            return (
                <div style={{ padding: 24, fontFamily: 'Arial, sans-serif' }}>
                    <h1>Không tải được màn hình</h1>
                    <p>Component Inertia <code>{name}</code> chưa tồn tại trong bundle hiện tại. Hãy chạy <code>pnpm build</code> và refresh trang.</p>
                    <button type="button" onClick={() => window.location.reload()}>Tải lại</button>
                </div>
            );
        },
    };
}

createInertiaApp({
    resolve: (name) => {
        const resolver = pages[`./pages/${name}.jsx`];
        return resolver ? resolver() : Promise.resolve(missingPageComponent(name));
    },
    setup({ el, App, props }) {
        const pageProps = props.initialPage?.props ?? {};
        createRoot(el).render(
            <I18nProvider
                initialLocale={pageProps.locale ?? 'vi'}
                localeMeta={pageProps.locales}
            >
                <ErrorBoundary>
                    <ThemeProvider
                        preferences={pageProps.preferences}
                        themes={pageProps.themes}
                    >
                        <ConfirmProvider>
                            <App {...props} />
                            <Toaster richColors position="top-right" closeButton visibleToasts={3} offset={18} toastOptions={{ className: 'pushsale-toast', duration: 5500 }} />
                        </ConfirmProvider>
                    </ThemeProvider>
                </ErrorBoundary>
            </I18nProvider>
        );
    },
    progress: {
        color: '#2563eb',
    },
});
