import { useMemo, useState } from "react";
import { Head, Link, router } from "@inertiajs/react";
import { ArrowRight, Download, Search, Send, Wallet } from "lucide-react";
import { toast } from "sonner";
import { ConfirmDialog } from "@/components/ui/dialog";
import { submitForm } from "@/lib/http";
import { usePaged } from "@/components/app/data-table";
import { withAppLayout } from "@/layouts/AppLayout";
import { EmptyState, PageHeader, Panel } from "@/components/app/page";
import { Meter } from "@/components/app/charts";
import { Avatar } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Combobox } from "@/components/ui/select";
import { Segmented } from "@/components/ui/tabs";
import { downloadCsv, useVisitState } from "@/lib/use-visit-state";
import { cn, formatMoney, formatNumber, percent } from "@/lib/utils";

export default function PaymentsManage({
    session,
    classId,
    classes,
    students,
    urls,
}) {
    const busy = useVisitState();
    const [query, setQuery] = useState("");
    const [status, setStatus] = useState("all");

    // Selecting a class runs the existing select_class action, which also
    // creates any missing payment records for the class this year.
    const selectClass = (id) =>
        id &&
        router.post(urls.select, { my_class_id: id }, { preserveScroll: true });

    const totals = useMemo(
        () =>
            students.reduce(
                (a, s) => ({
                    amount: a.amount + s.amount,
                    paid: a.paid + s.paid,
                    balance: a.balance + s.balance,
                }),
                { amount: 0, paid: 0, balance: 0 },
            ),
        [students],
    );
    const counts = useMemo(
        () => ({
            owing: students.filter((s) => s.balance > 0).length,
            cleared: students.filter((s) => s.items > 0 && s.balance === 0)
                .length,
        }),
        [students],
    );
    const rows = students.filter((s) => {
        if (
            query &&
            !`${s.name} ${s.adm_no}`.toLowerCase().includes(query.toLowerCase())
        )
            return false;
        if (status === "owing") return s.balance > 0;
        if (status === "cleared") return s.items > 0 && s.balance === 0;
        return true;
    });
    const { shown, pager } = usePaged(rows, 10, "students");
    const className = classes.find((c) => c.id === classId)?.name;

    return (
        <>
            <Head title="Student payments" />
            <PageHeader
                breadcrumbs={[
                    { label: "Finance" },
                    { label: "Student payments" },
                ]}
                title="Student payments"
                meta={<Badge tone="outline">{session} session</Badge>}
                description="Choose a class to see what each student owes and record payments."
            />

            <div className="mb-6 flex flex-wrap items-end gap-3">
                <div className="w-full sm:w-72">
                    <label className="mb-1.5 block text-sm font-medium">
                        Class
                    </label>
                    <Combobox
                        value={classId ?? ""}
                        onChange={selectClass}
                        options={classes.map((c) => ({
                            value: c.id,
                            label: c.name,
                        }))}
                        placeholder="Choose a class"
                        clearable={false}
                    />
                </div>
                {busy && (
                    <span className="pb-2 text-sm text-fg-muted">
                        Loading class…
                    </span>
                )}
                {classId && urls.sendInvoices && <SendClassInvoices url={urls.sendInvoices} className={className} count={students.length} />}
            </div>

            {!classId ? (
                <Panel>
                    <EmptyState
                        icon={Wallet}
                        title="Choose a class to begin"
                        description="Payment records for the current year are prepared automatically when a class is opened."
                    />
                </Panel>
            ) : (
                <div className="space-y-6">
                    <div className="panel grid divide-y divide-border sm:grid-cols-4 sm:divide-x sm:divide-y-0">
                        <Figure
                            label={`${className} · expected`}
                            value={formatMoney(totals.amount)}
                            note={`${formatNumber(students.length)} students`}
                        />
                        <Figure
                            label="Collected"
                            value={formatMoney(totals.paid)}
                            note={`of ${formatMoney(totals.amount)} expected`}
                            tone="success"
                        />
                        <Figure
                            label="Outstanding"
                            value={formatMoney(totals.balance)}
                            note={`${counts.owing} students owing`}
                            tone={totals.balance > 0 ? "danger" : undefined}
                        />
                        <div className="px-4 py-3.5">
                            <div className="overline-label">
                                Collection progress
                            </div>
                            <div className="mt-3">
                                <Meter
                                    value={totals.paid}
                                    max={totals.amount}
                                />
                            </div>
                        </div>
                    </div>

                    <Panel
                        flush
                        title="Students"
                        actions={
                            <Button
                                size="sm"
                                variant="ghost"
                                onClick={() =>
                                    downloadCsv(
                                        `fees-${className}-${session}.csv`,
                                        [
                                            {
                                                label: "Name",
                                                value: (r) => r.name,
                                            },
                                            {
                                                label: "Admission no.",
                                                value: (r) => r.adm_no,
                                            },
                                            {
                                                label: "Section",
                                                value: (r) => r.section,
                                            },
                                            {
                                                label: "Expected",
                                                value: (r) => r.amount,
                                            },
                                            {
                                                label: "Paid",
                                                value: (r) => r.paid,
                                            },
                                            {
                                                label: "Balance",
                                                value: (r) => r.balance,
                                            },
                                            {
                                                label: "Optional fees",
                                                value: (r) => r.optional.amount,
                                            },
                                            {
                                                label: "Optional paid",
                                                value: (r) => r.optional.paid,
                                            },
                                            {
                                                label: "Optional balance",
                                                value: (r) =>
                                                    r.optional.balance,
                                            },
                                        ],
                                        rows,
                                    )
                                }
                            >
                                <Download />
                                Export
                            </Button>
                        }
                    >
                        <div className="flex flex-wrap items-center gap-2 border-b border-border px-3 py-2.5">
                            <div className="relative w-full sm:w-64">
                                <Search className="pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-fg-subtle" />
                                <input
                                    value={query}
                                    onChange={(e) => setQuery(e.target.value)}
                                    placeholder="Search this class…"
                                    className="h-8 w-full rounded-md border border-border bg-muted pl-8 pr-3 text-sm placeholder:text-fg-subtle focus:border-primary focus:bg-surface focus:shadow-focus focus:outline-none"
                                />
                            </div>
                            <Segmented
                                size="sm"
                                value={status}
                                onChange={setStatus}
                                options={[
                                    {
                                        value: "all",
                                        label: `All ${students.length}`,
                                    },
                                    {
                                        value: "owing",
                                        label: `Owing ${counts.owing}`,
                                    },
                                    {
                                        value: "cleared",
                                        label: `Paid ${counts.cleared}`,
                                    },
                                ]}
                            />
                        </div>
                        {rows.length ? (
                            <>
                                <div className="scrollbar-thin overflow-x-auto">
                                    <table className="w-full text-left">
                                        <thead>
                                            <tr className="border-b border-border bg-canvas text-2xs font-semibold uppercase text-fg-muted">
                                                <th className="h-9 px-4">
                                                    Student
                                                </th>
                                                <th className="hidden h-9 px-3 md:table-cell">
                                                    Section
                                                </th>
                                                <th className="h-9 px-3 text-right">
                                                    Expected
                                                </th>
                                                <th className="h-9 px-3 text-right">
                                                    Paid
                                                </th>
                                                <th className="h-9 px-3 text-right">
                                                    Balance
                                                </th>
                                                <th className="hidden h-9 px-3 text-right md:table-cell">
                                                    Optional due
                                                </th>
                                                <th className="hidden h-9 w-40 px-3 lg:table-cell">
                                                    Progress
                                                </th>
                                                <th className="h-9 px-4" />
                                            </tr>
                                        </thead>
                                        <tbody className="tabular">
                                            {shown.map((s) => (
                                                <tr
                                                    key={s.id}
                                                    className="border-b border-border last:border-0 hover:bg-muted"
                                                >
                                                    <td className="h-12 px-4">
                                                        <div className="flex items-center gap-3">
                                                            <Avatar
                                                                src={s.photo}
                                                                name={s.name}
                                                            />
                                                            <div className="min-w-0">
                                                                <Link
                                                                    href={
                                                                        s.invoice_url
                                                                    }
                                                                    className="block truncate font-medium hover:text-primary"
                                                                >
                                                                    {s.name}
                                                                </Link>
                                                                <div className="text-xs text-fg-muted">
                                                                    {s.adm_no}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td className="hidden px-3 text-sm text-fg-muted md:table-cell">
                                                        {s.section}
                                                    </td>
                                                    <td className="px-3 text-right">
                                                        {s.items ? (
                                                            formatMoney(
                                                                s.amount,
                                                            )
                                                        ) : (
                                                            <span className="text-fg-subtle">
                                                                No fees
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="px-3 text-right text-success-fg">
                                                        {formatMoney(s.paid)}
                                                    </td>
                                                    <td
                                                        className={cn(
                                                            "px-3 text-right font-medium",
                                                            s.balance > 0 &&
                                                                "text-danger-fg",
                                                        )}
                                                    >
                                                        {formatMoney(s.balance)}
                                                    </td>
                                                    <td
                                                        className={cn(
                                                            "hidden px-3 text-right md:table-cell",
                                                            s.optional.balance >
                                                                0
                                                                ? "text-danger-fg"
                                                                : "text-fg-subtle",
                                                        )}
                                                    >
                                                        {s.optional.amount
                                                            ? formatMoney(
                                                                  s.optional
                                                                      .balance,
                                                              )
                                                            : "—"}
                                                    </td>
                                                    <td className="hidden px-3 lg:table-cell">
                                                        <Meter
                                                            value={s.paid}
                                                            max={s.amount}
                                                        />
                                                    </td>
                                                    <td className="px-4 text-right">
                                                        <Button
                                                            size="xs"
                                                            asChild
                                                        >
                                                            <Link
                                                                href={
                                                                    s.invoice_url
                                                                }
                                                            >
                                                                {s.balance > 0
                                                                    ? "Record payment"
                                                                    : "View"}
                                                                <ArrowRight />
                                                            </Link>
                                                        </Button>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                                {pager}
                            </>
                        ) : (
                            <EmptyState compact title="No students match" />
                        )}
                    </Panel>
                </div>
            )}
        </>
    );
}

PaymentsManage.layout = withAppLayout;

function Figure({ label, value, note, tone }) {
    return (
        <div className="px-4 py-3.5">
            <div className="overline-label truncate">{label}</div>
            <div
                className={cn(
                    "tabular mt-1.5 text-xl font-semibold",
                    tone === "danger" && "text-danger-fg",
                    tone === "success" && "text-success-fg",
                )}
            >
                {value}
            </div>
        </div>
    );
}

/** Email + SMS this term's invoice (with any balance brought forward) to every parent in the class. */
function SendClassInvoices({ url, className, count }) {
    const [open, setOpen] = useState(false);
    const [busy, setBusy] = useState(false);
    const send = async () => {
        setBusy(true);
        const r = await submitForm(url, {});
        setBusy(false);
        setOpen(false);
        r.ok ? toast.success(r.message) : toast.error(r.message);
    };
    return (
        <>
            <Button className="ml-auto" onClick={() => setOpen(true)}>
                <Send />
                Send invoices to class
            </Button>
            <ConfirmDialog
                open={open}
                onOpenChange={(o) => !busy && setOpen(o)}
                title={`Send invoices to ${className} parents?`}
                description={`Each of the ${count} students' parents gets this term's invoice by email and SMS, including any balance brought forward from earlier terms.`}
                confirmLabel="Send invoices"
                tone="primary"
                loading={busy}
                onConfirm={send}
            />
        </>
    );
}
