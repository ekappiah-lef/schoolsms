import { useMemo, useState } from "react";
import { Head, Link, router } from "@inertiajs/react";
import { toast } from "sonner";
import { Banknote, Copy, MoreHorizontal, Pencil, Plus, Trash2 } from "lucide-react";
import { usePaged } from "@/components/app/data-table";
import { withAppLayout } from "@/layouts/AppLayout";
import { EmptyState, PageHeader, Panel } from "@/components/app/page";
import { Meter } from "@/components/app/charts";
import { useConfirmAction } from "@/components/app/confirm-action";
import { ConfirmDialog } from "@/components/ui/dialog";
import { submitForm } from "@/lib/http";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Select } from "@/components/ui/select";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { formatDate, formatMoney } from "@/lib/utils";

const TERMS = { 1: "1st term", 2: "2nd term", 3: "3rd term" };

export default function PaymentsIndex({
    year,
    session,
    currentTerm,
    approval,
    years,
    payments,
    categories,
    urls,
}) {
    const [confirm, confirmDialog] = useConfirmAction();
    const [classFilter, setClassFilter] = useState("");
    const [term, setTerm] = useState(
        () => new URLSearchParams(window.location.search).get("term") ?? String(currentTerm),
    );
    const [copying, setCopying] = useState(false);
    const [copyBusy, setCopyBusy] = useState(false);
    const start = Number(String(year).slice(0, 4));
    const nextLabel = term === "3" ? `${TERMS[1]} ${start + 1}-${start + 2}` : `${TERMS[Number(term) + 1]} ${year}`;
    const toCopy = payments.filter((p) => String(p.term) === term && p.category !== "opening").length;
    const approvable = approval && approval.year === year && String(approval.term) === term;
    const [approving, setApproving] = useState(false);
    const [approveBusy, setApproveBusy] = useState(false);
    const approve = async (yes) => {
        setApproveBusy(true);
        const r = await submitForm(urls.approve, { approve: yes ? 1 : 0 });
        setApproveBusy(false);
        setApproving(false);
        if (!r.ok) return toast.error(r.message);
        toast.success(r.message);
        router.reload({ preserveState: true });
    };
    const copy = async () => {
        setCopyBusy(true);
        const r = await submitForm(urls.copyTerm, { year, term });
        setCopyBusy(false);
        setCopying(false);
        if (!r.ok) return toast.error(r.message);
        toast.success(r.message);
        router.visit(`${urls.year.replace(":year", r.data.year)}?term=${r.data.term}`);
    };

    const classes = useMemo(() => {
        const seen = new Map();
        payments.forEach((p) =>
            seen.set(
                p.class_id ? String(p.class_id) : "general",
                p.class ?? "All classes",
            ),
        );
        return [...seen.entries()].map(([value, label]) => ({ value, label }));
    }, [payments]);

    const rows = payments.filter(
        (p) =>
            (!term || !p.term || String(p.term) === term) &&
            (!classFilter ||
                (classFilter === "general"
                    ? !p.class_id
                    : String(p.class_id) === classFilter)),
    );
    const { shown, pager } = usePaged(rows, 10, "fees");

    return (
        <>
            <Head title="Fee setup" />
            {approvable && (
                <ConfirmDialog
                    open={approving}
                    onOpenChange={(o) => !approveBusy && setApproving(o)}
                    title={`Approve ${TERMS[term]} fees?`}
                    description={`Bills go out on ${formatDate(approval.date, "d MMM yyyy")}.`}
                    confirmLabel="Approve"
                    tone="primary"
                    loading={approveBusy}
                    onConfirm={() => approve(true)}
                />
            )}
            <ConfirmDialog
                open={copying}
                onOpenChange={(o) => !copyBusy && setCopying(o)}
                title={`Copy ${toCopy} fees to ${nextLabel}?`}
                confirmLabel="Copy"
                tone="primary"
                loading={copyBusy}
                onConfirm={copy}
            />
            <PageHeader
                breadcrumbs={[{ label: "Finance" }, { label: "Fee setup" }]}
                title="Fee setup"
                actions={
                    <>
                        <Button asChild>
                            <Link href={urls.config}>
                                Finance configuration
                            </Link>
                        </Button>
                        <Button variant="primary" asChild>
                            <Link href={urls.create}>
                                <Plus />
                                New fee
                            </Link>
                        </Button>
                    </>
                }
            />

            <Panel
                flush
                title={year ? `Fees for ${year}${term ? ` · ${TERMS[term]}` : ""}` : "Fees"}
                actions={
                    <>
                        {approvable && (approval.sent ? (
                            <Badge>Bills sent</Badge>
                        ) : approval.approved ? (
                            <>
                                <Badge>Approved · {formatDate(approval.date, "d MMM yyyy")}</Badge>
                                <Button size="sm" loading={approveBusy} onClick={() => approve(false)}>
                                    Cancel approval
                                </Button>
                            </>
                        ) : (
                            toCopy > 0 && (
                                <Button size="sm" variant="primary" onClick={() => setApproving(true)}>
                                    Approve
                                </Button>
                            )
                        ))}
                        {term && toCopy > 0 && (
                            <Button size="sm" onClick={() => setCopying(true)}>
                                <Copy />
                                Copy to next term
                            </Button>
                        )}
                        {classes.length > 1 && (
                            <Select
                                size="sm"
                                className="w-44"
                                value={classFilter}
                                onChange={setClassFilter}
                                options={classes}
                                clearable
                                clearLabel="All fees"
                                placeholder="All fees"
                            />
                        )}
                        <Select
                            size="sm"
                            className="w-32"
                            value={term}
                            onChange={(v) => setTerm(v ?? "")}
                            options={Object.entries(TERMS).map(([value, label]) => ({ value, label }))}
                            clearable
                            clearLabel="All terms"
                            placeholder="All terms"
                        />
                        {years.length > 0 && (
                            <Select
                                size="sm"
                                className="w-36"
                                value={year}
                                onChange={(v) =>
                                    router.visit(urls.year.replace(":year", v))
                                }
                                options={years.map((y) => ({
                                    value: y,
                                    label: y,
                                }))}
                            />
                        )}
                    </>
                }
            >
                {rows.length ? (
                    <>
                        <div className="scrollbar-thin overflow-x-auto">
                            <table className="w-full text-left">
                                <thead>
                                    <tr className="border-b border-border bg-canvas text-2xs font-semibold uppercase text-fg-muted">
                                        <th className="h-9 px-4">Fee</th>
                                        <th className="h-9 px-3">Term</th>
                                        <th className="h-9 px-3">Applies to</th>
                                        <th className="h-9 px-3 text-right">
                                            Amount
                                        </th>
                                        <th className="hidden h-9 px-3 md:table-cell">
                                            Reference
                                        </th>
                                        <th className="hidden h-9 w-48 px-3 lg:table-cell">
                                            Collected
                                        </th>
                                        <th className="h-9 w-12 px-4" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {shown.map((p) => (
                                        <tr
                                            key={p.id}
                                            className="border-b border-border last:border-0 hover:bg-muted"
                                        >
                                            <td className="px-4 py-3">
                                                <div className="font-medium">
                                                    {p.title}
                                                </div>
                                                <div className="max-w-md truncate text-xs text-fg-muted">
                                                    {[
                                                        p.items.length
                                                            ? `${p.items.length} items: ${p.items.map((i) => i.name).join(", ")}`
                                                            : null,
                                                        p.description,
                                                    ]
                                                        .filter(Boolean)
                                                        .join(" · ")}
                                                </div>
                                            </td>
                                            <td className="whitespace-nowrap px-3 text-sm">
                                                {TERMS[p.term] ?? "All terms"}
                                            </td>
                                            <td className="px-3">
                                                <div className="flex flex-wrap gap-1">
                                                    {p.class ? (
                                                        <Badge
                                                            tone="outline"
                                                            shape="tag"
                                                        >
                                                            {p.class}
                                                        </Badge>
                                                    ) : (
                                                        <Badge
                                                            tone="primary"
                                                            shape="tag"
                                                        >
                                                            All classes
                                                        </Badge>
                                                    )}
                                                    {p.category !== "all" && (
                                                        <Badge
                                                            tone={
                                                                p.category ===
                                                                "new"
                                                                    ? "info"
                                                                    : "neutral"
                                                            }
                                                            shape="tag"
                                                        >
                                                            {
                                                                categories[
                                                                    p.category
                                                                ]
                                                            }
                                                        </Badge>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="tabular px-3 text-right font-medium">
                                                {formatMoney(p.amount)}
                                            </td>
                                            <td className="tabular hidden px-3 text-sm text-fg-muted md:table-cell">
                                                {p.ref_no}
                                            </td>
                                            <td className="hidden px-3 lg:table-cell">
                                                {p.records ? (
                                                    <div>
                                                        <Meter
                                                            value={p.collected}
                                                            max={p.expected}
                                                        />
                                                        <div className="tabular mt-1 text-xs text-fg-muted">
                                                            {formatMoney(
                                                                p.collected,
                                                            )}{" "}
                                                            of{" "}
                                                            {formatMoney(
                                                                p.expected,
                                                            )}{" "}
                                                            · {p.records}{" "}
                                                            students
                                                        </div>
                                                    </div>
                                                ) : (
                                                    <span className="text-xs text-fg-subtle">
                                                        No student records yet
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-4 text-right">
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger
                                                        asChild
                                                    >
                                                        <Button
                                                            variant="ghost"
                                                            size="icon-sm"
                                                            aria-label={`Actions for ${p.title}`}
                                                        >
                                                            <MoreHorizontal />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent>
                                                        <DropdownMenuItem
                                                            asChild
                                                        >
                                                            <Link
                                                                href={
                                                                    p.urls.edit
                                                                }
                                                            >
                                                                <Pencil />
                                                                Edit
                                                            </Link>
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem asChild>
                                                            <Link href={p.urls.duplicate}>
                                                                <Copy />
                                                                Duplicate
                                                            </Link>
                                                        </DropdownMenuItem>
                                                        <DropdownMenuSeparator />
                                                        <DropdownMenuItem
                                                            destructive
                                                            onSelect={() =>
                                                                confirm({
                                                                    title: `Delete "${p.title}"?`,
                                                                    description:
                                                                        p.records
                                                                            ? `This also deletes ${p.records} student payment records and all their receipts (${formatMoney(p.collected)} collected). This cannot be undone.`
                                                                            : "This removes the fee definition. This cannot be undone.",
                                                                    confirmLabel:
                                                                        "Delete fee",
                                                                    method: "delete",
                                                                    url: p.urls
                                                                        .destroy,
                                                                })
                                                            }
                                                        >
                                                            <Trash2 />
                                                            Delete
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        {pager}
                    </>
                ) : (
                    <EmptyState
                        icon={Banknote}
                        title="No fees set up"
                        action={
                            <Button size="sm" variant="primary" asChild>
                                <Link href={urls.create}>New fee</Link>
                            </Button>
                        }
                    />
                )}
            </Panel>
            {confirmDialog}
        </>
    );
}

PaymentsIndex.layout = withAppLayout;
