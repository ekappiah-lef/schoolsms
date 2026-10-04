import { forwardRef } from 'react';
import { Link } from '@inertiajs/react';

/**
 * Link that uses Inertia for pages rebuilt in React and a normal page load for
 * pages still rendered by Blade (Inertia would otherwise show them in a modal).
 */
export const NavLink = forwardRef(function NavLink({ href, spa = true, children, ...props }, ref) {
    if (spa) {
        return (
            <Link ref={ref} href={href} {...props}>
                {children}
            </Link>
        );
    }
    return (
        <a ref={ref} href={href} {...props}>
            {children}
        </a>
    );
});

/** Programmatic equivalent of NavLink. */
export function visit(router, href, spa) {
    if (spa) router.visit(href);
    else window.location.href = href;
}
