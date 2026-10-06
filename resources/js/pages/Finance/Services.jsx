import { useMemo, useState } from "react";
import { Head, Link, router } from "@inertiajs/react";
import {
    Bus,
    Check,
    Download,
    Music2,
    NotebookText,
    Receipt,
    Users,
    Utensils,
    Wallet,
} from "lucide-react";
import { withAppLayout } from "@/layouts/AppLayout";
import { ModuleHeader } from "@/components/app/module";
import { EmptyState } from "@/components/app/page";
import { Meter } from "@/components/app/charts";
import { SearchInput, usePaged } from "@/components/app/data-table";
import { Avatar } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Select } from "@/components/ui/select";
import { Segmented } from "@/components/ui/tabs";
import { downloadCsv } from "@/lib/use-visit-state";
import { cn, formatMoney, percent } from "@/lib/utils";

const ICONS = {
    feeding: Utensils,
    bus: Bus,
    extracurricular: Music2,
    books: NotebookText,
    sales: NotebookText,
};
/**
 * Feeding / Bus / Extra-curricular / Books roster: who takes what, and what
 * they have paid. Every filter recalculates the cards.
 */
export default function ServiceRoster({
    group,
    label,
    year,
    years,
    rows,
    options,
    routes,
    directions,
    classes,
    urls,
}) {
    const isBus = group === "bus";
    const [q, setQ] = useState("");
    const [cls, setCls] = useState("");
    const [picked, setPicked] = useState([]); // option ids, or route ids for the bus
    const [match, setMatch] = useState("exact");
    const [direction, setDirection] = useState("");
    const [status, setStatus] = useState("all");
    const Icon = ICONS[group];

    const choices = isBus ? routes : options;
    const toggle = (v) => {
        setPicked((p) =>
            p.includes(v) ? p.filter((x) => x !== v) : [...p, v],
        );
    };

    const filtered = useMemo(() => {
        const term = q.trim().toLowerCase();
        return rows.filter((r) => {
            if (
                term &&
                !`${r.name} ${r.adm_no ?? ""}`.toLowerCase().includes(term)
            )
                return false;
            if (cls && String(r.class_id) !== String(cls)) return false;
            if (isBus) {
                if (picked.length && !picked.includes(r.route_id)) return false;
                if (direction && r.direction !== direction) return false;
            } else if (picked.length) {
                // "Exactly": takes these and nothing else. "Includes": takes at least these.
                const has = picked.every((p) => r.options.includes(p));
                if (
                    !has ||
                    (match === "exact" && r.options.length !== picked.length)
                )
                    return false;
            }
            if (status === "owing" && r.balance <= 0) return false;
            if (status === "paid" && r.balance > 0) return false;
            if (status === "part" && !(r.paid > 0 && r.balance > 0))
                return false;
            if (status === "unpaid" && r.paid > 0) return false;
            return true;
        });
    }, [rows, q, cls, picked, match, direction, status, isBus]);

    const totals = filtered.reduce(
        (a, r) => ({
            amount: a.amount + r.amount,
            paid: a.paid + r.paid,
            balance: a.balance + r.balance,
        }),
        { amount: 0, paid: 0, balance: 0 },
    );
    const countFor = (v) =>
        rows.filter((r) => (isBus ? r.route_id === v : r.options.includes(v)))
            .length;
    const active = q || cls || picked.length || direction || status !== "all";

    const reset = () => {
        setQ("");
        setCls("");
        setPicked([]);
        setDirection("");
        setStatus("all");
        setMatch("exact");
    };

    const { shown, pager, offset } = usePaged(filtered);

    const exportCsv = () =>
        downloadCsv(
            `${group}-${year}.csv`,
            [
                { label: "Student", value: (r) => r.name },
                { label: "Admission no.", value: (r) => r.adm_no },
                { label: "Class", value: (r) => r.class },
                {
                    label: isBus ? "Route" : "Takes",
                    value: (r) => r.lines.map((l) => l.label).join("; "),
                },
                { label: "Amount", value: (r) => r.amount },
                { label: "Paid", value: (r) => r.paid },
                { label: "Balance", value: (r) => r.balance },
            ],
            filtered,
        );

    return (
        <>
            <Head title={label} />
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6">
                <ModuleHeader
                    crumbs={["Finance", "Services", label]}
                    title={label}
                    description={`Students on ${label.toLowerCase()} this year, what they take and what they have paid.`}
                    session={year}
                    aside={
                        years.length > 1 ? (
                            <Select
                                size="sm"
                                className="w-36"
                                value={year}
                                onChange={(v) =>
                                    router.get(urls.self, { year: v })
                                }
                                options={years.map((y) => ({
                                    value: y,
                                    label: y,
                                }))}
                            />
                        ) : null
                    }
                />

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Card
                        icon={Users}
                        label="Students"
                        value={filtered.length}
                        note={
                            active
                                ? `of ${rows.length} on ${label.toLowerCase()}`
                                : "On this service"
                        }
                    />
                    <Card
                        icon={Wallet}
                        label="Total billed"
                        value={formatMoney(totals.amount)}
                        note={
                            active ? "For the filtered students" : "This year"
                        }
                    />
                    <Card
                        icon={Check}
                        label="Amount paid"
                        value={formatMoney(totals.paid)}
                        tone="success"
                        note="Received so far"
                        meter={[totals.paid, totals.amount]}
                    />
                    <Card
                        icon={Receipt}
                        label="Amount due"
                        value={formatMoney(totals.balance)}
                        tone="danger"
                        note={`${filtered.filter((r) => r.balance > 0).length} parents still to pay`}
                    />
                </div>

                <div className="flex flex-col gap-4 rounded-lg bg-surface p-5 shadow-card">
                    <div className="flex flex-wrap items-center gap-3">
                        <SearchInput
                            value={q}
                            onChange={setQ}
                            placeholder="Search student or admission no."
                            className="w-full sm:w-72"
                            delay={0}
                        />
                        <Select
                            size="sm"
                            className="w-44"
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
                                { value: "owing", label: "Owing" },
                                { value: "part", label: "Part paid" },
                                { value: "unpaid", label: "Unpaid" },
                                { value: "paid", label: "Paid" },
                            ]}
                        />
                        <div className="ml-auto flex gap-2">
                            {active && (
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    onClick={reset}
                                >
                                    Clear filters
                                </Button>
                            )}
                            <Button
                                size="sm"
                                onClick={exportCsv}
                                disabled={!filtered.length}
                            >
                                <Download />
                                Export
                            </Button>
                        </div>
                    </div>

                    <div className="flex flex-col gap-2">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <span className="text-sm font-medium">
                                {isBus ? "Routes" : "Takes"}{" "}
                                <span className="font-normal text-fg-muted">
                                    · pick one or more
                                </span>
                            </span>
                            {isBus ? (
                                <Segmented
                                    size="sm"
                                    value={direction}
                                    onChange={setDirection}
                                    options={[
                                        { value: "", label: "Any direction" },
                                        ...directions,
                                    ]}
                                />
                            ) : (
                                picked.length > 0 && (
                                    <Segmented
                                        size="sm"
                                        value={match}
                                        onChange={setMatch}
                                        options={[
                                            {
                                                value: "exact",
                                                label:
                                                    picked.length === 1
                                                        ? "Only this"
                                                        : "Only these",
                                            },
                                            {
                                                value: "includes",
                                                label: "Includes",
                                            },
                                        ]}
                                    />
                                )
                            )}
                        </div>
                        {choices.length ? (
                            <div className="flex flex-wrap gap-2">
                                {choices.map((c) => {
                                    const on = picked.includes(c.value);
                                    return (
                                        <button
                                            key={c.value}
                                            type="button"
                                            aria-pressed={on}
                                            onClick={() => toggle(c.value)}
                                            className={cn(
                                                "inline-flex h-8 items-center gap-2 rounded-full px-3 text-sm font-medium shadow-field transition-shadow",
                                                on
                                                    ? "bg-primary text-white shadow-none"
                                                    : "bg-surface hover:shadow-card-hover",
                                            )}
                                        >
                                            {on && (
                                                <Check
                                                    className="size-3.5"
                                                    strokeWidth={3}
                                                />
                                            )}
                                            {c.label}
                                            <span
                                                className={cn(
                                                    "tabular rounded-full px-1.5 text-xs",
                                                    on
                                                        ? "bg-white/20"
                                                        : "bg-subtle text-fg-muted",
                                                )}
                                            >
                                                {countFor(c.value)}
                                            </span>
                                        </button>
                                    );
                                })}
                            </div>
                        ) : (
                            <p className="text-sm text-fg-subtle">
                                Nothing configured yet.{" "}
                                <Link
                                    href={urls.config}
                                    className="text-primary hover:underline"
                                >
                                    Finance configuration
                                </Link>
                            </p>
                        )}
                        {!isBus && picked.length > 0 && (
                            <p className="text-xs text-fg-muted">
                                {match === "exact"
                                    ? `Showing students on ${picked.map((p) => choices.find((c) => c.value === p)?.label).join(" + ")} and nothing else.`
                                    : `Showing students on ${picked.map((p) => choices.find((c) => c.value === p)?.label).join(" + ")}, with or without others.`}
                            </p>
                        )}
                    </div>
                </div>

                <div className="overflow-hidden rounded-lg bg-surface shadow-card">
                    {filtered.length ? (
                        <div className="scrollbar-thin overflow-x-auto">
                            <table className="w-full text-left">
                                <thead>
                                    <tr className="border-b border-border bg-canvas text-2xs font-semibold uppercase text-fg-muted">
                                        <th className="h-10 px-5">Student</th>
                                        <th className="h-10 px-3">Class</th>
                                        <th className="h-10 px-3">
                                            {isBus ? "Route" : "Takes"}
                                        </th>
                                        <th className="h-10 px-3 text-right">
                                            Amount
                                        </th>
                                        <th className="h-10 px-3 text-right">
                                            Paid
                                        </th>
                                        <th className="h-10 px-3 text-right">
                                            Balance
                                        </th>
                                        <th className="h-10 px-3">Status</th>
                                        <th className="h-10 px-5" />
                                    </tr>
                                </thead>
                                <tbody className="tabular">
                                    {shown.map((r) => (
                                        <tr
                                            key={r.id}
                                            className="border-b border-border align-top last:border-0 hover:bg-muted/50"
                                        >
                                            <td className="px-5 py-3">
                                                <div className="flex items-center gap-3">
                                                    <Avatar
                                                        src={r.photo}
                                                        name={r.name}
                                                        size="md"
                                                    />
                                                    <div className="min-w-0">
                                                        <div className="truncate font-medium">
                                                            {r.name}
                                                        </div>
                                                        <div className="text-xs text-fg-muted">
                                                            {r.adm_no}
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-3 py-3 text-sm">
                                                {r.class || "—"}
                                            </td>
                                            <td className="px-3 py-3">
                                                <div className="flex flex-col gap-1">
                                                    {r.lines.map((l, i) => (
                                                        <div
                                                            key={i}
                                                            className="flex items-center gap-2 text-sm"
                                                        >
                                                            <span
                                                                className={cn(
                                                                    "size-1.5 shrink-0 rounded-full",
                                                                    l.balance >
                                                                        0
                                                                        ? l.paid >
                                                                          0
                                                                            ? "bg-warning"
                                                                            : "bg-danger"
                                                                        : "bg-success",
                                                                )}
                                                            />
                                                            <span className="truncate">
                                                                {isBus
                                                                    ? l.label.replace(
                                                                          /^Bus · /,
                                                                          "",
                                                                      )
                                                                    : l.label}
                                                            </span>
                                                            <span className="text-xs text-fg-subtle">
                                                                {formatMoney(
                                                                    l.paid,
                                                                )}
                                                                /
                                                                {formatMoney(
                                                                    l.amount,
                                                                )}
                                                            </span>
                                                        </div>
                                                    ))}
                                                </div>
                                            </td>
                                            <td className="px-3 py-3 text-right">
                                                {formatMoney(r.amount)}
                                            </td>
                                            <td className="px-3 py-3 text-right text-success-fg">
                                                {formatMoney(r.paid)}
                                            </td>
                                            <td
                                                className={cn(
                                                    "px-3 py-3 text-right font-semibold",
                                                    r.balance > 0
                                                        ? "text-danger-fg"
                                                        : "text-fg-muted",
                                                )}
                                            >
                                                {formatMoney(r.balance)}
                                            </td>
                                            <td className="px-3 py-3">
                                                {r.balance <= 0 ? (
                                                    <Badge tone="success">
                                                        Paid
                                                    </Badge>
                                                ) : r.paid > 0 ? (
                                                    <Badge tone="warning">
                                                        Part paid
                                                    </Badge>
                                                ) : (
                                                    <Badge tone="danger">
                                                        Unpaid
                                                    </Badge>
                                                )}
                                            </td>
                                            <td className="px-5 py-3 text-right">
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
                                        <td className="px-5 py-3" colSpan={3}>
                                            Total · {filtered.length} students
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
                                                totals.balance > 0 &&
                                                    "text-danger-fg",
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
                            icon={Icon}
                            title={
                                rows.length
                                    ? "No students match these filters"
                                    : `No students on ${label.toLowerCase()} yet`
                            }
                            description={
                                rows.length
                                    ? "Change or clear the filters."
                                    : "Services are chosen when admitting a student, or on the student’s edit page."
                            }
                            action={
                                active ? (
                                    <Button size="sm" onClick={reset}>
                                        Clear filters
                                    </Button>
                                ) : null
                            }
                        />
                    )}
                </div>
            </div>
        </>
    );
}

ServiceRoster.layout = withAppLayout;

function Card({ label, value, tone }) {
    return (
        <div className="flex flex-col gap-2 rounded-lg bg-surface p-5 shadow-card">
            <span className="text-2xs font-semibold uppercase tracking-wider text-fg-subtle">
                {label}
            </span>
            <span
                className={cn(
                    "tabular text-3xl font-semibold tracking-tight",
                    tone === "success" && "text-success-fg",
                    tone === "danger" && "text-danger-fg",
                )}
            >
                {value}
            </span>
        </div>
    );
}
