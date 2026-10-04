import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';
import { format, formatDistanceToNowStrict, isValid, parseISO } from 'date-fns';

export function cn(...inputs) {
    return twMerge(clsx(inputs));
}

const numberFormat = new Intl.NumberFormat('en-GB');
const compactFormat = new Intl.NumberFormat('en-GB', { notation: 'compact', maximumFractionDigits: 1 });

export const formatNumber = (n) => (n === null || n === undefined || n === '' ? '—' : numberFormat.format(Number(n)));
export const formatCompact = (n) => compactFormat.format(Number(n || 0));

/** Amounts are stored as plain integers with no currency in the database. */
export const formatMoney = (n) => formatNumber(n);

export function parseDate(value) {
    if (!value) return null;
    if (value instanceof Date) return isValid(value) ? value : null;
    const iso = parseISO(String(value));
    if (isValid(iso)) return iso;
    // Legacy values such as 05/21/2010 from the old date picker
    const loose = new Date(value);
    return isValid(loose) ? loose : null;
}

export function formatDate(value, pattern = 'd MMM yyyy') {
    const date = parseDate(value);
    return date ? format(date, pattern) : value || '—';
}

export function timeAgo(value) {
    const date = parseDate(value);
    return date ? `${formatDistanceToNowStrict(date)} ago` : '';
}

export function initials(name = '') {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((p) => p[0]?.toUpperCase())
        .join('');
}

export function percent(part, whole) {
    if (!whole) return 0;
    return Math.round((part / whole) * 1000) / 10;
}

export function ordinal(n) {
    if (!n) return '—';
    const s = ['th', 'st', 'nd', 'rd'];
    const v = n % 100;
    return n + (s[(v - 20) % 10] || s[v] || s[0]);
}

/**
 * Uploaded photos are saved as absolute URLs using whatever host the app was
 * opened on at the time (e.g. a LAN IP). Serve files from this app's own
 * storage/asset folders relative to the current origin so they always load.
 */
export function photoUrl(url) {
    if (!url) return url;
    const m = /^https?:\/\/[^/]+(\/(?:storage|global_assets)\/.*)$/i.exec(url);
    return m ? m[1].replace(/\/{2,}/g, '/') : url;
}

/** The legacy app stores a placeholder image for users without a photo. */
export function isPlaceholderPhoto(url) {
    return !url || /global_assets\/images\/user\.png$/.test(url);
}
