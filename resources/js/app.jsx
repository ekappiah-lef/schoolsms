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
        watchIdle(props.initialPage.props.app?.idle_minutes || 15, !!props.initialPage.props.auth?.user);
        createRoot(el).render(<App {...props} />);
    },
    progress: { color: '#4f46e5', delay: 120, showSpinner: false },
});

/*
 * The server ends a session after `idle_minutes` without requests, and the page's security token goes
 * with it. While the user is working (typing, clicking, scrolling) the session is kept alive; once they
 * stop, the page reloads at the limit (and when an idle tab is shown again) so they land on the login page.
 * A page brought back with Back is shown only after checking the user is still signed in.
 */
function watchIdle(minutes, signedIn) {
    const limit = minutes * 60 * 1000 + 5000;
    const keepAliveEvery = Math.min(4, Math.max(1, minutes / 3)) * 60 * 1000;
    let last = Date.now();
    let timer;
    let pinging = false;
    const reset = () => {
        last = Date.now();
        clearTimeout(timer);
        timer = setTimeout(() => window.location.reload(), limit);
    };
    const ping = () =>
        fetch('/session/ping', { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', cache: 'no-store' })
            .then((r) => r.json())
            .then((j) => !!j.signed_in)
            .catch(() => true);
    const toLogin = () => window.location.replace('/login');
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
    // A page restored from the browser's memory (Back/Forward): load it again from the server.
    window.addEventListener('pageshow', (e) => {
        if (e.persisted) window.location.reload();
    });
    // Signing in or out happens without a full reload: follow the page on screen.
    router.on('navigate', (e) => {
        signedIn = !!e.detail.page?.props?.auth?.user;
    });

    // Activity on the page keeps the session alive (marks typed for a long time are not lost).
    const active = () => {
        if (!signedIn || pinging || Date.now() - last < keepAliveEvery || Date.now() - last > limit) return;
        pinging = true;
        ping()
            .then((ok) => (ok ? reset() : toLogin()))
            .finally(() => (pinging = false));
    };
    ['keydown', 'pointerdown', 'input', 'wheel', 'touchstart'].forEach((t) => window.addEventListener(t, active, { passive: true, capture: true }));

    // Back/Forward within the app: hide the page until the sign-in is confirmed.
    window.addEventListener(
        'popstate',
        (e) => {
            if (!e.state?.props?.auth?.user) return;
            const root = document.documentElement;
            root.style.visibility = 'hidden';
            ping().then((ok) => {
                if (!ok) return toLogin();
                root.style.visibility = '';
                reset();
            });
        },
        true,
    );
}
