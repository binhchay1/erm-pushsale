import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { Toaster } from 'sonner';

import { ErrorBoundary } from '@/components/ErrorBoundary';
import { ThemeProvider } from '@/providers/ThemeProvider';
import '../css/app.css';

const pages = import.meta.glob('./pages/**/*.jsx');

createInertiaApp({
    resolve: (name) => pages[`./pages/${name}.jsx`](),
    setup({ el, App, props }) {
        const pageProps = props.initialPage?.props ?? {};
        createRoot(el).render(
            <ErrorBoundary>
                <ThemeProvider
                    preferences={pageProps.preferences}
                    themes={pageProps.themes}
                >
                    <App {...props} />
                    <Toaster richColors position="top-right" closeButton />
                </ThemeProvider>
            </ErrorBoundary>
        );
    },
    progress: {
        color: '#2563eb',
    },
});
