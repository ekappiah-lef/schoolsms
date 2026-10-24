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
import { Checkbox } from "@/components/ui/checkbox";
import { Modal } from "@/components/ui/dialog";
import { submitForm } from "@/lib/http";
import { toast } from "sonner";
import { MultiSelect, Select } from "@/components/ui/select";
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
const STATUSES = [
    { value: "owing", label: "Owing" },
    { value: "part", label: "Part paid" },
    { value: "unpaid", label: "Unpaid" },
    { value: "paid", label: "Paid" },
];

export default function ServiceRoster({
    group,
    label,
    year,
    term,
    years,
    rows,
    options,
    routes,
    directions,
    classes,
    urls,
    pupils = [],
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
                    session={term ? `Term ${term} · ${year}` : year}
                    aside={
                        <div className="flex gap-2">
                            {term && (
                                <Select
                                    size="sm"
                                    className="w-28"
                                    value={String(term)}
                                    onChange={(v) =>
                                        router.get(urls.self, { year, term: v })
                                    }
                                    options={[1, 2, 3].map((t) => ({
                                        value: String(t),
                                        label: `Term ${t}`,
                                    }))}
                                />
                            )}
                            {years.length > 1 && (
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
                            )}
                            {urls.add && <AddToService url={urls.add} group={group} label={label} options={options} routes={routes} directions={directions} classes={classes} pupils={pupils} />}
                        </div>
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
                            active ? "For the filtered students" : term ? `Term ${term}` : "This year"
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

                <div className="flex flex-col gap-3 rounded-xl bg-surface p-5 shadow-card">
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
                        <Select
                            size="sm"
                            className="w-40"
                            value={status === "all" ? "" : status}
                            onChange={(v) => setStatus(v || "all")}
                            options={STATUSES}
                            clearable
                            clearLabel="All payments"
                            placeholder="All payments"
                        />
                        {isBus ? (
                            <Select
                                size="sm"
                                className="w-40"
                                value={direction}
                                onChange={setDirection}
                                options={directions}
                                clearable
                                clearLabel="Any direction"
                                placeholder="Any direction"
                            />
                        ) : (
                            picked.length > 0 && (
                                <Select
                                    size="sm"
                                    className="w-40"
                                    value={match}
                                    onChange={(v) => setMatch(v || "exact")}
                                    options={[
                                        { value: "exact", label: picked.length === 1 ? "Only this" : "Only these" },
                                        { value: "includes", label: "Includes" },
                                    ]}
                                />
                            )
                        )}
                        <div className="ml-auto flex gap-2">
                            {active && (
                                <Button size="sm" variant="ghost" onClick={reset}>
                                    Clear filters
                                </Button>
                            )}
                            <Button size="sm" onClick={exportCsv} disabled={!filtered.length}>
                                <Download />
                                Export
                            </Button>
                        </div>
                    </div>
                    {choices.length ? (
                        <MultiSelect
                            value={picked}
                            onChange={setPicked}
                            options={choices.map((c) => ({ value: c.value, label: c.label, hint: countFor(c.value) }))}
                            placeholder={isBus ? "All routes" : `All ${label.toLowerCase()}`}
                            searchPlaceholder={isBus ? "Search routes" : "Search services"}
                        />
                    ) : (
                        <p className="text-sm text-fg-subtle">
                            Nothing configured yet.{" "}
                            <Link href={urls.config} className="text-primary hover:underline">
                                Finance configuration
                            </Link>
                        </p>
                    )}
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

/** Admins: give this service to chosen students (pick a class, tick the students). */
function AddToService({ url, group, label, options, routes, directions, classes, pupils }) {
    const [open, setOpen] = useState(false);
    const [cls, setCls] = useState("");
    const [picked, setPicked] = useState([]);
    const [option, setOption] = useState("");
    const [route, setRoute] = useState("");
    const [direction, setDirection] = useState("both");
    const [busy, setBusy] = useState(false);
    const isBus = group === "bus";
    const list = pupils.filter((p) => String(p.class) === String(cls));
    const toggle = (id) => setPicked((x) => (x.includes(id) ? x.filter((y) => y !== id) : [...x, id]));
    const save = async () => {
        setBusy(true);
        const r = await submitForm(url, { students: picked.join(","), option, route, direction });
        setBusy(false);
        if (!r.ok) return toast.error(r.message);
        toast.success(r.message);
        setOpen(false);
        setPicked([]);
        router.reload({ preserveScroll: true });
        return undefined;
    };
    return (
        <>
            <Button size="sm" variant="primary" onClick={() => setOpen(true)}>
                Add students
            </Button>
            <Modal
                open={open}
                onOpenChange={(o) => !busy && setOpen(o)}
                title={`Add to ${label}`}
                className="max-w-xl"
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setOpen(false)} disabled={busy}>
                            Cancel
                        </Button>
                        <Button variant="primary" onClick={save} loading={busy} disabled={!picked.length || (isBus ? !route : !option)}>
                            Add {picked.length || ""}
                        </Button>
                    </>
                }
            >
                <div className="flex flex-col gap-3">
                    {isBus ? (
                        <div className="grid grid-cols-2 gap-2">
                            <Select value={route} onChange={setRoute} options={routes.map((r) => ({ value: String(r.value), label: r.label }))} placeholder="Route" />
                            <Select value={direction} onChange={setDirection} options={directions} />
                        </div>
                    ) : (
                        <Select value={option} onChange={setOption} options={options.map((o) => ({ value: String(o.value), label: o.label }))} placeholder="Service" />
                    )}
                    <Select value={cls} onChange={(v) => { setCls(v); setPicked([]); }} options={classes.map((c) => ({ value: String(c.value), label: c.label }))} placeholder="Class" />
                    {cls && (
                        <div className="max-h-72 overflow-y-auto rounded-lg border border-border p-2">
                            <label className="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-sm font-medium hover:bg-muted">
                                <Checkbox checked={list.length > 0 && picked.length === list.length} onCheckedChange={(v) => setPicked(v ? list.map((p) => p.id) : [])} />
                                All
                            </label>
                            {list.map((p) => (
                                <label key={p.id} className="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-muted">
                                    <Checkbox checked={picked.includes(p.id)} onCheckedChange={() => toggle(p.id)} />
                                    {p.name}
                                </label>
                            ))}
                        </div>
                    )}
                </div>
            </Modal>
        </>
    );
}
