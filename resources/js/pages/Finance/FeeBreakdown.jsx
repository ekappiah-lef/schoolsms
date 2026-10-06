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
import { DropdownMenu, DropdownMenuCheckboxItem, DropdownMenuContent, DropdownMenuLabel, DropdownMenuTrigger } from "@/components/ui/dropdown-menu";
import { downloadCsv } from "@/lib/use-visit-state";
import { cn, formatMoney } from "@/lib/utils";

/**
 * Fee breakdown, for management and audit:
 *  1. Term by term: bills sent, money received, expenses and the balance of every term.
 *  2. Fee items: what each item (Tuition, P.T.A, Medicals, Uniform …) comes to for
 *     the chosen term or year, and which parent / child has paid it.
 */
export default function FeeBreakdown({
    session,
    years,
    term,
    item,
    items,
    termly,
    rows,
    urls,
}) {
    const go = (changes, scrollTo) =>
        router.get(
            urls.self,
            { year: session, ...(term ? { term } : {}), item, ...changes },
            {
                preserveScroll: true,
                onSuccess: () =>
                    scrollTo &&
                    document
                        .getElementById(scrollTo)
                        ?.scrollIntoView({
                            behavior: "smooth",
                            block: "start",
                        }),
            },
        );

    return (
        <>
            <Head title="Fee breakdown" />
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-8">
                <ModuleHeader
                    crumbs={["Finance", "Fee breakdown"]}
                    title="Fee breakdown"
                    description="What was billed, received and spent each term, and every fee item by student and parent."
                />

                <TermTable
                    rows={termly}
                    session={session}
                    term={term}
                    ledgerUrl={urls.ledger}
                    onPick={(r) =>
                        go({ year: r.session, term: r.term }, "fee-items")
                    }
                />

                <section
                    id="fee-items"
                    className="flex scroll-mt-6 flex-col gap-4"
                >
                    <div className="flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <h2 className="text-lg font-semibold">Fee items</h2>
                            <p className="text-sm text-fg-muted">
                                {term ? `Term ${term} · ` : "Whole year · "}
                                {session.replace("-", " – ")}
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Segmented
                                size="sm"
                                value={String(term ?? "")}
                                onChange={(v) => go({ term: v || undefined })}
                                options={[
                                    { value: "", label: "Whole year" },
                                    { value: "1", label: "Term 1" },
                                    { value: "2", label: "Term 2" },
                                    { value: "3", label: "Term 3" },
                                ]}
                            />
                            <Select
                                size="sm"
                                className="w-36"
                                value={session}
                                onChange={(v) => go({ year: v })}
                                options={years.map((y) => ({
                                    value: y,
                                    label: y.replace("-", " – "),
                                }))}
                            />
                        </div>
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
                                            on
                                                ? "bg-primary-soft/50 shadow-[0_0_0_1.5px_rgb(var(--primary))]"
                                                : "bg-surface",
                                        )}
                                    >
                                        <span className="font-medium">
                                            {i.name}
                                        </span>
                                        <dl className="tabular mt-1 flex flex-col gap-1.5 text-sm">
                                            <div className="flex items-baseline justify-between gap-3">
                                                <dt className="text-fg-muted">
                                                    Total
                                                </dt>
                                                <dd className="text-xl font-semibold tracking-tight">
                                                    {formatMoney(i.billed)}
                                                </dd>
                                            </div>
                                            <div className="flex justify-between gap-3">
                                                <dt className="text-fg-muted">
                                                    Paid
                                                </dt>
                                                <dd className="font-medium text-success-fg">
                                                    {formatMoney(i.paid)}
                                                </dd>
                                            </div>
                                            <div className="flex justify-between gap-3">
                                                <dt className="text-fg-muted">
                                                    Owed
                                                </dt>
                                                <dd
                                                    className={cn(
                                                        "font-medium",
                                                        i.due > 0
                                                            ? "text-danger-fg"
                                                            : "text-fg-subtle",
                                                    )}
                                                >
                                                    {formatMoney(i.due)}
                                                </dd>
                                            </div>
                                        </dl>
                                    </button>
                                );
                            })}
                        </div>
                    ) : null}

                    <ItemTable
                        key={`${session}-${term}-${item}`}
                        item={item}
                        rows={rows}
                        session={session}
                        term={term}
                    />
                </section>
            </div>
        </>
    );
}

