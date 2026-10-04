import { Link } from '@inertiajs/react';
import { ChevronRight, CircleAlert, Inbox } from 'lucide-react';
import { cn } from '@/lib/utils';

/**
 * Page title block used on every screen: eyebrow/breadcrumbs, title, meta, actions.
 * `breadcrumbs`: [{ label, href? }]
 */
export function PageHeader({ breadcrumbs, title, meta, description, actions, className, children }) {
    return (
        <div className={cn('mb-6 flex flex-col gap-4 md:flex-row md:items-end md:justify-between', className)}>
            <div className="min-w-0">
                {breadcrumbs?.length > 0 && <Breadcrumbs items={breadcrumbs} />}
                <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <h1 className="truncate text-2xl font-semibold">{title}</h1>
                    {meta}
                </div>
                {description && <p className="mt-1 max-w-3xl text-base text-fg-muted">{description}</p>}
                {children}
            </div>
            {actions && <div className="flex shrink-0 flex-wrap items-center gap-2">{actions}</div>}
        </div>
    );
}

export function Breadcrumbs({ items }) {
    return (
        <nav aria-label="Breadcrumb" className="mb-1.5">
            <ol className="flex flex-wrap items-center gap-1 text-2xs font-semibold uppercase tracking-[0.06em] text-fg-subtle">
                {items.map((item, i) => (
                    <li key={i} className="flex items-center gap-1">
                        {i > 0 && <ChevronRight className="size-3" />}
                        {item.href ? (
                            <Link href={item.href} className="transition-colors hover:text-fg">
                                {item.label}
                            </Link>
                        ) : (
                            <span className={i === items.length - 1 ? 'text-primary' : undefined}>{item.label}</span>
                        )}
                    </li>
                ))}
            </ol>
        </nav>
    );
}

/** Bordered surface. Use `flush` when the content (e.g. a table) provides its own padding. */
export function Panel({ title, description, actions, children, className, bodyClassName, flush = false, footer }) {
    return (
        <section className={cn('panel flex min-w-0 flex-col', className)}>
            {(title || actions) && (
                <header className="flex items-start justify-between gap-3 border-b border-border px-4 py-3">
                    <div className="min-w-0">
                        {title && <h2 className="text-base font-semibold">{title}</h2>}
                        {description && <p className="mt-0.5 text-sm text-fg-muted">{description}</p>}
                    </div>
                    {actions && <div className="flex shrink-0 items-center gap-2">{actions}</div>}
                </header>
            )}
            <div className={cn(!flush && 'p-4', 'min-w-0 flex-1', bodyClassName)}>{children}</div>
            {footer && <footer className="border-t border-border px-4 py-2.5">{footer}</footer>}
        </section>
    );
}

/**
 * KPI tile. Deliberately plain: label, value, one line of context.
 */
export function StatCard({ label, value, context, icon: Icon, href, tone = 'neutral', className }) {
    const body = (
        <>
            <div className="flex items-center justify-between gap-2">
                <span className="overline-label">{label}</span>
                {Icon && <Icon className="size-4 text-fg-subtle" />}
            </div>
            <div className="tabular mt-2 text-2xl font-semibold tracking-tight">{value}</div>
            {context && (
                <div
                    className={cn(
                        'mt-1 truncate text-sm',
                        tone === 'danger' ? 'text-danger-fg' : tone === 'success' ? 'text-success-fg' : 'text-fg-muted',
                    )}
                >
                    {context}
                </div>
            )}
        </>
    );
    const cls = cn('block min-w-0 px-4 py-3.5', className);
    return href ? (
        <Link href={href} className={cn(cls, 'transition-colors hover:bg-muted')}>
            {body}
        </Link>
    ) : (
        <div className={cls}>{body}</div>
    );
}

/** Row of KPIs inside a single bordered strip, divided by hairlines (avoids a wall of cards). */
export function StatStrip({ children, className }) {
    return (
        <div className={cn('panel grid divide-y divide-border overflow-hidden sm:grid-cols-2 sm:divide-y-0 lg:grid-cols-4 [&>*]:border-border sm:[&>*:nth-child(odd)]:border-r lg:[&>*:not(:last-child)]:border-r', className)}>
            {children}
        </div>
    );
}

export function EmptyState({ icon: Icon = Inbox, title, description, action, className, compact }) {
    return (
        <div className={cn('flex flex-col items-center justify-center text-center', compact ? 'px-4 py-8' : 'px-6 py-14', className)}>
            <div className="mb-3 flex size-10 items-center justify-center rounded-lg border border-border bg-muted text-fg-subtle">
                <Icon className="size-5" />
            </div>
            <p className="text-base font-semibold">{title}</p>
            {description && <p className="mt-1 max-w-sm text-sm text-fg-muted">{description}</p>}
            {action && <div className="mt-4">{action}</div>}
        </div>
    );
}

export function ErrorState({ title = 'Something went wrong', description, action }) {
    return <EmptyState icon={CircleAlert} title={title} description={description} action={action} />;
}

/** Definition list used on profile/detail pages. */
export function DescriptionList({ items, columns = 2, className }) {
    return (
        <dl className={cn('grid gap-x-6 gap-y-4', columns === 2 && 'sm:grid-cols-2', columns === 3 && 'sm:grid-cols-2 xl:grid-cols-3', className)}>
            {items
                .filter((i) => !i.hidden)
                .map((item) => (
                    <div key={item.label} className="min-w-0">
                        <dt className="text-xs text-fg-muted">{item.label}</dt>
                        <dd className={cn('mt-0.5 break-words text-base', !item.value && item.value !== 0 && 'text-fg-subtle')}>
                            {item.value || item.value === 0 ? item.value : '—'}
                        </dd>
                    </div>
                ))}
        </dl>
    );
}
