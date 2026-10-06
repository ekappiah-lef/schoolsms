import { useMemo, useState } from "react";
import { Head, Link, router } from "@inertiajs/react";
import { ArrowDownLeft, ArrowUpRight, Download } from "lucide-react";
import { withAppLayout } from "@/layouts/AppLayout";
import { ModuleHeader } from "@/components/app/module";
import { EmptyState } from "@/components/app/page";
import { SearchInput, usePaged } from "@/components/app/data-table";
import { Button } from "@/components/ui/button";
import { Select } from "@/components/ui/select";
import { Segmented } from "@/components/ui/tabs";
import { BalanceCards, PeriodFilter } from "@/components/fees/period-picker";
import { downloadCsv } from "@/lib/use-visit-state";
import { cn, formatDate, formatMoney } from "@/lib/utils";

/**
 * Ledger: every movement of money (school-fee receipts, optional-service
 * receipts, other income, expenses) in the chosen terms, with the balance after
 * each one counted from zero at the start of the selection.
 */
const signedMoney = (v) =>
    v < 0 ? `−${formatMoney(Math.abs(v))}` : formatMoney(v);

export default function Ledger({
    selection,
    invoiced,
    received,
    expenses,
    rows,
    filters,
    urls,
}) {
    const [dir, setDir] = useState(filters.dir || "all");
    const [source, setSource] = useState(filters.source || "");
    const [category, setCategory] = useState(filters.category || "");
    const [q, setQ] = useState("");

    const sources = useMemo(
        () =>
            [...new Set(rows.map((r) => r.source))]
                .sort()
                .map((s) => ({ value: s, label: s })),
        [rows],
    );
    const categories = useMemo(
        () =>
            [
                ...new Set(
                    rows
                        .filter((r) => !source || r.source === source)
                        .map((r) => r.category),
                ),
            ]
                .sort()
                .map((c) => ({ value: c, label: c })),
        [rows, source],
    );

    const filtered = useMemo(() => {
        const term = q.trim().toLowerCase();
        return rows.filter(
            (r) =>
                (dir === "all" || r.dir === dir) &&
                (!source || r.source === source) &&
                (!category || r.category === category) &&
                (!term ||
                    `${r.party} ${r.category} ${r.ref} ${r.detail} ${r.source}`
                        .toLowerCase()
                        .includes(term)),
        );
    }, [rows, dir, source, category, q]);

    const fIn = filtered
        .filter((r) => r.dir === "in")
        .reduce((a, r) => a + r.amount, 0);
    const fOut = filtered
        .filter((r) => r.dir === "out")
        .reduce((a, r) => a + r.amount, 0);
    const isFiltered = dir !== "all" || source || category || q;
    // The running balance counts from zero at the start of the chosen terms.
    const balanceLabel = `${selection.balanceName} after`;

    const { shown, pager } = usePaged(filtered);

    const exportCsv = () =>
        downloadCsv(
            `ledger ${selection.label}.csv`,
            [
                {
                    label: "Date",
                    value: (r) => formatDate(r.at, "dd/MM/yyyy HH:mm"),
                },
                { label: "Source", value: (r) => r.source },
                { label: "Item / category", value: (r) => r.category },
                { label: "Paid by / description", value: (r) => r.party },
                { label: "Details", value: (r) => r.detail },
                { label: "Reference", value: (r) => r.ref },
                {
                    label: "Money in",
                    value: (r) => (r.dir === "in" ? r.amount : ""),
                },
                {
                    label: "Money out",
                    value: (r) => (r.dir === "out" ? r.amount : ""),
                },
                { label: balanceLabel, value: (r) => r.balance },
            ],
            filtered,
        );

    return (
        <>
            <Head title="Ledger" />
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6">
                <ModuleHeader
                    crumbs={["Finance", "Ledger"]}
                    title="Ledger"
                    aside={<PeriodFilter selection={selection} url={urls.self} />}
                />

                <BalanceCards
                    selection={selection}
                    invoiced={invoiced}
                    received={received}
                    expenses={expenses}
                />

                <div className="flex flex-col gap-3 rounded-lg bg-surface p-4 shadow-card">
                    <div className="flex flex-wrap items-center gap-3">
                        <SearchInput
                            value={q}
                            onChange={setQ}
                            placeholder="Search name, item, reference…"
                            className="w-full sm:w-72"
                            delay={0}
                        />
                        <Segmented
                            size="sm"
                            value={dir}
                            onChange={setDir}
                            options={[
                                { value: "all", label: "All" },
                                { value: "in", label: "Money in" },
                                { value: "out", label: "Money out" },
                            ]}
                        />
                        <Select
                            size="sm"
                            className="w-48"
                            value={source}
                            onChange={(v) => {
                                setSource(v);
                                setCategory("");
                            }}
                            options={sources}
                            clearable
                            clearLabel="All sources"
                            placeholder="All sources"
                        />
                        <Select
                            size="sm"
                            className="w-56"
                            value={category}
                            onChange={setCategory}
                            options={categories}
                            clearable
                            clearLabel="All items"
                            placeholder="All items"
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
                    {isFiltered && (
                        <div className="tabular flex flex-wrap gap-x-6 gap-y-1 text-sm text-fg-muted">
                            <span>{filtered.length} entries</span>
                            <span>
                                In{" "}
                                <span className="font-semibold text-success-fg">
                                    {formatMoney(fIn)}
                                </span>
                            </span>
                            <span>
                                Out{" "}
                                <span className="font-semibold text-danger-fg">
                                    {formatMoney(fOut)}
                                </span>
                            </span>
                            <button
                                type="button"
                                className="text-primary hover:underline"
                                onClick={() => {
                                    setDir("all");
                                    setSource("");
                                    setCategory("");
                                    setQ("");
                                }}
                            >
                                Clear filters
                            </button>
                        </div>
                    )}
                </div>

                <div className="overflow-hidden rounded-lg bg-surface shadow-card">
                    {filtered.length ? (
                        <div className="scrollbar-thin overflow-x-auto">
                            <table className="w-full text-left">
                                <thead>
                                    <tr className="border-b border-border bg-canvas text-2xs font-semibold uppercase text-fg-muted">
                                        <th className="h-10 px-4">Date</th>
                                        <th className="h-10 px-3">
                                            Source / item
                                        </th>
                                        <th className="h-10 px-3">
                                            Paid by / description
                                        </th>
                                        <th className="h-10 px-3">Reference</th>
                                        <th className="h-10 px-3 text-right">
                                            Money in
                                        </th>
                                        <th className="h-10 px-3 text-right">
                                            Money out
                                        </th>
                                        <th className="h-10 px-4 text-right">
                                            {balanceLabel}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="tabular text-sm">
                                    {shown.map((r) => (
                                        <tr
                                            key={r.key}
                                            className="border-b border-border align-top last:border-0 hover:bg-muted/50"
                                        >
                                            <td className="whitespace-nowrap px-4 py-2.5 text-fg-muted">
                                                {formatDate(r.at, "dd/MM/yyyy")}
                                                {r.key[0] !== "t" && (
                                                    <div className="text-xs text-fg-subtle">
                                                        {formatDate(
                                                            r.at,
                                                            "HH:mm",
                                                        )}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="px-3 py-2.5">
                                                <div className="flex items-center gap-1.5 font-medium">
                                                    {r.dir === "in" ? (
                                                        <ArrowDownLeft className="size-3.5 text-success-fg" />
                                                    ) : (
                                                        <ArrowUpRight className="size-3.5 text-danger-fg" />
                                                    )}
                                                    {r.source}
                                                </div>
                                                <div className="text-xs text-fg-muted">
                                                    {r.category}
                                                </div>
                                            </td>
                                            <td className="max-w-xs px-3 py-2.5">
                                                {r.party_url ? (
                                                    <Link
                                                        href={r.party_url}
                                                        className="font-medium hover:text-primary hover:underline"
                                                    >
                                                        {r.party}
                                                    </Link>
                                                ) : (
                                                    <span>{r.party}</span>
                                                )}
                                                {r.detail && (
                                                    <div className="truncate text-xs text-fg-muted">
                                                        {r.detail}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="px-3 py-2.5">
                                                <a
                                                    href={r.url}
                                                    target={
                                                        r.key[0] === "t"
                                                            ? undefined
                                                            : "_blank"
                                                    }
                                                    rel="noreferrer"
                                                    className="text-primary hover:underline"
                                                >
                                                    {r.ref}
                                                </a>
                                            </td>
                                            <td className="px-3 py-2.5 text-right font-medium text-success-fg">
                                                {r.dir === "in"
                                                    ? formatMoney(r.amount)
                                                    : ""}
                                            </td>
                                            <td className="px-3 py-2.5 text-right font-medium text-danger-fg">
                                                {r.dir === "out"
                                                    ? formatMoney(r.amount)
                                                    : ""}
                                            </td>
                                            <td
                                                className={cn(
                                                    "px-4 py-2.5 text-right font-semibold",
                                                    r.balance < 0 &&
                                                        "text-danger-fg",
                                                )}
                                            >
                                                {signedMoney(r.balance)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                            {pager}
                        </div>
                    ) : (
                        <EmptyState
                            title="No entries"
                            description={
                                isFiltered
                                    ? "No entries match these filters."
                                    : "No money moved in the chosen terms."
                            }
                        />
                    )}
                </div>
                <p className="text-xs text-fg-muted">
                    “{balanceLabel}” is money received less expenses from the start of {selection.label} up to each entry (each term starts from zero),
                    counting every entry (not only the filtered ones). Fee
                    payments come from receipts; other income and expenses from{" "}
                    <Link
                        href={urls.transactions}
                        className="text-primary hover:underline"
                    >
                        Income &amp; expenses
                    </Link>
                    .
                </p>
            </div>
        </>
    );
}

Ledger.layout = withAppLayout;
