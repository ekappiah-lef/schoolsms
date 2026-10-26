import { Head, Link } from '@inertiajs/react';
import { FileText, ScrollText, UserRound, Users } from 'lucide-react';
import { withAppLayout } from '@/layouts/AppLayout';
import { ModuleHeader } from '@/components/app/module';
import { EmptyState } from '@/components/app/page';
import { Avatar } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn, formatDate, formatMoney } from '@/lib/utils';

const TODAY = { present: ['Present today', 'success'], late: ['Late today', 'warning'], absent: ['Absent today', 'danger'] };

/** A parent's children: class, fees owed, and links to the profile, results and fees statement. */
export default function Children({ children }) {
    return (
        <>
            <Head title="My children" />
            <div className="mx-auto flex w-full max-w-5xl flex-col gap-6">
                <ModuleHeader crumbs={['Family', 'My children']} title="My children" />
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
                                            {c.status === 'away' && <Badge tone="warning">Away</Badge>}
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
                                {c.status !== 'graduated' && (
                                    <div className="flex flex-col gap-2 rounded-lg bg-muted/50 p-3 text-sm">
                                        <div className="flex items-center justify-between gap-2">
                                            <span className="font-medium">Attendance this term</span>
                                            {TODAY[c.attendance.today] ? (
                                                <Badge tone={TODAY[c.attendance.today][1]} dot>
                                                    {TODAY[c.attendance.today][0]}
                                                </Badge>
                                            ) : (
                                                <Badge dot>Not marked today</Badge>
                                            )}
                                        </div>
                                        <dl className="tabular grid grid-cols-3 gap-3">
                                            <div>
                                                <dt className="text-fg-muted">Present</dt>
                                                <dd className="font-semibold text-success-fg">{c.attendance.present}</dd>
                                            </div>
                                            <div>
                                                <dt className="text-fg-muted">Late</dt>
                                                <dd className="font-semibold text-warning-fg">{c.attendance.late}</dd>
                                            </div>
                                            <div>
                                                <dt className="text-fg-muted">Absent</dt>
                                                <dd className={cn('font-semibold', c.attendance.absent ? 'text-danger-fg' : 'text-fg-muted')}>{c.attendance.absent}</dd>
                                            </div>
                                        </dl>
                                        {c.attendance.missed.length > 0 && (
                                            <div className="flex flex-wrap gap-1.5">
                                                {c.attendance.missed.map((m) => (
                                                    <Badge key={m.date} tone={m.status === 'absent' ? 'danger' : 'warning'} shape="tag">
                                                        {m.status === 'absent' ? 'Absent' : 'Late'} {formatDate(m.date, 'd MMM')}
                                                    </Badge>
                                                ))}
                                            </div>
                                        )}
                                    </div>
                                )}
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
                    <EmptyState icon={Users} title="No children linked to your account" />
                )}
            </div>
        </>
    );
}

Children.layout = withAppLayout;
