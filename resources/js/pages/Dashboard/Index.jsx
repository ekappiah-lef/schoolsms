import { useMemo, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Area, AreaChart, Cell, Pie, PieChart, ResponsiveContainer, Tooltip, XAxis } from 'recharts';
import {
    ArrowRight,
    BadgeCheck,
    BookOpen,
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    CircleCheck,
    NotebookPen,
    Plus,
    Scale,
    ScrollText,
    School,
    UserPlus,
    UserRound,
    Users,
    Wallet,
} from 'lucide-react';
import {
    addMonths,
    eachDayOfInterval,
    endOfMonth,
    endOfWeek,
    format,
    isSameDay,
    isSameMonth,
    startOfMonth,
    startOfWeek,
} from 'date-fns';
import AppLayout, { quickActions } from '@/layouts/AppLayout';
import { EmptyState } from '@/components/app/page';
import { HorizontalBars, Legend, SERIES, StackedBars } from '@/components/app/charts';
import { NavLink } from '@/components/app/nav-link';
import { Avatar } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Select } from '@/components/ui/select';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { cn, formatCompact, formatDate, formatMoney, formatNumber, parseDate, percent, timeAgo } from '@/lib/utils';

// Fee donut colours from the Stitch dashboard (primary, secondary, tertiary, outline).
const FEE_COLORS = ['#4f46e5', '#006591', '#006e4b', '#777587', '#9085e9', '#39b8fd'];

/*
 * Dashboard — implements stitch_minimalist_logo_login_page/lef_executive_school_management_dashboard,
 * using only real data. Sections the backend has no data for (seat capacity, events)
 * are replaced with the closest real equivalent and labelled as such.
 */
export default function Dashboard(props) {
    const { session, term, stats, links, enrolment, performance, fees, finance, teaching, student, children, classMonitor, exams } = props;
    const { auth, nav } = usePage().props;
    const actions = quickActions(auth.can, nav ?? []);

    return (
        <>
            <Head title="Dashboard" />

            {/* Page header */}
            <div className="mb-8 flex flex-col justify-between gap-4 md:flex-row md:items-end">
                <h1 className="text-[2rem] font-semibold leading-10 tracking-[-0.025em]">
                    {auth.can.teamSA ? 'Academic and Operations Dashboard' : `Welcome, ${auth.user.name.split(' ')[0]}`}
                </h1>
                <div className="flex items-center gap-2">
                    <span className="inline-flex h-9 items-center gap-1.5 rounded-lg bg-muted px-3 text-sm font-medium shadow-xs">
                        <CalendarDays className="size-[18px] text-fg-subtle" />
                        {session.replace('-', ' – ')} Year
                    </span>
                    {actions.length > 0 && (
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="primary" className="h-9 rounded-lg">
                                    <Plus />
                                    Quick Action
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent>
                                <DropdownMenuLabel>Quick actions</DropdownMenuLabel>
                                {actions.map((a) => (
                                    <DropdownMenuItem key={a.label} asChild>
                                        <NavLink href={a.href} spa={a.spa}>
                                            {a.label}
                                        </NavLink>
                                    </DropdownMenuItem>
                                ))}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    )}
                </div>
            </div>

            <div className="flex flex-col gap-8">
                {stats && <HeroStats stats={stats} links={links} enrolment={enrolment} />}

                {classMonitor?.length > 0 && <ClassMonitor classes={classMonitor} />}

                {finance && <FinanceBreakdown finance={finance} session={session} />}

                {fees && (
                    <section className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                        <FeeIntake fees={fees} />
                        <CollectionTrend fees={fees} />
                    </section>
                )}

                {teaching && <TeachingSection teaching={teaching} />}
                {student && <StudentSection student={student} />}
                {children && <ChildrenSection items={children} />}

                <section className="grid grid-cols-1 gap-6 lg:grid-cols-12">
                    <AcademicCalendar term={term} className="lg:col-span-5" />
                    <Agenda exams={exams} term={term} session={session} className="lg:col-span-7" />
                </section>

                {/* Additional insight beyond the reference design */}
                {(enrolment || performance !== undefined) && (
                    <section className="grid grid-cols-1 gap-6 xl:grid-cols-5">
                        {enrolment && (
                            <Card className="xl:col-span-3" eyebrow="Enrolment" title="Students by class" action={<Legend items={[{ label: 'Male', color: SERIES.one }, { label: 'Female', color: SERIES.two }, { label: 'Not recorded', color: SERIES.rest }]} />}>
                                {enrolment.length ? (
                                    <StackedBars
                                        data={enrolment}
                                        xKey="class"
                                        series={[
                                            { key: 'male', label: 'Male', color: SERIES.one },
                                            { key: 'female', label: 'Female', color: SERIES.two },
                                            { key: 'unspecified', label: 'Not recorded', color: SERIES.rest },
                                        ]}
                                        onBarClick={(d) => d?.url && router.visit(d.url)}
                                    />
                                ) : (
                                    <EmptyState compact title="No students enrolled yet" />
                                )}
                            </Card>
                        )}
                        {performance !== undefined && (
                            <Card className="xl:col-span-2" eyebrow="Academic performance" title={performance ? `${performance.exam}` : 'Class averages'}>
                                {performance?.classes?.length ? (
                                    <HorizontalBars data={performance.classes} labelKey="class" valueKey="average" name="Class average" suffix="%" />
                                ) : (
                                    <EmptyState compact icon={NotebookPen} title="No results recorded yet" description="Class averages appear once marks are entered this year." />
                                )}
                            </Card>
                        )}
                    </section>
                )}

                {fees?.recent?.length > 0 && <RecentPayments fees={fees} />}
            </div>
        </>
    );
}

