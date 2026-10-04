import { useId, cloneElement, isValidElement } from 'react';
import { CircleAlert } from 'lucide-react';
import { cn } from '@/lib/utils';

/**
 * Label + control + hint/error. The control receives id/aria props automatically.
 */
export function FormField({ label, required, hint, error, children, className, aside }) {
    const id = useId();
    const control = isValidElement(children)
        ? cloneElement(children, {
              id: children.props.id ?? id,
              invalid: children.props.invalid ?? (error ? true : undefined),
              'aria-invalid': error ? true : undefined,
              'aria-describedby': error || hint ? `${id}-desc` : undefined,
          })
        : children;

    return (
        <div className={cn('flex min-w-0 flex-col gap-1.5', className)}>
            {label && (
                <div className="flex items-baseline justify-between gap-2">
                    <label htmlFor={children?.props?.id ?? id} className="text-sm font-medium text-fg">
                        {label}
                        {required && <span className="ml-0.5 text-danger">*</span>}
                    </label>
                    {aside && <span className="text-xs text-fg-subtle">{aside}</span>}
                </div>
            )}
            {control}
            {error ? (
                <p id={`${id}-desc`} className="flex items-center gap-1 text-xs text-danger-fg">
                    <CircleAlert className="size-3.5 shrink-0" />
                    {error}
                </p>
            ) : hint ? (
                <p id={`${id}-desc`} className="text-xs text-fg-muted">
                    {hint}
                </p>
            ) : null}
        </div>
    );
}

/** A titled group of fields inside a form panel. */
export function FormSection({ id, title, description, children, className, columns = 2 }) {
    return (
        <section id={id} className={cn('scroll-mt-20 grid gap-x-8 gap-y-5 py-6 first:pt-0 last:pb-0 lg:grid-cols-[220px_1fr]', className)}>
            <div>
                <h3 className="text-base font-semibold">{title}</h3>
                {description && <p className="mt-1 text-sm text-fg-muted">{description}</p>}
            </div>
            <div className={cn('grid gap-x-4 gap-y-4', columns === 2 && 'sm:grid-cols-2', columns === 3 && 'sm:grid-cols-2 xl:grid-cols-3')}>
                {children}
            </div>
        </section>
    );
}

/** Summary of server-side errors shown above a form. */
export function FormErrorSummary({ errors, labels = {} }) {
    const entries = Object.entries(errors ?? {});
    if (!entries.length) return null;
    return (
        <div role="alert" className="rounded-lg border border-danger/25 bg-danger-soft px-4 py-3 text-sm text-danger-fg">
            <p className="flex items-center gap-2 font-semibold">
                <CircleAlert className="size-4" />
                {entries.length === 1 ? 'One field needs attention' : `${entries.length} fields need attention`}
            </p>
            <ul className="mt-1.5 list-disc space-y-0.5 pl-9">
                {entries.map(([field, message]) => (
                    <li key={field}>
                        {labels[field] ? <span className="font-medium">{labels[field]}: </span> : null}
                        {message}
                    </li>
                ))}
            </ul>
        </div>
    );
}
