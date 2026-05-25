import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { Toaster } from 'sonner';

import { ThemeProvider } from '@/providers/ThemeProvider';
import '../css/app.css';

const pages = import.meta.glob('./pages/**/*.jsx');

createInertiaApp({
    resolve: (name) => pages[`./pages/${name}.jsx`](),
    setup({ el, App, props }) {
        createRoot(el).render(
            <ThemeProvider>
                <App {...props} />
                <Toaster richColors position="top-right" closeButton />
            </ThemeProvider>
        );
    },
    progress: {
        color: '#2563eb',
    },
});