Dashboard.layout = (page) => <AppLayout>{page}</AppLayout>;

/* ------------------------------------------------------------------ */

function Card({ eyebrow, title, description, action, children, className, padded = true }) {
    return (
        <div className={cn('flex flex-col gap-6 rounded-lg bg-surface shadow-card', padded && 'p-6 md:p-8', className)}>
            {(title || eyebrow) && (
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="flex min-w-0 flex-col gap-0.5">
                        {eyebrow && <span className="text-2xs font-semibold uppercase tracking-wider text-fg-subtle">{eyebrow}</span>}
                        {title && <h2 className="text-lg font-semibold tracking-tight text-fg">{title}</h2>}
                        {description && <p className="text-base text-fg-muted">{description}</p>}
                    </div>
                    {action}
                </div>
            )}
            {children}
        </div>
    );
}

function HeroCard({ label, badge, value, footLeft, footRight, footRightTone, href }) {
    const body = (
        <>
            <div className="flex items-center justify-between gap-2">
                <span className="text-2xs font-semibold uppercase tracking-wider text-fg-subtle">{label}</span>
            </div>
            <div className="mt-3">
                <span className="tabular text-5xl font-semibold leading-none tracking-[-0.03em] text-fg">{value}</span>
            </div>
        </>
    );
    const cls = 'flex flex-col justify-between rounded-lg bg-surface p-6 shadow-card transition-shadow hover:shadow-card-hover';
    return href ? (
        <Link href={href} className={cls}>
            {body}
        </Link>
    ) : (
        <div className={cls}>{body}</div>
    );
}

function Pill({ icon: Icon, children, tone = 'default' }) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 rounded-full bg-muted px-1.5 py-0.5 text-2xs font-semibold',
                tone === 'success' && 'text-success-fg',
                tone === 'info' && 'text-info',
                tone === 'primary' && 'text-primary-hover',
                tone === 'default' && 'text-fg-muted',
            )}
        >
            {Icon && <Icon className="size-3.5" />}
            {children}
        </span>
    );
}

function HeroStats({ stats, links, enrolment }) {
    const girls = (enrolment ?? []).reduce((a, r) => a + r.female, 0);
    const ratio = stats.teachers ? (stats.students / stats.teachers).toFixed(1) : null;
    return (
        <section className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <HeroCard
                label="Total students"
                value={formatNumber(stats.students)}
                footLeft="Active students"
                footRight={`${stats.sections} sections`}
                href={links.students}
            />
            <HeroCard
                label="Teaching staff"
                value={formatNumber(stats.teachers)}
                footLeft="Faculty ratio"
                footRight={`${girls} girls`}
                href={links.users}
            />
            <HeroCard
                label="Admin team"
                value={formatNumber(stats.admins)}
                footLeft="Accounts office"
                footRight={`${stats.accountants} accountants`}
                footRightTone="success"
                href={links.users}
            />
            <HeroCard
                label="Registered parents"
                value={formatNumber(stats.parents)}
                footLeft="Linked to students"
                footRight={`${stats.linkedParents} accounts`}
                href={links.users}
            />
        </section>
    );
}

