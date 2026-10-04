import { Head, Link } from '@inertiajs/react';
import { FileText, ScrollText, UserRound, Users } from 'lucide-react';
import { withAppLayout } from '@/layouts/AppLayout';
import { ModuleHeader } from '@/components/app/module';
import { EmptyState } from '@/components/app/page';
import { Avatar } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn, formatMoney } from '@/lib/utils';

/** A parent's children: class, fees owed, and links to the profile, results and fees statement. */
export default function Children({ children }) {
    return (
        <>
            <Head title="My children" />
            <div className="mx-auto flex w-full max-w-5xl flex-col gap-6">
                <ModuleHeader crumbs={['Family', 'My children']} title="My children" description="Your children at the school, what is still owed, and their results." />
                {children.length ? (
                    <div className="grid gap-4 md:grid-cols-2">
                        {children.map((c) => (
                            <div key={c.urls.profile} className="flex flex-col gap-4 rounded-lg bg-surface p-5 shadow-card">
                                <div className="flex items-center gap-4">
                                    <Avatar src={c.photo} name={c.name} size="lg" />
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-center gap-2">
                                            <span className="truncate text-lg font-semibold">{c.name}</span>
                                            {c.status === 'graduated' && <Badge tone="info">Graduated</Badge>}
                                        </div>
                                        <div className="text-sm text-fg-muted">
                                            {c.class} · {c.adm_no}
                                        </div>
                                    </div>
                                </div>
                                <dl className="tabular grid grid-cols-3 gap-3 rounded-lg bg-muted/50 p-3 text-sm">
                                    <div>
                                        <dt className="text-fg-muted">Billed</dt>
                                        <dd className="font-semibold">{formatMoney(c.fees.amount)}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-fg-muted">Paid</dt>
                                        <dd className="font-semibold text-success-fg">{formatMoney(c.fees.paid)}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-fg-muted">Owed</dt>
                                        <dd className={cn('font-semibold', c.fees.balance > 0 ? 'text-danger-fg' : 'text-fg-muted')}>{formatMoney(c.fees.balance)}</dd>
                                    </div>
                                </dl>
                                <div className="flex flex-wrap gap-2">
                                    <Button size="sm" asChild>
                                        <Link href={c.urls.profile}>
                                            <UserRound />
                                            Profile
                                        </Link>
                                    </Button>
                                    <Button size="sm" asChild>
                                        <a href={c.urls.results}>
                                            <ScrollText />
                                            Results
                                        </a>
                                    </Button>
                                    <Button size="sm" asChild>
                                        <a href={c.urls.fees} target="_blank" rel="noreferrer">
                                            <FileText />
                                            Fees statement
                                        </a>
                                    </Button>
                                </div>
                            </div>
                        ))}
                    </div>
                ) : (
                    <EmptyState icon={Users} title="No children linked to your account" description="Ask the school office to link your children." />
                )}
            </div>
        </>
    );
}

Children.layout = withAppLayout;
