import defaultTheme from 'tailwindcss/defaultTheme';

/*
 * Design tokens follow stitch_minimalist_logo_login_page/clarity_edtech/DESIGN.md.
 * Colors are CSS variables (see resources/css/app.css) so components only ever
 * reference semantic names.
 */
const token = (name) => `rgb(var(--${name}) / <alpha-value>)`;

/** @type {import('tailwindcss').Config} */
export default {
    content: ['./resources/views/app.blade.php', './resources/js/**/*.{js,jsx}'],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', 'InterVariable', ...defaultTheme.fontFamily.sans],
            },
            fontSize: {
                // label-sm from the design doc: uppercase overlines / table headers
                '2xs': ['0.6875rem', { lineHeight: '0.875rem', letterSpacing: '0.04em' }],
                xs: ['0.75rem', { lineHeight: '1.125rem' }],
                sm: ['0.8125rem', { lineHeight: '1.25rem' }],
                base: ['0.875rem', { lineHeight: '1.375rem' }],
                md: ['1rem', { lineHeight: '1.5rem' }],
                lg: ['1.125rem', { lineHeight: '1.625rem', letterSpacing: '-0.015em' }],
                xl: ['1.375rem', { lineHeight: '1.875rem', letterSpacing: '-0.02em' }],
                '2xl': ['1.625rem', { lineHeight: '2.125rem', letterSpacing: '-0.025em' }],
            },
            colors: {
                canvas: token('canvas'),
                surface: token('surface'),
                muted: token('muted'),
                subtle: token('subtle'),
                border: token('border'),
                'border-strong': token('border-strong'),
                fg: token('fg'),
                'fg-muted': token('fg-muted'),
                'fg-subtle': token('fg-subtle'),
                primary: {
                    DEFAULT: token('primary'),
                    hover: token('primary-hover'),
                    soft: token('primary-soft'),
                    fg: token('primary-fg'),
                },
                success: { DEFAULT: token('success'), soft: token('success-soft'), fg: token('success-fg') },
                warning: { DEFAULT: token('warning'), soft: token('warning-soft'), fg: token('warning-fg') },
                danger: { DEFAULT: token('danger'), soft: token('danger-soft'), fg: token('danger-fg') },
                info: { DEFAULT: token('info'), soft: token('info-soft'), fg: token('info-fg') },
                sidebar: {
                    DEFAULT: token('sidebar'),
                    border: token('sidebar-border'),
                },
            },
            borderRadius: {
                sm: '0.25rem',
                DEFAULT: '0.375rem',
                md: '0.375rem',
                lg: '0.5rem',
                xl: '0.75rem',
            },
            boxShadow: {
                xs: '0 1px 2px 0 rgb(19 27 46 / 0.05)',
                card: '0 1px 3px 0 rgb(19 27 46 / 0.06), 0 1px 2px -1px rgb(19 27 46 / 0.04)',
                'card-hover': '0 6px 16px -4px rgb(19 27 46 / 0.1), 0 2px 4px -2px rgb(19 27 46 / 0.05)',
                field: '0 1px 2px 0 rgb(19 27 46 / 0.06), 0 0 0 1px rgb(199 196 216 / 0.55)',
                sm: '0 1px 2px 0 rgb(15 23 42 / 0.05), 0 1px 1px 0 rgb(15 23 42 / 0.02)',
                md: '0 4px 6px -1px rgb(15 23 42 / 0.06), 0 2px 4px -2px rgb(15 23 42 / 0.03)',
                lg: '0 20px 25px -5px rgb(15 23 42 / 0.08), 0 8px 10px -6px rgb(15 23 42 / 0.04)',
                focus: '0 0 0 1px rgb(var(--surface)), 0 0 0 3px rgb(var(--primary) / 0.35)',
                btn: 'inset 0 1px 0 rgb(255 255 255 / 0.15)',
            },
            keyframes: {
                'fade-in': { from: { opacity: 0 }, to: { opacity: 1 } },
                'slide-in-left': { from: { transform: 'translateX(-100%)' }, to: { transform: 'translateX(0)' } },
                'slide-in-right': { from: { transform: 'translateX(100%)' }, to: { transform: 'translateX(0)' } },
                'pop-in': {
                    from: { opacity: 0, transform: 'translateY(4px) scale(0.98)' },
                    to: { opacity: 1, transform: 'translateY(0) scale(1)' },
                },
                progress: { from: { transform: 'translateX(-100%)' }, to: { transform: 'translateX(250%)' } },
            },
            animation: {
                'fade-in': 'fade-in 120ms ease-out',
                'slide-in-left': 'slide-in-left 180ms cubic-bezier(0.2, 0.8, 0.2, 1)',
                'slide-in-right': 'slide-in-right 180ms cubic-bezier(0.2, 0.8, 0.2, 1)',
                'pop-in': 'pop-in 120ms ease-out',
                progress: 'progress 1s ease-in-out infinite',
            },
        },
    },
    plugins: [],
};
