import { useMemo, useState } from "react";
import { Head, Link, router } from "@inertiajs/react";
import { Banknote, MoreHorizontal, Pencil, Plus, Trash2 } from "lucide-react";
import { usePaged } from "@/components/app/data-table";
import { withAppLayout } from "@/layouts/AppLayout";
import { EmptyState, PageHeader, Panel } from "@/components/app/page";
import { Meter } from "@/components/app/charts";
import { useConfirmAction } from "@/components/app/confirm-action";
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
import { formatMoney } from "@/lib/utils";

export default function PaymentsIndex({
    year,
    session,
    years,
    payments,
    categories,
    urls,
}) {
    const [confirm, confirmDialog] = useConfirmAction();
    const [classFilter, setClassFilter] = useState("");

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
            !classFilter ||
            (classFilter === "general"
                ? !p.class_id
                : String(p.class_id) === classFilter),
    );
    const { shown, pager } = usePaged(rows, 10, "fees");

    return (
        <>
            <Head title="Fee setup" />
            <PageHeader
                breadcrumbs={[{ label: "Finance" }, { label: "Fee setup" }]}
                title="Fee setup"
                meta={
                    year && (
                        <Badge
                            tone={year === session ? "success" : "outline"}
                            dot={year === session}
                        >
                            {year}
                            {year === session ? " · current" : ""}
                        </Badge>
                    )
                }
                description="Fees charged per year. A fee without a class applies to every class."
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
                title={year ? `Fees for ${year}` : "Fees"}
                actions={
                    <>
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
                        description="Create the fees for this year, then open a class under Student payments to generate each student's records."
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