function ClassMonitor({ classes }) {
    const [selectedId, setSelectedId] = useState(() => (classes.find((c) => c.enrolled > 0) ?? classes[0]).id);
    const c = classes.find((x) => x.id === selectedId) ?? classes[0];
    const teachers = [...new Set(c.sections.map((s) => s.teacher).filter(Boolean))];
    const total = classes.reduce((a, x) => a + x.enrolled, 0);

    return (
        <section className="flex flex-col gap-6 rounded-lg bg-surface p-6 shadow-card md:p-8">
            <div className="flex flex-col justify-between gap-4 lg:flex-row lg:items-center">
                <div className="flex flex-col gap-0.5">
                    <h2 className="text-2xl font-semibold tracking-tight">Class Enrolment Monitor</h2>
                    <p className="text-base text-fg-muted">Select a class to inspect its enrolment, sections, class teachers and subjects.</p>
                </div>
                <Select
                    className="w-full sm:w-60"
                    value={String(c.id)}
                    onChange={(v) => setSelectedId(Number(v))}
                    options={classes.map((x) => ({ value: String(x.id), label: x.name }))}
                />
            </div>

            <div className="flex flex-col gap-6 rounded-lg bg-muted/60 p-6">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="text-lg font-semibold">
                            {c.name}
                            {c.type ? <span className="font-normal text-fg-muted"> · {c.type}</span> : null}
                        </span>
                        <span className="size-1 rounded-full bg-border-strong" />
                        <span className="flex items-center gap-1 text-xs text-fg-muted">
                            <UserRound className="size-4 text-fg-subtle" />
                            {teachers.length ? `Lead: ${teachers.join(', ')}` : 'No class teacher assigned'}
                        </span>
                        <span className="size-1 rounded-full bg-border-strong" />
                        <span className="flex items-center gap-1 text-xs text-fg-muted">
                            <School className="size-4 text-fg-subtle" />
                            {c.sections.length} {c.sections.length === 1 ? 'section' : 'sections'}
                        </span>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Metric label="Active students" value={c.enrolled} note={`of ${total} in the school`} tone="primary" />
                    <Metric label="Gender split" value={`${c.male} · ${c.female}`} note={`Male · Female${c.enrolled - c.male - c.female > 0 ? ` (${c.enrolled - c.male - c.female} not recorded)` : ''}`} />
                    <CapacityMetric c={c} />
                    <Metric label="Subjects offered" value={c.subjects} note={`${c.sections.length} ${c.sections.length === 1 ? 'section' : 'sections'}`} tone="success" />
                </div>

                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div className="flex flex-wrap items-center gap-2 text-xs text-fg-muted">
                        <BadgeCheck className="size-[18px] text-success" />
                        {c.sections.length ? (
                            c.sections.map((s) => (
                                <span key={s.name} className={cn('rounded bg-surface px-2 py-0.5 shadow-xs', s.capacity && s.students >= s.capacity && 'text-danger-fg')}>
                                    {s.name}:{' '}
                                    <span className="font-medium text-fg">
                                        {s.capacity ? `${Math.min(s.students, s.capacity)}/${s.capacity}` : s.students}
                                    </span>
                                    {s.capacity && s.students > s.capacity ? <span className="font-semibold"> +{s.students - s.capacity}</span> : null}
                                    {s.teacher ? ` · ${s.teacher}` : ''}
                                </span>
                            ))
                        ) : (
                            <span>No sections yet</span>
                        )}
                    </div>
                    <div className="flex items-center gap-2">
                        <Link href={c.urls.roster} className="flex h-9 items-center gap-1.5 px-3 text-sm font-medium text-fg-muted transition-colors hover:text-fg">
                            <Users className="size-[18px]" />
                            View Class Roster
                        </Link>
                        {c.urls.admit && (
                            <Button variant="primary" className="h-9 rounded-lg" asChild>
                                <Link href={c.urls.admit}>
                                    <UserPlus />
                                    Admit Student
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>
            </div>
        </section>
    );
}

/** Enrolled against capacity (sum of section capacities), e.g. 18/24. */
function CapacityMetric({ c }) {
    if (!c.capacity) {
        return (
            <div className="flex flex-col gap-0.5 rounded-md bg-surface p-3 shadow-xs">
                <span className="text-2xs font-semibold uppercase tracking-wider text-fg-subtle">Class capacity</span>
                <span className="text-[2rem] font-semibold leading-10 tracking-tight text-fg-subtle">—</span>
                <span className="text-xs text-fg-muted">
                    {c.sectionsWithCapacity ? `Set on ${c.sectionsWithCapacity} of ${c.sections.length} sections` : 'Not set'} ·{' '}
                    <Link href="/sections" className="text-primary hover:underline">
                        Sections
                    </Link>
                </span>
            </div>
        );
    }
    const left = c.capacity - c.enrolled;
    const pct = Math.min(100, Math.round((c.enrolled / c.capacity) * 100));
    return (
        <div className="flex flex-col gap-1 rounded-md bg-surface p-3 shadow-xs">
            <span className="text-2xs font-semibold uppercase tracking-wider text-fg-subtle">Class capacity</span>
            <span className={cn('tabular whitespace-nowrap text-[2rem] font-semibold leading-10 tracking-tight', left <= 0 && 'text-danger-fg')}>
                {Math.min(c.enrolled, c.capacity)}/{c.capacity}
                {left < 0 && <span className="ml-1 text-lg">+{-left}</span>}
            </span>
            <span className={cn('text-xs', left <= 0 ? 'text-danger-fg' : 'text-fg-muted')}>{left > 0 ? `${left} places left` : left === 0 ? 'Full' : `Full · ${-left} over capacity`}</span>
            <div className="h-1.5 overflow-hidden rounded-full bg-subtle">
                <div className={cn('h-full rounded-full', left <= 0 ? 'bg-danger' : pct >= 85 ? 'bg-warning' : 'bg-primary')} style={{ width: `${pct}%` }} />
            </div>
        </div>
    );
}

/** Detailed finance breakdown for the accounts team, linking to the finance dashboard. */
function FinanceBreakdown({ finance, session }) {
    const { fees, cashflow, balance, urls } = finance;
    const blocks = [
        { label: 'School Fees', v: fees.school, note: fees.school.discount ? `${formatMoney(fees.school.discount)} discounts given` : 'Tuition and termly charges' },
        { label: 'Optional Fees', v: fees.optional, note: 'Feeding, bus, activities, books' },
        { label: 'Overall Fees', v: fees.total, note: `Year ${session}` },
    ];

    return (
        <section className="flex flex-col gap-6 rounded-lg bg-surface p-6 shadow-card md:p-8">
            <div className="flex flex-col justify-between gap-3 md:flex-row md:items-end">
                <div className="flex flex-col gap-0.5">
                    <span className="text-2xs font-semibold uppercase tracking-wider text-fg-subtle">Financial overview</span>
                    <h2 className="text-2xl font-semibold tracking-tight">Finance Overview</h2>
                    <p className="text-base text-fg-muted">A clear view of fees billed, payments received, outstanding balances, and cash flow for the academic year.</p>
                </div>
                <Link href={urls.dashboard} className="flex items-center gap-0.5 text-sm font-medium text-primary hover:underline">
                    Finance dashboard <ArrowRight className="size-4" />
                </Link>
            </div>

            <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                {blocks.map((b) => (
                    <div key={b.label} className="flex flex-col gap-3 rounded-lg bg-muted/60 p-5">
                        <div className="flex items-center justify-between">
                            <span className="text-sm font-semibold">{b.label}</span>
                        </div>
                        <div className="grid grid-cols-3 gap-2">
                            <Figure label="Billed" value={b.v.expected} />
                            <Figure label="Paid" value={b.v.paid} tone="success" />
                            <Figure label="Due" value={b.v.due} tone="danger" />
                        </div>
                        <div className="h-2 overflow-hidden rounded-full bg-surface">
                            <div className="h-full rounded-full bg-primary" style={{ width: `${Math.min(100, percent(b.v.paid, b.v.expected))}%` }} />
                        </div>
                        <span className="text-xs text-fg-muted">{b.note}</span>
                    </div>
                ))}
            </div>

            <div className="grid grid-cols-1 gap-6 xl:grid-cols-5">
                <div className="xl:col-span-3">
                    <h3 className="mb-2 text-sm font-semibold">By class type</h3>
                    <div className="overflow-hidden rounded-lg shadow-field">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="border-b border-border bg-canvas text-2xs font-semibold uppercase text-fg-muted">
                                    <th className="h-9 px-4">Class type</th>
                                    <th className="h-9 px-3 text-right">Students</th>
                                    <th className="h-9 px-3 text-right">Billed</th>
                                    <th className="h-9 px-3 text-right">Paid</th>
                                    <th className="h-9 px-4 text-right">Due</th>
                                </tr>
                            </thead>
                            <tbody className="tabular">
                                {fees.byType.map((t) => (
                                    <tr key={t.type} className="border-b border-border last:border-0">
                                        <td className="px-4 py-2.5 font-medium">{t.type}</td>
                                        <td className="px-3 text-right text-fg-muted">{t.students}</td>
                                        <td className="px-3 text-right">{formatMoney(t.total.expected)}</td>
                                        <td className="px-3 text-right text-success-fg">{formatMoney(t.total.paid)}</td>
                                        <td className={cn('px-4 text-right font-medium', t.total.due > 0 ? 'text-danger-fg' : 'text-fg-muted')}>{formatMoney(t.total.due)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
                <div className="flex flex-col gap-4 xl:col-span-2">
                    <div>
                        <h3 className="mb-2 text-sm font-semibold">Optional services</h3>
                        <ul className="divide-y divide-border overflow-hidden rounded-lg shadow-field">
                            {fees.services.map((sv) => (
                                <li key={sv.group}>
                                    <Link href={sv.url} className="flex items-center justify-between gap-3 px-4 py-2.5 text-sm hover:bg-muted/60">
                                        <span className="font-medium">
                                            {sv.label} <span className="font-normal text-fg-muted">· {sv.students}</span>
                                        </span>
                                        <span className="tabular text-xs">
                                            <span className="text-success-fg">{formatMoney(sv.paid)}</span>
                                            <span className="text-fg-subtle"> / {formatMoney(sv.expected)}</span>
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </div>
                    <div className="rounded-lg bg-muted/60 p-4">
                        <div className="mb-2 flex items-center justify-between">
                            <span className="text-sm font-semibold">Cash Position</span>
                            <Link href={urls.ledger} className="text-xs text-primary hover:underline">
                                Ledger
                            </Link>
                        </div>
                        <dl className="tabular space-y-1.5 text-sm">
                            <div className="flex justify-between">
                                <dt className="text-fg-muted">Money received this year</dt>
                                <dd className="text-success-fg">{formatMoney(cashflow.income)}</dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-fg-muted">Expenses this year</dt>
                                <dd className="text-danger-fg">{formatMoney(cashflow.expenses)}</dd>
                            </div>
                            <div className="flex justify-between border-t border-border pt-1.5 font-semibold">
                                <dt>Year balance</dt>
                                <dd className={cn(cashflow.net < 0 && 'text-danger-fg')}>{formatMoney(cashflow.net)}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </section>
    );
}

function Figure({ label, value, tone }) {
    return (
        <div className="min-w-0">
            <div className="text-2xs font-semibold uppercase tracking-wider text-fg-subtle">{label}</div>
            <div className={cn('tabular truncate text-base font-semibold', tone === 'success' && 'text-success-fg', tone === 'danger' && value > 0 && 'text-danger-fg')} >
                {formatMoney(value)}
            </div>
        </div>
    );
}

function Metric({ label, value, note, tone }) {
    return (
        <div className="flex flex-col gap-0.5 rounded-md bg-surface p-3 shadow-xs">
            <span className="text-2xs font-semibold uppercase tracking-wider text-fg-subtle">{label}</span>
            <span className={cn('tabular whitespace-nowrap text-[2rem] font-semibold leading-10 tracking-tight', tone === 'primary' && 'text-primary-hover', tone === 'success' && 'text-success-fg')}>{value}</span>
            <span className="text-xs text-fg-muted">{note}</span>
        </div>
    );
}

const INTAKE_VIEWS = [
    { value: 'type', label: 'By class type' },
    { value: 'fee', label: 'By fee' },
    { value: 'source', label: 'School fees & services' },
];

/** Collected vs billed this year, in a few groups chosen from a dropdown; details on hover. */
function FeeIntake({ fees }) {
    const [view, setView] = useState('type');
    const groups = fees.groups?.[view] ?? [];
    const collected = groups.reduce((a, g) => a + g.collected, 0);
    const expected = groups.reduce((a, g) => a + g.expected, 0);
    const paidGroups = groups.filter((g) => g.collected > 0);
    const data = paidGroups.length ? paidGroups : [{ label: 'Nothing collected yet', collected: 1 }];
    const [hover, setHover] = useState(null);
    const focus = hover !== null ? paidGroups[hover] : null;

    return (
        <Card
            title={<span className="text-2xl">Fee Collection Overview</span>}
            description="Track school fee collections and outstanding balances across all class levels."
            action={
                <select
                    value={view}
                    onChange={(e) => {
                        setView(e.target.value);
                        setHover(null);
                    }}
                    aria-label="Group fee intake"
                    className="h-8 rounded-md border-0 bg-muted px-2.5 text-sm font-medium text-fg shadow-xs focus:outline-none focus:ring-2 focus:ring-primary/30"
                >
                    {INTAKE_VIEWS.map((v) => (
                        <option key={v.value} value={v.value}>
                            {v.label}
                        </option>
                    ))}
                </select>
            }
        >
            <div className="grid grid-cols-1 items-center gap-6 sm:grid-cols-[176px_1fr]">
                <div className="relative mx-auto size-44">
                    <ResponsiveContainer width="100%" height="100%">
                        <PieChart>
                            <Pie
                                isAnimationActive={false}
                                data={data}
                                dataKey="collected"
                                nameKey="label"
                                innerRadius="72%"
                                outerRadius="100%"
                                paddingAngle={paidGroups.length > 1 ? 2 : 0}
                                stroke="none"
                                startAngle={90}
                                endAngle={-270}
                                onMouseEnter={(_, i) => paidGroups.length && setHover(i)}
                                onMouseLeave={() => setHover(null)}
                            >
                                {data.map((d, i) => (
                                    <Cell key={d.label} fill={paidGroups.length ? FEE_COLORS[i % FEE_COLORS.length] : '#eaedff'} opacity={hover === null || hover === i ? 1 : 0.35} />
                                ))}
                            </Pie>
                        </PieChart>
                    </ResponsiveContainer>
                    <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center px-4 text-center">
                        <span className="max-w-full truncate text-2xs font-semibold uppercase tracking-wider text-fg-subtle">{focus ? focus.label : 'Total collected'}</span>
                        <span className="tabular text-lg font-semibold tracking-tight">{formatMoney(focus ? focus.collected : collected)}</span>
                        <span className="tabular text-[11px] text-fg-subtle">of {formatMoney(focus ? focus.expected : expected)}</span>
                    </div>
                </div>
                <ul className="flex flex-col divide-y divide-border">
                    {groups.map((g) => {
                        const i = paidGroups.indexOf(g);
                        return (
                            <li
                                key={g.label}
                                onMouseEnter={() => i >= 0 && setHover(i)}
                                onMouseLeave={() => setHover(null)}
                                className={cn('flex items-center justify-between gap-3 py-2 text-sm transition-opacity', hover !== null && hover !== i && 'opacity-50')}
                            >
                                <span className="flex min-w-0 items-center gap-2">
                                    <span className="size-2.5 shrink-0 rounded-sm" style={{ background: i >= 0 ? FEE_COLORS[i % FEE_COLORS.length] : '#eaedff' }} />
                                    <span className="truncate font-medium">{g.label}</span>
                                </span>
                                <span className="tabular shrink-0 text-fg-muted">
                                    <span className="font-medium text-fg">{formatMoney(g.collected)}</span> / {formatMoney(g.expected)}
                                </span>
                            </li>
                        );
                    })}
                    {!groups.length && <li className="py-2 text-sm text-fg-muted">No fee records this year.</li>}
                </ul>
            </div>
            <div className="flex items-center justify-between pt-1 text-xs text-fg-muted">
                <span>
                    {fees.cleared} of {fees.records} school-fee records fully paid
                </span>
                <a href={fees.links.setup} className="flex items-center gap-0.5 text-sm font-medium text-primary hover:underline">
                    Fee setup <ArrowRight className="size-4" />
                </a>
            </div>
        </Card>
    );
}

function CollectionTrend({ fees }) {
    const range = `${fees.trend[0]?.label.split(' ')[0]} — ${fees.trend[fees.trend.length - 1]?.label}`.toUpperCase();
    const hasData = fees.trend.some((t) => t.total > 0);
    return (
        <Card
            title={<span className="text-2xl">Collection Trends</span>}
            description="Monthly payment activity based on issued receipts."
            action={<span className="font-mono text-[11px] text-fg-subtle">{range}</span>}
        >
            <div className="h-40 w-full">
                {hasData ? (
                    <ResponsiveContainer width="100%" height="100%">
                        <AreaChart data={fees.trend} margin={{ top: 8, right: 8, left: 8, bottom: 0 }}>
                            <defs>
                                <linearGradient id="collectFill" x1="0" x2="0" y1="0" y2="1">
                                    <stop offset="0%" stopColor="#4f46e5" stopOpacity={0.22} />
                                    <stop offset="100%" stopColor="#4f46e5" stopOpacity={0} />
                                </linearGradient>
                            </defs>
                            <XAxis dataKey="month" tick={{ fontSize: 11, fill: '#777587', fontFamily: 'JetBrains Mono, monospace' }} tickLine={false} axisLine={false} />
                            <Tooltip formatter={(v) => [formatMoney(v), 'Collected']} labelFormatter={(_, p) => p?.[0]?.payload?.label} />
                            <Area isAnimationActive={false} type="monotone" dataKey="total" stroke="#4f46e5" strokeWidth={2.5} fill="url(#collectFill)" dot={{ r: 3.5, fill: '#4f46e5', strokeWidth: 0 }} activeDot={{ r: 5, fill: '#fff', stroke: '#4f46e5', strokeWidth: 2.5 }} />
                        </AreaChart>
                    </ResponsiveContainer>
                ) : (
                    <EmptyState compact icon={Wallet} title="No payments in the last six months" />
                )}
            </div>
            <div className="grid grid-cols-3 gap-2">
                <MiniStat label="Avg. collection per student" value={formatMoney(fees.perStudent)} />
                <MiniStat label="Outstanding fees" value={formatMoney(fees.outstanding)} tone="danger" />
                <MiniStat label="Total collected" value={formatMoney(fees.collected)} tone="success" />
            </div>
        </Card>
    );
}

function MiniStat({ label, value, tone }) {
    return (
        <div className="flex min-w-0 flex-col gap-0.5 rounded-md bg-muted/60 p-2">
            <span className="truncate text-2xs font-semibold uppercase tracking-wider text-fg-subtle">{label}</span>
            <span className={cn('tabular truncate text-lg font-semibold', tone === 'danger' && 'text-danger', tone === 'success' && 'text-success-fg')}>{value}</span>
        </div>
    );
}

function AcademicCalendar({ term, className }) {
    const [month, setMonth] = useState(() => startOfMonth(new Date()));
    const begins = parseDate(term.begins);
    const ends = parseDate(term.ends);
    const days = useMemo(
        () => eachDayOfInterval({ start: startOfWeek(startOfMonth(month), { weekStartsOn: 1 }), end: endOfWeek(endOfMonth(month), { weekStartsOn: 1 }) }),
        [month],
    );
    const today = new Date();

    return (
        <div className={cn('flex flex-col gap-4 rounded-lg bg-surface p-6 shadow-card md:p-8', className)}>
            <div className="flex items-center justify-between">
                <div className="flex flex-col">
                    <span className="text-2xs font-semibold uppercase tracking-wider text-fg-subtle">Academic calendar</span>
                    <h3 className="text-lg font-semibold">{format(month, 'MMMM yyyy')}</h3>
                </div>
                <div className="flex items-center gap-0.5">
                    <button type="button" onClick={() => setMonth((m) => addMonths(m, -1))} className="rounded-md p-1 text-fg-muted hover:bg-muted" aria-label="Previous month">
                        <ChevronLeft className="size-5" />
                    </button>
                    <button type="button" onClick={() => setMonth((m) => addMonths(m, 1))} className="rounded-md p-1 text-fg-muted hover:bg-muted" aria-label="Next month">
                        <ChevronRight className="size-5" />
                    </button>
                </div>
            </div>
            <div className="grid grid-cols-7 gap-y-2 text-center">
                {['M', 'T', 'W', 'T', 'F', 'S', 'S'].map((d, i) => (
                    <span key={i} className="text-2xs font-semibold text-fg-subtle">
                        {d}
                    </span>
                ))}
                {days.map((d) => {
                    const inMonth = isSameMonth(d, month);
                    const isToday = isSameDay(d, today);
                    const isBegin = begins && isSameDay(d, begins);
                    const isEnd = ends && isSameDay(d, ends);
                    const weekend = d.getDay() === 0 || d.getDay() === 6;
                    return (
                        <span
                            key={d.toISOString()}
                            title={isBegin ? 'Term begins' : isEnd ? 'Term ends' : undefined}
                            className={cn(
                                'mx-auto flex size-8 items-center justify-center rounded-md text-xs',
                                !inMonth && 'text-fg-subtle/50',
                                inMonth && (weekend ? 'text-fg-subtle' : 'text-fg'),
                                isToday && 'bg-primary font-semibold text-white shadow-sm',
                                isBegin && !isToday && 'bg-info-soft font-semibold text-info',
                                isEnd && !isToday && 'bg-[#6ffbbe] font-semibold text-success-fg',
                            )}
                        >
                            {format(d, 'd')}
                        </span>
                    );
                })}
            </div>
            <div className="flex items-center justify-between pt-1 text-xs text-fg-subtle">
                <span className="flex items-center gap-1">
                    <span className="size-2 rounded-full bg-primary" /> Today
                </span>
                <span className="flex items-center gap-1">
                    <span className="size-2 rounded-full bg-info" /> Term begins
                </span>
                <span className="flex items-center gap-1">
                    <span className="size-2 rounded-full bg-success" /> Term ends
                </span>
            </div>
        </div>
    );
}

function Agenda({ exams, term, session, className }) {
    const begins = parseDate(term.begins);
    const ends = parseDate(term.ends);
    const tones = ['bg-primary/10 text-primary-hover', 'bg-info/10 text-info', 'bg-success/10 text-success', 'bg-subtle text-fg-muted'];
    const badges = ['bg-primary-soft text-primary-hover', 'bg-info-soft text-info', 'bg-[#6ffbbe] text-success-fg', 'bg-subtle text-fg-muted'];

    return (
        <div className={cn('flex flex-col gap-4 rounded-lg bg-surface p-6 shadow-card md:p-8', className)}>
            <div className="flex items-center justify-between">
                <div className="flex flex-col">
                    <span className="text-2xs font-semibold uppercase tracking-wider text-fg-subtle">Year agenda</span>
                    <h3 className="text-lg font-semibold">Term Milestones &amp; Examinations</h3>
                </div>
            </div>
            <div className="flex flex-col gap-2">
                <AgendaRow tone={tones[1]} badge={badges[1]} top={begins ? format(begins, 'MMM') : 'Term'} day={begins ? format(begins, 'dd') : '—'} title="Term begins" meta={begins ? formatDate(begins, 'EEEE, d MMMM yyyy') : 'Not set in school settings'} tag="Calendar" />
                {(exams ?? []).map((e) => (
                    <AgendaRow
                        key={e.id}
                        tone={tones[(e.term - 1) % 4] ?? tones[0]}
                        badge={badges[(e.term - 1) % 4] ?? badges[0]}
                        top="Term"
                        day={String(e.term).padStart(2, '0')}
                        title={e.name}
                        meta={e.records ? `${e.records} results · ${e.classes} classes${e.average !== null ? ` · avg ${e.average}%` : ''}` : 'No marks entered yet'}
                        tag={e.records ? 'Marks in progress' : 'Scheduled'}
                    />
                ))}
                <AgendaRow tone={tones[3]} badge={badges[3]} top={ends ? format(ends, 'MMM') : 'Term'} day={ends ? format(ends, 'dd') : '—'} title="Term ends" meta={ends ? formatDate(ends, 'EEEE, d MMMM yyyy') : 'Not set in school settings'} tag="Calendar" />
            </div>
            <div className="flex items-center justify-between pt-1">
                <span className="text-xs text-fg-subtle">
                    {session} session · term dates come from school settings
                </span>
            </div>
        </div>
    );
}

function AgendaRow({ tone, badge, top, day, title, meta, tag }) {
    return (
        <div className="flex items-center justify-between gap-3 rounded-lg bg-muted/50 p-3 transition-colors hover:bg-muted">
            <div className="flex min-w-0 items-center gap-3">
                <div className={cn('flex size-12 shrink-0 flex-col items-center justify-center rounded-md font-mono', tone)}>
                    <span className="text-2xs font-semibold uppercase leading-tight">{top}</span>
                    <span className="text-lg font-bold leading-tight">{day}</span>
                </div>
                <div className="flex min-w-0 flex-col">
                    <span className="truncate text-sm font-semibold">{title}</span>
                    <span className="truncate text-xs text-fg-muted">{meta}</span>
                </div>
            </div>
            <span className={cn('shrink-0 rounded-full px-1.5 py-0.5 text-2xs font-semibold', badge)}>{tag}</span>
        </div>
    );
}

function RecentPayments({ fees }) {
    return (
        <Card eyebrow="Finance" title="Recent payments" action={<a href={fees.links.manage} className="flex items-center gap-0.5 text-sm font-medium text-primary hover:underline">View all <ArrowRight className="size-4" /></a>}>
            <ul className="-mx-2 grid gap-1 sm:grid-cols-2">
                {fees.recent.map((r) => (
                    <li key={r.id}>
                        <a href={r.url} className="flex items-center gap-3 rounded-lg px-2 py-2 hover:bg-muted">
                            <Avatar src={r.photo} name={r.student} />
                            <div className="min-w-0 flex-1">
                                <div className="truncate text-sm font-medium">{r.student}</div>
                                <div className="truncate text-xs text-fg-muted">
                                    {r.fee} · {timeAgo(r.date)}
                                </div>
                            </div>
                            <div className="text-right">
                                <div className="tabular text-sm font-semibold">{formatMoney(r.amount)}</div>
                                {r.balance > 0 ? <div className="tabular text-xs text-fg-muted">bal. {formatMoney(r.balance)}</div> : <div className="text-xs text-success-fg">Cleared</div>}
                            </div>
                        </a>
                    </li>
                ))}
            </ul>
        </Card>
    );
}

function TeachingSection({ teaching }) {
    return (
        <section className="grid gap-6 lg:grid-cols-2">
            <Card eyebrow="Teaching" title="My class sections" action={<a href={teaching.links.timetables} className="text-sm font-medium text-primary hover:underline">Timetables</a>}>
                {teaching.sections.length ? (
                    <ul className="-mx-2 flex flex-col gap-1">
                        {teaching.sections.map((s) => (
                            <li key={s.id}>
                                <Link href={s.url} className="flex items-center justify-between rounded-lg bg-muted/50 px-3 py-3 hover:bg-muted">
                                    <span className="text-sm font-semibold">{s.name}</span>
                                    <span className="tabular text-sm text-fg-muted">{s.students} students</span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                ) : (
                    <EmptyState compact icon={School} title="No sections assigned" description="An administrator assigns class teachers under Sections." />
                )}
            </Card>
            <Card
                eyebrow="Teaching"
                title="My subjects"
                action={
                    <Button size="sm" variant="primary" asChild>
                        <a href={teaching.links.marks}>
                            <NotebookPen />
                            Enter marks
                        </a>
                    </Button>
                }
            >
                {teaching.subjects.length ? (
                    <ul className="-mx-2 flex flex-col gap-1">
                        {teaching.subjects.map((s) => (
                            <li key={s.id} className="flex items-center justify-between rounded-lg bg-muted/50 px-3 py-2.5">
                                <span className="flex items-center gap-2 text-sm font-medium">
                                    <BookOpen className="size-4 text-fg-subtle" />
                                    {s.name}
                                </span>
                                <Badge tone="primary">{s.class}</Badge>
                            </li>
                        ))}
                    </ul>
                ) : (
                    <EmptyState compact icon={BookOpen} title="No subjects assigned" />
                )}
            </Card>
        </section>
    );
}

function StudentSection({ student }) {
    return (
        <Card eyebrow="My records" title={student.class}>
            <div className="flex flex-wrap items-center gap-6">
                <div>
                    <div className="text-xs text-fg-muted">Admission number</div>
                    <div className="tabular font-medium">{student.adm_no || '—'}</div>
                </div>
                <div className="ml-auto flex flex-wrap gap-2">
                    <Button asChild>
                        <Link href={student.links.profile}>
                            <UserRound />
                            Profile
                        </Link>
                    </Button>
                    <Button asChild>
                        <a href={student.links.timetables}>
                            <CalendarDays />
                            Timetable
                        </a>
                    </Button>
                    <Button variant="primary" asChild>
                        <a href={student.links.marksheet}>
                            <ScrollText />
                            Marksheet
                        </a>
                    </Button>
                </div>
            </div>
        </Card>
    );
}

function ChildrenSection({ items }) {
    return (
        <Card eyebrow="Family" title="My children">
            {items.length ? (
                <ul className="-mx-2 flex flex-col gap-1">
                    {items.map((c) => (
                        <li key={c.profile} className="flex flex-wrap items-center gap-3 rounded-lg bg-muted/50 px-3 py-3">
                            <Avatar src={c.photo} name={c.name} size="lg" />
                            <div className="min-w-0 flex-1">
                                <div className="font-semibold">{c.name}</div>
                                <div className="text-sm text-fg-muted">
                                    {c.adm_no} · {c.class}
                                </div>
                            </div>
                            <Button size="sm" asChild>
                                <NavLink href={c.profile}>Profile</NavLink>
                            </Button>
                            <Button size="sm" variant="primary" asChild>
                                <a href={c.marksheet}>Marksheet</a>
                            </Button>
                        </li>
                    ))}
                </ul>
            ) : (
                <EmptyState compact icon={Users} title="No children linked to your account" description="Contact the school office to link your children." />
            )}
        </Card>
    );
}
