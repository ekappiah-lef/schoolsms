import axios from 'axios';

/*
 * Axios instance for the existing JSON endpoints (store/update actions that
 * return `{ ok, msg }`). CSRF protection uses the XSRF-TOKEN cookie Laravel
 * refreshes on every response (axios sends it as X-XSRF-TOKEN), so it stays
 * valid after an in-app login/logout, unlike the token in the page's meta tag.
 */
const http = axios.create({
    headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
    withXSRFToken: true,
});

export default http;

/**
 * Submit a form to one of the legacy JSON endpoints.
 *
 * Resolves with `{ ok, message }` or `{ ok: false, errors }` (field => message)
 * so callers never have to know about status codes.
 */
export async function submitForm(url, data, { method = 'post' } = {}) {
    const body = new FormData();
    Object.entries(data).forEach(([key, value]) => {
        if (value === undefined || value === null) {
            body.append(key, '');
        } else if (value instanceof File) {
            body.append(key, value);
        } else {
            body.append(key, String(value));
        }
    });
    // PHP cannot parse multipart PUT bodies, so spoof the method like Blade forms do.
    if (method.toLowerCase() !== 'post') body.append('_method', method.toUpperCase());

    try {
        const { data: resp } = await http.post(url, body);
        return { ok: resp?.ok !== false, message: resp?.msg ?? 'Saved', data: resp };
    } catch (error) {
        const status = error.response?.status;
        if (status === 422) {
            const errors = Object.fromEntries(
                Object.entries(error.response.data.errors ?? {}).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v]),
            );
            return { ok: false, errors, message: 'Please correct the highlighted fields.' };
        }
        if (status === 419) return { ok: false, message: 'Your session expired. Reload the page and try again.' };
        if (status === 403) return { ok: false, message: 'You are not allowed to perform this action.' };
        if (status === 404) return { ok: false, message: 'The record could not be found.' };
        if (status === 500) {
            return { ok: false, message: 'The server could not save this record. Check for duplicate entries or contact the administrator.' };
        }
        return { ok: false, message: 'Network error. Check your connection and try again.' };
    }
}
