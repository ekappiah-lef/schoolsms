import { Fragment, useMemo, useState } from "react";
import { Head, Link, router } from "@inertiajs/react";
import { Columns3, Download, Layers } from "lucide-react";
import { withAppLayout } from "@/layouts/AppLayout";
import { ModuleHeader } from "@/components/app/module";
import { EmptyState } from "@/components/app/page";
import { SearchInput, usePaged } from "@/components/app/data-table";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Select } from "@/components/ui/select";
import { Segmented } from "@/components/ui/tabs";
import { PeriodTree, summary as periodSummary } from "@/components/fees/period-tree";
import { DropdownMenu, DropdownMenuCheckboxItem, DropdownMenuContent, DropdownMenuLabel, DropdownMenuTrigger } from "@/components/ui/dropdown-menu";
import { downloadCsv } from "@/lib/use-visit-state";
import { cn, formatMoney } from "@/lib/utils";

const SUM_KEYS = ['invoiced', 'school', 'services', 'sales', 'discount', 'paid', 'outstanding', 'paidBefore', 'paidDuring', 'paidAfter', 'fees', 'feesThis', 'feesEarlier', 'feesLater', 'other', 'expenses', 'net'];
const sumRows = (rs) => Object.fromEntries(SUM_KEYS.map((k) => [k, rs.reduce((a, r) => a + (r[k] ?? 0), 0)]));

/**
 * Fee breakdown, for management and audit. One Academic Period filter drives the whole page:
 *  1. Term by term: bills, money received, expenses and cash for every chosen term.
 *  2. How it adds up: the bills and the cash for the selection, line by line.
 *  3. Fee items: what each item (Tuition, P.T.A, Feeding, Bus …) comes to, and who has paid it.
 */
export default function FeeBreakdown({ picked, item, items, termly, rows, urls }) {
    const go = (changes, scrollTo) =>
        router.get(
            urls.self,
            { periods: picked.join(","), item, ...changes },
            {
                preserveScroll: true,
                onSuccess: () => scrollTo && document.getElementById(scrollTo)?.scrollIntoView({ behavior: "smooth", block: "start" }),
            },
        );

    const tree = useMemo(() => {
        const g = [];
        termly.forEach((r) => {
            let y = g.find((x) => x.session === r.session);
            if (!y) g.push((y = { session: r.session, terms: [] }));
            y.terms.push(r.term);
        });
        return g.map((y) => ({ ...y, terms: y.terms.sort() }));
    }, [termly]);
    const chosen = termly.filter((r) => picked.includes(r.key));
    const label = periodSummary(tree, picked);

    return (
        <>
            <Head title="Fee breakdown" />
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-8">
                <ModuleHeader
                    crumbs={["Finance", "Fee breakdown"]}
                    title="Fee breakdown"
                    description="What was billed, received and spent each term, and every fee item by student and parent."
                    aside={<PeriodTree years={tree} value={picked} onChange={(v) => go({ periods: v.join(",") })} label={null} className="w-72" />}
                />

                <TermTable rows={chosen} ledgerUrl={urls.ledger} onPick={(r) => go({ periods: r.key }, "fee-items")} />

                {chosen.length > 0 && <Statement rows={chosen} all={termly} label={label} ledgerUrl={urls.ledger} />}

                <section id="fee-items" className="flex scroll-mt-6 flex-col gap-4">
                    <div className="flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <h2 className="text-lg font-semibold">Fee items</h2>
                            <p className="text-sm text-fg-muted">{label}</p>
                        </div>
                        {items.length > 0 && (
                            <p className="tabular text-sm text-fg-muted">
                                Billed <span className="font-semibold text-fg">{formatMoney(items.reduce((a, i) => a + i.billed, 0))}</span>
                                {" · "}Paid <span className="font-semibold text-success-fg">{formatMoney(items.reduce((a, i) => a + i.paid, 0))}</span>
                                {" · "}Owed <span className="font-semibold text-danger-fg">{formatMoney(items.reduce((a, i) => a + i.due, 0))}</span>
                            </p>
                        )}
                    </div>

                    {items.length ? (
                        <div className="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5">
                            {items.map((i) => {
                                const on = i.name === item;
                                return (
                                    <button
                                        key={i.name}
                                        type="button"
                                        onClick={() => go({ item: i.name })}
                                        aria-pressed={on}
                                        className={cn(
                                            "flex flex-col gap-1 rounded-lg p-4 text-left shadow-card transition-shadow hover:shadow-card-hover",
                                            on ? "bg-primary-soft/50 shadow-[0_0_0_1.5px_rgb(var(--primary))]" : "bg-surface",
                                        )}
                                    >
                                        <span className="font-medium">{i.name}</span>
                                        <dl className="tabular mt-1 flex flex-col gap-1.5 text-sm">
                                            <div className="flex items-baseline justify-between gap-3">
                                                <dt className="text-fg-muted">Total</dt>
                                                <dd className="text-xl font-semibold tracking-tight">{formatMoney(i.billed)}</dd>
                                            </div>
                                            <div className="flex justify-between gap-3">
                                                <dt className="text-fg-muted">Paid</dt>
                                                <dd className="font-medium text-success-fg">{formatMoney(i.paid)}</dd>
                                            </div>
                                            <div className="flex justify-between gap-3">
                                                <dt className="text-fg-muted">Owed</dt>
                                                <dd className={cn("font-medium", i.due > 0 ? "text-danger-fg" : "text-fg-subtle")}>{formatMoney(i.due)}</dd>
                                            </div>
                                        </dl>
                                    </button>
                                );
                            })}
                        </div>
                    ) : null}

                    <ItemTable key={`${picked.join(",")}-${item}`} item={item} rows={rows} label={label} />
                </section>
            </div>
        </>
    );
}

