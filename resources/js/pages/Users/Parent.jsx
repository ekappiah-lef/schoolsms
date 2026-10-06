import { Head, Link } from '@inertiajs/react';
import { Mail, MapPin, Pencil, Phone, Users } from 'lucide-react';
import { withAppLayout } from '@/layouts/AppLayout';
import { Breadcrumbs, EmptyState, Panel } from '@/components/app/page';
import { Avatar } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn, formatMoney } from '@/lib/utils';
import { FamilyPanel } from '@/pages/Students/Show';

/** A parent and all their children, with what each child still owes. */
export default function ParentShow({ parent, children, totals, urls }) {
    const showFees = children.some((c) => c.fees);
    const active = children.filter((c) => c.status === 'active').length;

    return (
        <>
            <Head title={parent.name} />
            <div className="mb-5">
                <Breadcrumbs items={[{ label: 'People' }, urls.back ? { label: 'Users', href: urls.back } : { label: 'Users' }, { label: 'Parent' }]} />
                <div className="mt-3 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div className="flex min-w-0 items-center gap-4">
                        <Avatar src={parent.photo} name={parent.name} size="3xl" />
                        <div className="min-w-0">
                            <h1 className="truncate text-2xl font-semibold">{parent.name}</h1>
                            <div className="mt-1 text-base text-fg-muted">
                                Parent of {children.length} {children.length === 1 ? 'child' : 'children'}
                                {active !== children.length ? ` · ${active} at the school now` : ''}
                            </div>
                        </div>
                    </div>
                    {urls.edit && (
                        <Button variant="primary" asChild>
                            <a href={urls.edit}>
                                <Pencil />
                                Edit
                            </a>
                        </Button>
                    )}
                </div>
            </div>

            <div className="grid gap-6 xl:grid-cols-3">
                <div className="space-y-6">
                    <Panel title="Contact">
                        <ul className="space-y-2 text-sm">
                            <Row icon={Phone} value={[parent.phone, parent.phone2].filter(Boolean).join(' · ')} href={parent.phone ? `tel:${parent.phone}` : null} />
                            <Row icon={Mail} value={parent.email} href={parent.email ? `mailto:${parent.email}` : null} />
                            <Row icon={MapPin} value={parent.address} />
                        </ul>
                        {parent.username && <p className="mt-4 text-xs text-fg-muted">Login ID {parent.username}</p>}
                    </Panel>
                    {showFees && (
                        <Panel title="Family fees · all years">
                            <dl className="tabular grid grid-cols-3 gap-3 text-sm">
                                <Total label="Billed" value={totals.amount} />
                                <Total label="Paid" value={totals.paid} tone="success" />
                                <Total label="Still owed" value={totals.balance} tone={totals.balance > 0 ? 'danger' : undefined} />
                            </dl>
                        </Panel>
                    )}
                </div>

                <div className="space-y-6 xl:col-span-2">
                    <Panel title={`Children (${children.length})`}>
                        {children.length ? (
                            <ul className="-my-2 divide-y divide-border">
                                {children.map((c) => (
                                    <li key={c.url} className="flex flex-wrap items-center gap-3 py-3">
                                        <Link href={c.url} className="flex min-w-0 flex-1 items-center gap-3 hover:text-primary">
                                            <Avatar src={c.photo} name={c.name} size="md" />
                                            <div className="min-w-0">
                                                <div className="flex items-center gap-2">
                                                    <span className="truncate font-medium">{c.name}</span>
                                                    {c.status === 'graduated' && <Badge tone="info">Graduated</Badge>}
                                                </div>
                                                <div className="text-xs text-fg-muted">
                                                    {c.status === 'graduated' ? 'Left the school' : c.class || '—'} · {c.adm_no}
                                                </div>
                                            </div>
                                        </Link>
                                        {c.fees && (
                                            <div className="tabular flex items-center gap-4 text-sm">
                                                <span className={cn(c.fees.balance > 0 ? 'text-danger-fg' : 'text-fg-muted')}>
                                                    {c.fees.balance > 0 ? `Owes ${formatMoney(c.fees.balance)}` : 'Fully paid'}
                                                </span>
                                                <Button size="xs" asChild>
                                                    <Link href={c.invoice_url}>Payments</Link>
                                                </Button>
                                            </div>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <EmptyState compact icon={Users} title="No children linked" description="Link this parent when admitting or editing a student." />
                        )}
                    </Panel>
                    {parent.details && <FamilyPanel d={parent.details} />}
                </div>
            </div>
        </>
    );
}

ParentShow.layout = withAppLayout;

function Row({ icon: Icon, value, href }) {
    if (!value) return null;
    return (
        <li className="flex items-start gap-2.5">
            <Icon className="mt-0.5 size-4 shrink-0 text-fg-subtle" />
            {href ? (
                <a href={href} className="break-all hover:text-primary">
                    {value}
                </a>
            ) : (
                <span className="break-words">{value}</span>
            )}
        </li>
    );
}

function Total({ label, value, tone }) {
    return (
        <div>
            <dt className="text-xs text-fg-muted">{label}</dt>
            <dd className={cn('mt-0.5 font-semibold', tone === 'success' && 'text-success-fg', tone === 'danger' && 'text-danger-fg')}>{formatMoney(value)}</dd>
        </div>
    );
}
