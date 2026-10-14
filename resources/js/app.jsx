import '../css/app.css';
import http from './lib/http';

import { createInertiaApp, router } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';

createInertiaApp({
    title: (title) => (title ? `${title} · EduNetwork Portal` : 'EduNetwork Portal'),
    resolve: (name) => {
        // Each page is its own chunk, so the first load only ships what it needs.
        const pages = import.meta.glob('./pages/**/*.jsx');
        const page = pages[`./pages/${name}.jsx`];
        if (!page) throw new Error(`Unknown page: ${name}`);
        return page();
    },
    setup({ el, App, props }) {
        watchIdle(props.initialPage.props.app?.idle_minutes || 30);
        createRoot(el).render(<App {...props} />);
    },
    progress: { color: '#4f46e5', delay: 120, showSpinner: false },
});

/*
 * The server ends a session after `idle_minutes` without requests, and the page's security token goes
 * with it. Reload the page at that moment (and when an idle tab is shown again) so the user lands on a
 * fresh login page instead of getting an error when they sign in or save.
 */
function watchIdle(minutes) {
    const limit = minutes * 60 * 1000 + 5000;
    let last = Date.now();
    let timer;
    const reset = () => {
        last = Date.now();
        clearTimeout(timer);
        timer = setTimeout(() => window.location.reload(), limit);
    };
    reset();
    router.on('finish', reset);
    http.interceptors.response.use(
        (response) => (reset(), response),
        (error) => (reset(), Promise.reject(error)),
    );
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible' && Date.now() - last > limit) window.location.reload();
    });
    // A request sent with an expired token: reload rather than show an error.
    router.on('invalid', (event) => {
        if (event.detail.response?.status === 419) {
            event.preventDefault();
            window.location.reload();
        }
    });
}
