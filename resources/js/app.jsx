import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { Toaster } from 'sonner';

import { ErrorBoundary } from '@/components/ErrorBoundary';
import { I18nProvider } from '@/providers/I18nProvider';
import { ThemeProvider } from '@/providers/ThemeProvider';
import '../css/app.css';

const pages = import.meta.glob('./pages/**/*.jsx');

createInertiaApp({
    resolve: (name) => pages[`./pages/${name}.jsx`](),
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
                        <App {...props} />
                        <Toaster richColors position="top-right" closeButton />
                    </ThemeProvider>
                </ErrorBoundary>
            </I18nProvider>
        );
    },
    progress: {
        color: '#2563eb',
    },
});
