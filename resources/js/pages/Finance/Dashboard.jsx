import { Fragment, useEffect, useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { Bar, BarChart, CartesianGrid, Cell, Pie, PieChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { ArrowDownLeft, ArrowRight, ArrowUpRight, ChevronRight, Plus } from 'lucide-react';
import { withAppLayout } from '@/layouts/AppLayout';
import { ModuleHeader } from '@/components/app/module';
import { EmptyState } from '@/components/app/page';
import { HorizontalBars, Legend, Meter } from '@/components/app/charts';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Modal } from '@/components/ui/dialog';
import { usePaged } from '@/components/app/data-table';
import http from '@/lib/http';
import { BalanceCards, PeriodFilter, periodQuery as toQuery } from '@/components/fees/period-picker';
import { cn, formatDate, formatMoney } from '@/lib/utils';

const SCOPES = [
    { value: 'total', label: 'All fees' },
    { value: 'school', label: 'School fees' },
    { value: 'optional', label: 'Optional fees' },
];

/**
 * Finance dashboard for the terms / years ticked in the Academic Period: bills, fees paid and owed
 * (by class type and service), money received, expenses, the balance and the modes of payment.
 */
export default function FinanceDashboard({ selection, fees, cashflow, invoiced, byMethod = [], monthly, recent, urls }) {
    const [scope, setScope] = useState('total');
    const [breakdown, setBreakdown] = useState(false);
    const f = fees[scope];
    const periodLabel = selection.label;
    const periodQuery = toQuery(selection);

    const openBreakdown = () => {
        setBreakdown(true);
        setTimeout(() => document.getElementById('fees-by-type')?.scrollIntoView({ behavior: 'smooth', block: 'start' }), 50);
    };

    return (
        <>
            <Head title="Finance dashboard" />
            <div className="flex flex-col gap-8">
                <ModuleHeader
                    crumbs={['Finance', 'Dashboard']}
                    title="Finance dashboard"
                    aside={
                        <Button variant="primary" asChild>
                            <Link href={urls.transactions}>
                                <Plus />
                                Record income / expense
                            </Link>
                        </Button>
                    }
                />

                {/* Balances for the chosen period */}
                <section className="flex flex-col gap-4">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 className="text-lg font-semibold">{periodLabel}</h2>
                            <Link href={`${urls.ledger}?${periodQuery}`} className="text-sm text-primary hover:underline">
                                See every entry in the ledger
                            </Link>
                        </div>
                        <PeriodFilter selection={selection} url={urls.self} />
                    </div>
                    <BalanceCards selection={selection} invoiced={invoiced} received={cashflow.income} expenses={cashflow.expenses} />
                </section>

                {/* Fees this year and optional services */}
                <section className="grid grid-cols-1 gap-6 xl:grid-cols-5">
                    <FeesChartCard fees={fees} f={f} scope={scope} setScope={setScope} label={periodLabel} breakdown={breakdown} onBreakdown={() => (breakdown ? setBreakdown(false) : openBreakdown())} />
                    <ServicesChartCard services={fees.services} label={periodLabel} />
                </section>

                {breakdown && <FeesByType types={fees.byType} scope={scope} onClose={() => setBreakdown(false)} />}

                {/* Trends and categories */}
                <section className="grid grid-cols-1 gap-6 xl:grid-cols-5">
                    <Card className="xl:col-span-3" title="Income and expenses" eyebrow={`${periodLabel} · by month`} action={<Legend items={[{ label: 'Fees', color: '#4f46e5' }, { label: 'Other income', color: '#0284c7' }, { label: 'Expenses', color: '#e11d48' }]} />}>
                        {monthly.some((m) => m.income || m.expenses) ? (
                            <div className="h-72">
                                <ResponsiveContainer width="100%" height="100%">
                                    <BarChart data={monthly} margin={{ top: 8, right: 8, left: 0, bottom: 0 }} barGap={2}>
                                        <CartesianGrid vertical={false} stroke="#eceaf4" />
                                        <XAxis dataKey="month" tickLine={false} axisLine={false} tick={{ fontSize: 11, fill: '#777587' }} />
                                        <YAxis tickLine={false} axisLine={false} tick={{ fontSize: 11, fill: '#777587' }} tickFormatter={(v) => formatMoney(v)} width={72} />
                                        <Tooltip formatter={(v, n) => [formatMoney(v), n]} labelFormatter={(_, p) => p?.[0]?.payload?.label} cursor={{ fill: '#f2f3ff' }} />
                                        <Bar dataKey="fees" name="Fees" stackId="in" fill="#4f46e5" isAnimationActive={false} />
                                        <Bar dataKey="other" name="Other income" stackId="in" fill="#0284c7" radius={[3, 3, 0, 0]} isAnimationActive={false} />
                                        <Bar dataKey="expenses" name="Expenses" fill="#e11d48" radius={[3, 3, 0, 0]} isAnimationActive={false} />
                                    </BarChart>
                                </ResponsiveContainer>
                            </div>
                        ) : (
                            <EmptyState compact title="No money recorded yet" description="Fee payments, income and expenses appear here month by month." />
                        )}
                    </Card>
                    <Card className="xl:col-span-2" title="Expenses by category" eyebrow={periodLabel}>
                        {cashflow.expensesByCategory.length ? (
                            <HorizontalBars data={cashflow.expensesByCategory} labelKey="category" valueKey="total" name="Spent" domain={[0, 'auto']} format={formatMoney} labelWidth={130} color="#e11d48" />
                        ) : (
                            <EmptyState compact title="No expenses in this period" action={<Button size="sm" asChild><Link href={urls.transactions}>Record an expense</Link></Button>} />
                        )}
                    </Card>
                </section>

                <Card title="Mode of payment" eyebrow={periodLabel}>
                    <PaymentMethods rows={byMethod} url={urls.paymentMode} periodQuery={periodQuery} periodLabel={periodLabel} />
                </Card>

                <section className="grid grid-cols-1 gap-6 xl:grid-cols-5">
                    <Card className="xl:col-span-2" title="Income by source" eyebrow={periodLabel}>
                        <IncomeSources cashflow={cashflow} />
                    </Card>
                    <Card
                        className="xl:col-span-3"
                        title="Recent income & expenses"
                        eyebrow="Outside student fees"
                        action={
                            <Link href={urls.transactions} className="flex items-center gap-0.5 text-sm font-medium text-primary hover:underline">
                                View all <ArrowRight className="size-4" />
                            </Link>
                        }
                    >
                        {recent.length ? (
                            <ul className="-my-2 divide-y divide-border">
                                {recent.map((t, i) => (
                                    <li key={i} className="flex items-center gap-3 py-2.5 text-sm">
                                        <span className={cn('flex size-8 shrink-0 items-center justify-center rounded-full', t.type === 'income' ? 'bg-success-soft text-success-fg' : 'bg-danger-soft text-danger-fg')}>
                                            {t.type === 'income' ? <ArrowDownLeft className="size-4" /> : <ArrowUpRight className="size-4" />}
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <div className="truncate font-medium">{t.category}</div>
                                            <div className="truncate text-xs text-fg-muted">{t.description || '—'}</div>
                                        </div>
                                        <span className="tabular text-xs text-fg-muted">{formatDate(t.date, 'dd/MM/yyyy')}</span>
                                        <span className={cn('tabular w-28 text-right font-semibold', t.type === 'income' ? 'text-success-fg' : 'text-danger-fg')}>
                                            {t.type === 'income' ? '+' : '−'}
                                            {formatMoney(t.amount)}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <EmptyState compact title="Nothing recorded yet" description="Record capital put into the school and the expenses paid from it." />
                        )}
                    </Card>
                </section>
            </div>
        </>
    );
}

FinanceDashboard.layout = withAppLayout;

function Card({ eyebrow, title, action, children, className }) {
    return (
        <div className={cn('flex flex-col gap-5 rounded-lg bg-surface p-6 shadow-card', className)}>
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    {eyebrow && <span className="text-2xs font-semibold uppercase tracking-wider text-fg-subtle">{eyebrow}</span>}
                    <h3 className="text-lg font-semibold tracking-tight">{title}</h3>
                </div>
                {action}
            </div>
            {children}
        </div>
    );
}


const C_PAID = '#4f46e5';
const C_DUE = '#fb7185';
const C_DISC = '#cbd5e1';

function MoneyTip({ active, payload, label }) {
    if (!active || !payload?.length) return null;
    return (
        <div className="min-w-[170px] rounded-lg border border-border bg-surface px-3 py-2 text-xs shadow-lg">
            <div className="mb-1 font-semibold text-fg">{label ?? payload[0]?.payload?.label}</div>
            {payload.map((p) => (
                <div key={p.dataKey ?? p.name} className="flex items-center justify-between gap-4 py-0.5">
                    <span className="flex items-center gap-1.5 text-fg-muted">
                        <span className="size-2 rounded-[2px]" style={{ background: p.color || p.payload?.fill }} />
                        {p.name}
                    </span>
                    <span className="tabular font-medium text-fg">{formatMoney(p.value)}</span>
                </div>
            ))}
        </div>
    );
}

/** Fees: donut (paid / owed / discounts) and paid vs owed per class type. */
function FeesChartCard({ fees, f, scope, setScope, label, breakdown, onBreakdown }) {
    const discount = scope === 'optional' ? 0 : fees.school.discount;
    const slices = [
        { name: 'Paid', value: f.paid, fill: C_PAID },
        { name: 'Still owed', value: f.due, fill: C_DUE },
        ...(discount ? [{ name: 'Discounts', value: discount, fill: C_DISC }] : []),
    ].filter((s) => s.value > 0);
    const types = fees.byType.map((t) => ({ label: t.type, paid: t[scope].paid, due: t[scope].due })).filter((t) => t.paid + t.due > 0);

    return (
        <div className="flex flex-col gap-5 rounded-lg bg-surface p-6 shadow-card xl:col-span-3">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <span className="text-2xs font-semibold uppercase tracking-wider text-fg-subtle">{label}</span>
                    <h3 className="text-lg font-semibold tracking-tight">Fees</h3>
                </div>
                <div className="flex items-center gap-2">
                    <select
                        value={scope}
                        onChange={(e) => setScope(e.target.value)}
                        aria-label="Which fees"
                        className="h-8 rounded-md border-0 bg-muted px-2.5 text-sm font-medium shadow-xs focus:outline-none focus:ring-2 focus:ring-primary/30"
                    >
                        {SCOPES.map((s) => (
                            <option key={s.value} value={s.value}>
                                {s.label}
                            </option>
                        ))}
                    </select>
                    <Button size="sm" onClick={onBreakdown}>
                        {breakdown ? 'Hide table' : 'Class type table'}
                    </Button>
                </div>
            </div>

            <ul className="flex flex-wrap items-center gap-x-8 gap-y-2 border-y border-border py-3 text-sm">
                <LegendRow color={C_PAID} label="Paid" value={f.paid} />
                <LegendRow color={C_DUE} label="Still owed" value={f.due} tone="danger" />
                {scope !== 'optional' && <LegendRow color={C_DISC} label="Discounts given" value={discount} />}
            </ul>

            <div className="grid grid-cols-1 items-center gap-6 lg:grid-cols-[220px_1fr]">
                <div className="flex flex-col items-center gap-4">
                    <div className="relative size-48">
                        <ResponsiveContainer width="100%" height="100%">
                            <PieChart>
                                <Pie isAnimationActive={false} data={slices.length ? slices : [{ name: 'None', value: 1, fill: '#eaedff' }]} dataKey="value" nameKey="name" innerRadius="70%" outerRadius="100%" paddingAngle={slices.length > 1 ? 2 : 0} stroke="none" startAngle={90} endAngle={-270}>
                                    {(slices.length ? slices : [{ fill: '#eaedff' }]).map((s, i) => (
                                        <Cell key={i} fill={s.fill} />
                                    ))}
                                </Pie>
                                {slices.length > 0 && <Tooltip content={<MoneyTip />} />}
                            </PieChart>
                        </ResponsiveContainer>
                        <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center text-center">
                            <span className="text-2xs font-semibold uppercase tracking-wider text-fg-subtle">Collected</span>
                            <span className="tabular text-xl font-semibold tracking-tight">{formatMoney(f.paid)}</span>
                            <span className="tabular text-[11px] text-fg-subtle">of {formatMoney(f.expected)} billed</span>
                        </div>
                    </div>
                </div>

                <div className="min-w-0">
                    <div className="mb-2 flex items-center justify-between">
                        <span className="text-sm font-medium">By class type</span>
                        <Legend items={[{ label: 'Paid', color: C_PAID }, { label: 'Owed', color: C_DUE }]} />
                    </div>
                    {types.length ? (
                        <div style={{ height: Math.max(180, types.length * 44 + 20) }}>
                            <ResponsiveContainer width="100%" height="100%">
                                <BarChart data={types} layout="vertical" margin={{ top: 0, right: 12, bottom: 0, left: 0 }} barCategoryGap="28%" onClick={onBreakdown}>
                                    <CartesianGrid horizontal={false} stroke="#eceaf4" />
                                    <XAxis type="number" tick={{ fontSize: 11, fill: '#777587' }} tickLine={false} axisLine={false} tickFormatter={(v) => formatMoney(v)} />
                                    <YAxis type="category" dataKey="label" tick={{ fontSize: 12, fill: '#464555' }} tickLine={false} axisLine={false} width={96} />
                                    <Tooltip content={<MoneyTip />} cursor={{ fill: '#f2f3ff' }} />
                                    <Bar dataKey="paid" name="Paid" stackId="f" fill={C_PAID} isAnimationActive={false} className="cursor-pointer" />
                                    <Bar dataKey="due" name="Owed" stackId="f" fill={C_DUE} radius={[0, 4, 4, 0]} isAnimationActive={false} className="cursor-pointer" />
                                </BarChart>
                            </ResponsiveContainer>
                        </div>
                    ) : (
                        <EmptyState compact title="No fees billed yet" />
                    )}
                </div>
            </div>
        </div>
    );
}

function LegendRow({ color, label, value, tone }) {
    return (
        <li className="flex items-center gap-2.5">
            <span className="flex items-center gap-2 text-fg-muted">
                <span className="size-2.5 rounded-sm" style={{ background: color }} />
                {label}
            </span>
            <span className={cn('tabular font-semibold', tone === 'danger' && value > 0 ? 'text-danger-fg' : 'text-fg')}>{formatMoney(value)}</span>
        </li>
    );
}

/** Optional services: paid vs due per service, with the figures underneath. */
function ServicesChartCard({ services, label }) {
    const data = services.map((s) => ({ label: s.label, paid: s.paid, due: s.due }));
    return (
        <div className="flex flex-col gap-4 rounded-lg bg-surface p-6 shadow-card xl:col-span-2">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <span className="text-2xs font-semibold uppercase tracking-wider text-fg-subtle">{label}</span>
                    <h3 className="text-lg font-semibold tracking-tight">Optional services</h3>
                </div>
                <Legend items={[{ label: 'Paid', color: C_PAID }, { label: 'Due', color: C_DUE }]} />
            </div>
            <div className="h-44">
                <ResponsiveContainer width="100%" height="100%">
                    <BarChart data={data} margin={{ top: 4, right: 4, bottom: 0, left: 0 }} barCategoryGap="30%">
                        <CartesianGrid vertical={false} stroke="#eceaf4" />
                        <XAxis dataKey="label" tick={{ fontSize: 11, fill: '#464555' }} tickLine={false} axisLine={false} interval={0} tickFormatter={(v) => v.replace(' & stationery', '')} />
                        <YAxis tick={{ fontSize: 11, fill: '#777587' }} tickLine={false} axisLine={false} width={64} tickFormatter={(v) => formatMoney(v)} />
                        <Tooltip content={<MoneyTip />} cursor={{ fill: '#f2f3ff' }} />
                        <Bar dataKey="paid" name="Paid" stackId="s" fill={C_PAID} isAnimationActive={false} />
                        <Bar dataKey="due" name="Due" stackId="s" fill={C_DUE} radius={[4, 4, 0, 0]} isAnimationActive={false} />
                    </BarChart>
                </ResponsiveContainer>
            </div>
            <ul className="-mx-2 divide-y divide-border">
                {services.map((s) => {
                    return (
                        <li key={s.group}>
                            <Link href={s.url} className="flex flex-col gap-0.5 rounded-md px-2 py-2 text-sm hover:bg-muted/60">
                                <span className="flex items-center justify-between gap-3">
                                    <span className="font-medium">{s.label}</span>
                                    <span className={cn('tabular text-xs font-medium', s.due > 0 ? 'text-danger-fg' : 'text-fg-subtle')}>Due {formatMoney(s.due)}</span>
                                </span>
                                <span className="tabular flex items-center justify-between gap-3 text-xs text-fg-muted">
                                    <span>{s.students} students</span>
                                    <span>
                                        Paid <span className="font-medium text-fg">{formatMoney(s.paid)}</span> of {formatMoney(s.expected)}
                                    </span>
                                </span>
                            </Link>
                        </li>
                    );
                })}
            </ul>
        </div>
    );
}

/** Fees grouped by class type, each expandable into its classes. */
function FeesByType({ types, scope, onClose }) {
    const [open, setOpen] = useState(() => new Set());
    const toggle = (t) => setOpen((s) => {
        const n = new Set(s);
        n.has(t) ? n.delete(t) : n.add(t);
        return n;
    });
    const label = SCOPES.find((s) => s.value === scope)?.label;

    return (
        <div id="fees-by-type" className="scroll-mt-20 overflow-hidden rounded-lg bg-surface shadow-card">
            <div className="flex items-center justify-between border-b border-border px-5 py-3">
                <div>
                    <h3 className="font-semibold">{label} by class type</h3>
                    <p className="text-xs text-fg-muted">Click a class type to see its classes.</p>
                </div>
                <Button size="sm" variant="ghost" onClick={onClose}>
                    Hide
                </Button>
            </div>
            <div className="scrollbar-thin overflow-x-auto">
                <table className="w-full text-left">
                    <thead>
                        <tr className="border-b border-border bg-canvas text-2xs font-semibold uppercase text-fg-muted">
                            <th className="h-9 px-5">Class type</th>
                            <th className="h-9 px-3 text-right">Students</th>
                            <th className="h-9 px-3 text-right">Billed</th>
                            <th className="h-9 px-3 text-right">Paid</th>
                            <th className="h-9 px-3 text-right">Due</th>
                            <th className="hidden h-9 w-48 px-5 md:table-cell">Collected</th>
                        </tr>
                    </thead>
                    <tbody className="tabular">
                        {types.map((t) => {
                            const v = t[scope];
                            const expanded = open.has(t.type);
                            return (
                                <Fragment key={t.type}>
                                    <tr className="cursor-pointer border-b border-border hover:bg-muted/60" onClick={() => toggle(t.type)}>
                                        <td className="px-5 py-3 font-semibold">
                                            <span className="flex items-center gap-1.5">
                                                <ChevronRight className={cn('size-4 text-fg-subtle transition-transform', expanded && 'rotate-90')} />
                                                {t.type}
                                                <span className="font-normal text-fg-muted">· {t.classes.length} classes</span>
                                            </span>
                                        </td>
                                        <td className="px-3 text-right">{t.students}</td>
                                        <td className="px-3 text-right font-medium">{formatMoney(v.expected)}</td>
                                        <td className="px-3 text-right text-success-fg">{formatMoney(v.paid)}</td>
                                        <td className={cn('px-3 text-right font-semibold', v.due > 0 ? 'text-danger-fg' : 'text-fg-muted')}>{formatMoney(v.due)}</td>
                                        <td className="hidden px-5 md:table-cell">
                                            <div className="flex items-center gap-2">
                                                <div className="flex-1">
                                                    <Meter value={v.paid} max={v.expected} />
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    {expanded &&
                                        t.classes.map((c) => {
                                            const cv = c[scope];
                                            return (
                                                <tr key={c.id} className="border-b border-border bg-muted/30 text-sm">
                                                    <td className="py-2 pl-12 pr-5">
                                                        <Link href={c.url} className="hover:text-primary hover:underline">
                                                            {c.class}
                                                        </Link>
                                                        {c.discount > 0 && scope !== 'optional' && <Badge className="ml-2" tone="success" shape="tag">{formatMoney(c.discount)} discount</Badge>}
                                                    </td>
                                                    <td className="px-3 text-right text-fg-muted">{c.students}</td>
                                                    <td className="px-3 text-right">{formatMoney(cv.expected)}</td>
                                                    <td className="px-3 text-right text-success-fg">{formatMoney(cv.paid)}</td>
                                                    <td className={cn('px-3 text-right', cv.due > 0 ? 'text-danger-fg' : 'text-fg-muted')}>{formatMoney(cv.due)}</td>
                                                    <td className="hidden px-5 md:table-cell">
                                                        <Meter value={cv.paid} max={cv.expected} />
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                </Fragment>
                            );
                        })}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

function IncomeSources({ cashflow }) {
    const rows = [
        { category: 'School fees', total: cashflow.schoolFees },
        { category: 'Optional fees', total: cashflow.optionalFees },
        ...cashflow.incomeByCategory,
    ].filter((r) => r.total > 0);
    return rows.length ? (
        <HorizontalBars data={rows} labelKey="category" valueKey="total" name="Received" domain={[0, 'auto']} format={formatMoney} labelWidth={130} />
    ) : (
        <EmptyState compact title="No income in this period" />
    );
}

/** Fee payments in the chosen terms by mode (cash, mobile payment, bank transfer, cheque); click one to see who paid that way. */
function PaymentMethods({ rows, url, periodQuery, periodLabel }) {
    const [open, setOpen] = useState(null);
    const total = rows.reduce((a, r) => a + r.total, 0);
    if (!total) return <EmptyState compact title="No fee payments in this period" />;
    return (
        <>
            <div className="-mx-6 -mb-6 overflow-x-auto">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-y border-border bg-canvas text-left text-2xs font-semibold uppercase text-fg-muted">
                            <th className="h-9 px-6">Mode</th>
                            <th className="h-9 px-3 text-right">Payments</th>
                            <th className="h-9 px-6 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody className="tabular">
                        {rows.map((r) => (
                            <tr key={r.method} onClick={() => setOpen(r.method)} className="cursor-pointer border-b border-border hover:bg-muted/50">
                                <td className="px-6 py-2.5 font-medium text-primary">{r.method}</td>
                                <td className="px-3 py-2.5 text-right text-fg-muted">{r.count}</td>
                                <td className="px-6 py-2.5 text-right font-semibold">{formatMoney(r.total)}</td>
                            </tr>
                        ))}
                        <tr className="bg-canvas font-semibold">
                            <td className="px-6 py-2.5">Fees received</td>
                            <td className="px-3 py-2.5 text-right">{rows.reduce((a, r) => a + r.count, 0)}</td>
                            <td className="px-6 py-2.5 text-right">{formatMoney(total)}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <ModePayments method={open} onClose={() => setOpen(null)} url={url} periodQuery={periodQuery} periodLabel={periodLabel} />
        </>
    );
}

/** Everyone who paid by one mode in the period. */
function ModePayments({ method, onClose, url, periodQuery, periodLabel }) {
    const [data, setData] = useState(null);
    const [q, setQ] = useState('');

    useEffect(() => {
        if (!method) return;
        let off = false;
        setData(null);
        setQ('');
        http.get(`${url}?${periodQuery}`, { params: { method } })
            .then(({ data }) => !off && setData(data))
            .catch(() => !off && setData({ rows: [], total: 0, count: 0, students: 0 }));
        return () => {
            off = true;
        };
    }, [method, url, periodQuery]);

    const s = q.trim().toLowerCase();
    const rows = (data?.rows ?? []).filter((r) => !s || `${r.student} ${r.class ?? ''} ${r.for} ${r.reference ?? ''}`.toLowerCase().includes(s));
    const { shown, pager } = usePaged(rows, 10, 'payments');

    return (
        <Modal open={!!method} onOpenChange={(o) => !o && onClose()} title={method ?? ''} description={`Fee payments · ${periodLabel}`} className="top-[6vh] max-w-3xl">
            {!data ? (
                <p className="py-8 text-center text-sm text-fg-muted">Loading…</p>
            ) : (
                <div className="flex flex-col gap-4">
                    <div className="tabular grid grid-cols-3 gap-3 text-sm">
                        <div className="rounded-lg bg-canvas px-3 py-2">
                            <div className="text-xs text-fg-muted">Amount</div>
                            <div className="font-semibold">{formatMoney(data.total)}</div>
                        </div>
                        <div className="rounded-lg bg-canvas px-3 py-2">
                            <div className="text-xs text-fg-muted">Payments</div>
                            <div className="font-semibold">{data.count}</div>
                        </div>
                        <div className="rounded-lg bg-canvas px-3 py-2">
                            <div className="text-xs text-fg-muted">Students</div>
                            <div className="font-semibold">{data.students}</div>
                        </div>
                    </div>
                    <input
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        placeholder="Search student, class or reference"
                        className="h-9 rounded-md border border-border bg-surface px-3 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30"
                    />
                    <div className="overflow-hidden rounded-lg border border-border">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-border bg-canvas text-left text-2xs font-semibold uppercase text-fg-muted">
                                    <th className="h-9 px-3">Date</th>
                                    <th className="h-9 px-3">Student</th>
                                    <th className="h-9 px-3">For</th>
                                    <th className="h-9 px-3 text-right">Amount</th>
                                </tr>
                            </thead>
                            <tbody className="tabular">
                                {shown.map((r) => (
                                    <tr key={r.key} className="border-b border-border last:border-0">
                                        <td className="whitespace-nowrap px-3 py-2 text-fg-muted">{formatDate(r.at, 'dd/MM/yyyy')}</td>
                                        <td className="px-3 py-2">
                                            <Link href={r.url} className="font-medium hover:text-primary hover:underline">
                                                {r.student}
                                            </Link>
                                            <div className="text-xs text-fg-muted">{[r.class, r.reference].filter(Boolean).join(' · ')}</div>
                                        </td>
                                        <td className="px-3 py-2 text-fg-muted">{r.for}</td>
                                        <td className="px-3 py-2 text-right font-semibold">{formatMoney(r.amount)}</td>
                                    </tr>
                                ))}
                                {!rows.length && (
                                    <tr>
                                        <td colSpan={4} className="px-3 py-6 text-center text-fg-muted">
                                            No payments found.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    {pager}
                </div>
            )}
        </Modal>
    );
}
