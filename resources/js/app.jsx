import '../css/app.css';
import './lib/http';

import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';

createInertiaApp({
    title: (title) => (title ? `${title} · School Portal` : 'School Portal'),
    resolve: (name) => {
        // Each page is its own chunk, so the first load only ships what it needs.
        const pages = import.meta.glob('./pages/**/*.jsx');
        const page = pages[`./pages/${name}.jsx`];
        if (!page) throw new Error(`Unknown page: ${name}`);
        return page();
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
    progress: { color: '#4f46e5', delay: 120, showSpinner: false },
});