FeeBreakdown.layout = withAppLayout;

const TERM_COLUMNS = [
    { key: 'invoiced', label: 'Invoiced', help: 'Bills for the term (after discounts)' },
    { key: 'paid', label: 'Paid so far', help: 'Paid against those bills, whenever it was paid', tone: 'success' },
    { key: 'outstanding', label: 'Still owed', help: 'Not yet paid', tone: 'owed' },
    { key: 'opening', label: 'Cash at start', help: 'School cash on the first day of the term', muted: true, cash: true },
    { key: 'fees', label: 'Fees received', help: 'Fee payments made during the term, for any bill', tone: 'success', cash: true },
    { key: 'other', label: 'Other income', help: 'Capital, grants, donations', tone: 'success', cash: true },
    { key: 'expenses', label: 'Expenses', help: 'Spent during the term', tone: 'danger', cash: true },
    { key: 'net', label: 'Term balance', help: 'Fees received + other income − expenses', signed: true, strong: true, cash: true },
    { key: 'closing', label: 'Cash at end', help: 'Cash at start + term balance', muted: true, cash: true },
];
const DEFAULT_COLUMNS = TERM_COLUMNS.map((c) => c.key);
const COLUMNS_KEY = 'fee-breakdown.term-columns.v2';

/** The chosen terms grouped by school year; choose which figures to show. */
function TermTable({ rows, ledgerUrl, onPick }) {
    const [cols, setCols] = useState(() => {
        try {
            const saved = JSON.parse(window.localStorage.getItem(COLUMNS_KEY));
            if (Array.isArray(saved) && saved.length) return saved;
        } catch {
            /* ignore */
        }
        return DEFAULT_COLUMNS;
    });
    const toggleCol = (k) =>
        setCols((c) => {
            const next = c.includes(k) ? c.filter((x) => x !== k) : TERM_COLUMNS.map((x) => x.key).filter((x) => x === k || c.includes(x));
            const final = next.length ? next : c;
            try {
                window.localStorage.setItem(COLUMNS_KEY, JSON.stringify(final));
            } catch {
                /* ignore */
            }
            return final;
        });
    const shownCols = TERM_COLUMNS.filter((c) => cols.includes(c.key));

    // rows arrive newest first; a year's (or the selection's) cash at start is its oldest term's, cash at end its newest term's.
    const groups = [];
    rows.forEach((r) => {
        let g = groups.find((x) => x.session === r.session);
        if (!g) groups.push((g = { session: r.session, terms: [] }));
        g.terms.push(r);
    });
    const total = (rs) => ({ ...sumRows(rs), opening: rs[rs.length - 1]?.opening ?? 0, closing: rs[0]?.closing ?? 0, upcoming: rs.every((r) => r.upcoming) });
    const visible = groups.map((g) => ({ ...g, ...total(g.terms) }));
    const grand = visible.length > 1 ? total(rows) : null;

    const cell = (c, r, strongRow) => {
        const v = r[c.key];
        if (c.cash && r.upcoming && c.key !== 'opening' && c.key !== 'closing') {
            return (
                <td key={c.key} className="px-4 py-2.5 text-right text-fg-subtle">
                    —
                </td>
            );
        }
        const tone =
            c.tone === 'success' ? 'text-success-fg' : c.tone === 'danger' ? 'text-danger-fg' : c.tone === 'owed' ? (v > 0 ? 'text-danger-fg' : 'text-fg-subtle') : c.muted ? 'text-fg-muted' : c.signed && v < 0 ? 'text-danger-fg' : '';
        return (
            <td key={c.key} className={cn('tabular px-4 py-2.5 text-right', tone, (c.strong || strongRow) && 'font-semibold')}>
                {c.signed ? signed(v) : formatMoney(v)}
            </td>
        );
    };

    return (
        <section className="overflow-hidden rounded-lg bg-surface shadow-card">
            <div className="flex flex-wrap items-center justify-between gap-3 px-6 pb-4 pt-5">
                <div>
                    <h2 className="text-lg font-semibold">Term by term</h2>
                    <p className="text-sm text-fg-muted">Click a term to look at it on its own.</p>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button size="sm">
                                <Columns3 />
                                Columns ({shownCols.length})
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-72">
                            <DropdownMenuLabel>Show these figures</DropdownMenuLabel>
                            {TERM_COLUMNS.map((c) => (
                                <DropdownMenuCheckboxItem key={c.key} checked={cols.includes(c.key)} onCheckedChange={() => toggleCol(c.key)} onSelect={(e) => e.preventDefault()}>
                                    <div className="flex flex-col">
                                        <span>{c.label}</span>
                                        <span className="text-xs text-fg-muted">{c.help}</span>
                                    </div>
                                </DropdownMenuCheckboxItem>
                            ))}
                        </DropdownMenuContent>
                    </DropdownMenu>
                    <Link href={ledgerUrl} className="px-1 text-sm font-medium text-primary hover:underline">
                        Ledger
                    </Link>
                </div>
            </div>
            <div className="scrollbar-thin overflow-x-auto">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-y border-border bg-canvas text-left text-2xs font-semibold uppercase text-fg-muted">
                            <th className="h-10 px-6">Term</th>
                            {shownCols.map((c) => (
                                <th key={c.key} className="h-10 whitespace-nowrap px-4 text-right" title={c.help}>
                                    {c.label}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {visible.map((y) => (
                            <Fragment key={y.session}>
                                {visible.length > 1 && (
                                    <tr className="border-b border-border bg-muted/40">
                                        <td className="px-6 py-2.5 font-semibold">{y.session.replace('-', ' – ')}</td>
                                        {shownCols.map((c) => cell(c, y, true))}
                                    </tr>
                                )}
                                {y.terms.map((r) => (
                                    <tr key={r.key} onClick={() => onPick(r)} className="cursor-pointer border-b border-border last:border-0 hover:bg-muted/50">
                                        <td className={cn('whitespace-nowrap py-2.5 pr-6', visible.length > 1 ? 'pl-10' : 'pl-6')}>
                                            Term {r.term}
                                            {visible.length === 1 && <span className="text-fg-muted"> · {r.session.replace('-', ' – ')}</span>}
                                            {r.current && <Badge className="ml-2">Now</Badge>}
                                            {r.upcoming && <Badge className="ml-2">Upcoming</Badge>}
                                        </td>
                                        {shownCols.map((c) => cell(c, r))}
                                    </tr>
                                ))}
                                {visible.length === 1 && y.terms.length > 1 && (
                                    <tr className="border-t border-border bg-canvas font-semibold">
                                        <td className="px-6 py-2.5">Total</td>
                                        {shownCols.map((c) => cell(c, y, true))}
                                    </tr>
                                )}
                            </Fragment>
                        ))}
                        {grand && (
                            <tr className="border-t-2 border-border bg-canvas font-semibold">
                                <td className="px-6 py-2.5">Total for the selection</td>
                                {shownCols.map((c) => cell(c, grand, true))}
                            </tr>
                        )}
                        {!visible.length && (
                            <tr>
                                <td colSpan={shownCols.length + 1} className="px-6 py-8 text-center text-sm text-fg-muted">
                                    Choose one or more years or terms in the Academic Period at the top.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </section>
    );
}

/**
 * The selection line by line, as an auditor would check it:
 * the bills (invoiced = paid so far + still owed) and the cash (start + received − spent = end),
 * and how the fees received relate to the bills.
 */
function Statement({ rows, all, label, ledgerUrl }) {
    const t = sumRows(rows);
    const many = rows.length > 1;
    // Cash at start / end only make sense for terms that follow one another.
    const order = [...all].reverse().map((r) => r.key);
    const idx = rows.map((r) => order.indexOf(r.key)).sort((a, b) => a - b);
    const continuous = idx.every((v, i) => i === 0 || v === idx[i - 1] + 1);
    const first = rows[rows.length - 1];
    const last = rows[0];
    const anyUpcoming = rows.some((r) => r.upcoming);
    const during = many ? 'during their terms' : 'during the term';
    const ledger = `${ledgerUrl}?${new URLSearchParams({ from: first.from, to: last.upcoming ? new Date().toISOString().slice(0, 10) : last.to })}`;

    return (
        <section className="flex flex-col gap-4">
            <div className="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 className="text-lg font-semibold">How it adds up</h2>
                    <p className="text-sm text-fg-muted">{label}</p>
                </div>
                {continuous && (
                    <Link href={ledger} className="text-sm font-medium text-primary hover:underline">
                        Every payment and expense in the ledger
                    </Link>
                )}
            </div>
            <div className="grid gap-6 lg:grid-cols-2">
                <Lines
                    title="Bills"
                    note="What parents were billed and how much of it has been paid."
                    lines={[
                        ['School fees', t.school, t.discount ? `after ${formatMoney(t.discount)} discounts` : null],
                        ['Optional services', t.services, 'feeding, bus, clubs: a third of the year each term'],
                        ['Shop sales', t.sales],
                        ['Invoiced', t.invoiced, null, 'total'],
                        ['Paid before the term started', t.paidBefore, 'paid in advance'],
                        [`Paid ${during}`, t.paidDuring],
                        ['Paid after the term ended', t.paidAfter, 'paid late'],
                        ['Paid so far', t.paid, null, 'total'],
                        ['Still owed', t.outstanding, 'invoiced − paid so far', 'owed'],
                    ]}
                />
                <Lines
                    title="Cash"
                    note={anyUpcoming ? 'Upcoming terms have no money in or out yet.' : 'Money that came in and went out in the period.'}
                    lines={[
                        ...(continuous ? [['Cash at start', first.opening, `on ${fmtDate(first.from)}`, 'muted']] : []),
                        [`Fees for these bills`, t.feesThis, `same money as "paid ${during}"`],
                        ['Fees for earlier bills', t.feesEarlier, 'arrears from earlier terms'],
                        ['Fees for later bills', t.feesLater, 'paid in advance for coming terms'],
                        ['Fees received', t.fees, null, 'total'],
                        ['Other income', t.other],
                        ['Expenses', -t.expenses],
                        ['Term balance', t.net, 'fees received + other income − expenses', 'total'],
                        ...(continuous ? [['Cash at end', last.closing, `cash at start + term balance${last.upcoming ? '' : last.current ? ', today' : `, on ${fmtDate(last.to)}`}`, 'total']] : []),
                    ]}
                    footer={continuous ? null : 'Cash at start and end are shown when the chosen terms follow one another.'}
                />
            </div>
        </section>
    );
}

const fmtDate = (d) => d.split('-').reverse().join('/');

function Lines({ title, note, lines, footer }) {
    return (
        <div className="overflow-hidden rounded-lg bg-surface shadow-card">
            <div className="px-6 pb-3 pt-5">
                <h3 className="font-semibold">{title}</h3>
                <p className="text-sm text-fg-muted">{note}</p>
            </div>
            <table className="w-full text-sm">
                <tbody>
                    {lines.map(([name, v, hint, kind]) => (
                        <tr key={name} className={cn('border-t border-border', kind === 'total' && 'bg-canvas font-semibold')}>
                            <td className="px-6 py-2.5">
                                <div>{name}</div>
                                {hint && <div className="text-xs font-normal text-fg-muted">{hint}</div>}
                            </td>
                            <td className={cn('tabular px-6 py-2.5 text-right', kind === 'owed' && (v > 0 ? 'font-semibold text-danger-fg' : 'text-fg-subtle'), kind === 'muted' && 'text-fg-muted', v < 0 && 'text-danger-fg')}>
                                {signed(v)}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
            {footer && <p className="border-t border-border px-6 py-3 text-xs text-fg-muted">{footer}</p>}
        </div>
    );
}

const signed = (v) => (v < 0 ? `−${formatMoney(Math.abs(v))}` : formatMoney(v));

/** Who has paid the chosen fee item. */
function ItemTable({ item, rows, label }) {
    const [q, setQ] = useState("");
    const [cls, setCls] = useState("");
    const [status, setStatus] = useState("all");

    const classes = useMemo(
        () =>
            [...new Set(rows.map((r) => r.class).filter(Boolean))]
                .sort()
                .map((c) => ({ value: c, label: c })),
        [rows],
    );
    const filtered = useMemo(() => {
        const s = q.trim().toLowerCase();
        return rows.filter((r) => {
            if (
                s &&
                !`${r.student} ${r.adm_no ?? ""} ${r.parent ?? ""} ${r.parent_phone ?? ""}`
                    .toLowerCase()
                    .includes(s)
            )
                return false;
            if (cls && r.class !== cls) return false;
            if (status === "paid" && r.balance > 0) return false;
            if (status === "part" && !(r.paid > 0 && r.balance > 0))
                return false;
            if (status === "unpaid" && r.paid > 0) return false;
            return true;
        });
    }, [rows, q, cls, status]);
    const totals = filtered.reduce(
        (a, r) => ({
            amount: a.amount + r.amount,
            paid: a.paid + r.paid,
            balance: a.balance + r.balance,
        }),
        { amount: 0, paid: 0, balance: 0 },
    );

    const { shown, pager, offset } = usePaged(filtered);

    const exportCsv = () =>
        downloadCsv(
            `${item} - ${label}.csv`,
            [
                { label: "Student", value: (r) => r.student },
                { label: "Admission no.", value: (r) => r.adm_no },
                { label: "Class", value: (r) => r.class },
                { label: "Parent", value: (r) => r.parent },
                { label: "Parent phone", value: (r) => r.parent_phone },
                { label: "Bill", value: (r) => r.fee },
                { label: item, value: (r) => r.amount },
                { label: "Paid", value: (r) => r.paid },
                { label: "Balance", value: (r) => r.balance },
            ],
            filtered,
        );

    return (
        <div className="overflow-hidden rounded-lg bg-surface shadow-card">
            <div className="flex flex-wrap items-center gap-3 border-b border-border px-5 py-4">
                <h3 className="mr-1 font-semibold">{item ?? "No fees"}</h3>
                <SearchInput
                    value={q}
                    onChange={setQ}
                    placeholder="Search student, parent or phone"
                    className="w-full sm:w-64"
                    delay={0}
                />
                <Select
                    size="sm"
                    className="w-40"
                    value={cls}
                    onChange={setCls}
                    options={classes}
                    clearable
                    clearLabel="All classes"
                    placeholder="All classes"
                />
                <Segmented
                    size="sm"
                    value={status}
                    onChange={setStatus}
                    options={[
                        { value: "all", label: "All" },
                        { value: "paid", label: "Paid" },
                        { value: "part", label: "Part paid" },
                        { value: "unpaid", label: "Unpaid" },
                    ]}
                />
                <Button
                    size="sm"
                    className="ml-auto"
                    onClick={exportCsv}
                    disabled={!filtered.length}
                >
                    <Download />
                    Export
                </Button>
            </div>
            {filtered.length ? (
                <div className="scrollbar-thin overflow-x-auto">
                    <table className="w-full text-left">
                        <thead>
                            <tr className="border-b border-border bg-canvas text-2xs font-semibold uppercase text-fg-muted">
                                <th className="h-10 px-5">Student</th>
                                <th className="h-10 px-3">Class</th>
                                <th className="h-10 px-3">Parent</th>
                                <th className="h-10 px-3">Bill</th>
                                <th className="h-10 px-3 text-right">{item}</th>
                                <th className="h-10 px-3 text-right">Paid</th>
                                <th className="h-10 px-3 text-right">
                                    Balance
                                </th>
                                <th className="h-10 px-3">Status</th>
                                <th className="h-10 px-5" />
                            </tr>
                        </thead>
                        <tbody className="tabular">
                            {shown.map((r, i) => (
                                <tr
                                    key={i}
                                    className="border-b border-border last:border-0 hover:bg-muted/50"
                                >
                                    <td className="px-5 py-2.5">
                                        <div className="font-medium">
                                            {r.student}
                                        </div>
                                        <div className="text-xs text-fg-muted">
                                            {r.adm_no}
                                        </div>
                                    </td>
                                    <td className="px-3 py-2.5 text-sm">
                                        {r.class || "—"}
                                    </td>
                                    <td className="px-3 py-2.5 text-sm">
                                        <div>{r.parent || "—"}</div>
                                        {r.parent_phone && (
                                            <div className="text-xs text-fg-muted">
                                                {r.parent_phone}
                                            </div>
                                        )}
                                    </td>
                                    <td className="px-3 py-2.5 text-sm text-fg-muted">
                                        {r.fee}
                                    </td>
                                    <td className="px-3 py-2.5 text-right">
                                        {formatMoney(r.amount)}
                                    </td>
                                    <td className="px-3 py-2.5 text-right text-success-fg">
                                        {formatMoney(r.paid)}
                                    </td>
                                    <td
                                        className={cn(
                                            "px-3 py-2.5 text-right font-semibold",
                                            r.balance > 0
                                                ? "text-danger-fg"
                                                : "text-fg-muted",
                                        )}
                                    >
                                        {formatMoney(r.balance)}
                                    </td>
                                    <td className="px-3 py-2.5">
                                        {r.balance <= 0 ? (
                                            <Badge tone="success">Paid</Badge>
                                        ) : r.paid > 0 ? (
                                            <Badge tone="warning">
                                                Part paid
                                            </Badge>
                                        ) : (
                                            <Badge tone="danger">Unpaid</Badge>
                                        )}
                                    </td>
                                    <td className="px-5 py-2.5 text-right">
                                        <Button size="xs" asChild>
                                            <Link href={r.invoice_url}>
                                                {r.balance > 0
                                                    ? "Record payment"
                                                    : "View"}
                                            </Link>
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                        <tfoot className="tabular">
                            <tr className="border-t border-border bg-canvas text-sm font-semibold">
                                <td className="px-5 py-3" colSpan={4}>
                                    Total · {filtered.length}{" "}
                                    {filtered.length === 1
                                        ? "student"
                                        : "students"}
                                </td>
                                <td className="px-3 text-right">
                                    {formatMoney(totals.amount)}
                                </td>
                                <td className="px-3 text-right text-success-fg">
                                    {formatMoney(totals.paid)}
                                </td>
                                <td
                                    className={cn(
                                        "px-3 text-right",
                                        totals.balance > 0 && "text-danger-fg",
                                    )}
                                >
                                    {formatMoney(totals.balance)}
                                </td>
                                <td colSpan={2} />
                            </tr>
                        </tfoot>
                    </table>
                    {pager}
                </div>
            ) : (
                <EmptyState
                    icon={Layers}
                    title={
                        rows.length
                            ? "No students match these filters"
                            : "No fees billed for this period"
                    }
                    description={
                        rows.length
                            ? "Change or clear the filters."
                            : "School fees are set up under Payments and billed per term."
                    }
                />
            )}
        </div>
    );
}