FeeBreakdown.layout = withAppLayout;

const TERM_COLUMNS = [
    { key: 'invoiced', label: 'Invoiced', help: 'Bills sent for the term' },
    { key: 'paid', label: 'Paid', help: 'Paid against those bills', tone: 'success' },
    { key: 'outstanding', label: 'Still owed', help: 'Not yet paid', tone: 'owed' },
    { key: 'fees', label: 'Fees received', help: 'Fee payments made during the term (incl. arrears)', tone: 'success' },
    { key: 'other', label: 'Other income', help: 'Capital, grants, donations', tone: 'success' },
    { key: 'expenses', label: 'Expenses', help: 'Spent during the term', tone: 'danger' },
    { key: 'net', label: 'Term balance', help: 'Money received less expenses', signed: true, strong: true },
    { key: 'closing', label: 'Cash at end', help: 'School cash at the end of the term', muted: true },
];
const DEFAULT_COLUMNS = ['invoiced', 'paid', 'outstanding', 'net'];
const COLUMNS_KEY = 'fee-breakdown.term-columns';

/** Every term grouped by school year; choose the year and which figures to show. */
function TermTable({ rows, session, term, ledgerUrl, onPick }) {
    const groups = useMemo(() => {
        const g = [];
        rows.forEach((r) => {
            let y = g.find((x) => x.session === r.session);
            if (!y) g.push((y = { session: r.session, terms: [] }));
            y.terms.push(r);
        });
        return g.map((y) => ({
            ...y,
            ...Object.fromEntries(['invoiced', 'paid', 'outstanding', 'fees', 'other', 'expenses', 'net'].map((k) => [k, y.terms.reduce((t, r) => t + r[k], 0)])),
            closing: y.terms[0].closing,
        }));
    }, [rows]);

    const [year, setYear] = useState(() => (groups.some((g) => g.session === session) ? session : groups[0]?.session ?? ''));
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
    const visible = year ? groups.filter((g) => g.session === year) : groups;

    const cell = (c, r, strongRow) => {
        const v = r[c.key];
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
                    <p className="text-sm text-fg-muted">Click a term to see its fee items.</p>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <Select
                        size="sm"
                        className="w-40"
                        value={year}
                        onChange={setYear}
                        options={groups.map((g) => ({ value: g.session, label: g.session.replace('-', ' – ') }))}
                        clearable
                        clearLabel="All years"
                        placeholder="All years"
                    />
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
                                <th key={c.key} className="h-10 px-4 text-right" title={c.help}>
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
                                {y.terms.map((r) => {
                                    const selected = r.session === session && r.term === term;
                                    return (
                                        <tr key={r.key} onClick={() => onPick(r)} className={cn('cursor-pointer border-b border-border last:border-0 hover:bg-muted/50', selected && 'bg-primary-soft/40')}>
                                            <td className={cn('py-2.5 pr-6', visible.length > 1 ? 'pl-10' : 'pl-6')}>
                                                Term {r.term}
                                                {r.current && <Badge className="ml-2">Now</Badge>}
                                            </td>
                                            {shownCols.map((c) => cell(c, r))}
                                        </tr>
                                    );
                                })}
                                {visible.length === 1 && y.terms.length > 1 && (
                                    <tr className="border-t border-border bg-canvas font-semibold">
                                        <td className="px-6 py-2.5">Year total</td>
                                        {shownCols.map((c) => cell(c, y, true))}
                                    </tr>
                                )}
                            </Fragment>
                        ))}
                    </tbody>
                </table>
            </div>
        </section>
    );
}

const signed = (v) => (v < 0 ? `−${formatMoney(Math.abs(v))}` : formatMoney(v));

/** Who has paid the chosen fee item. */
function ItemTable({ item, rows, session, term }) {
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
            `${item}-${session}${term ? `-term${term}` : ""}.csv`,
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
                    onChange={(v) => setQ}
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
