import { useMemo, useState } from "react";
import { Head, Link, router } from "@inertiajs/react";
import { toast } from "sonner";
import { PackagePlus, Pencil, Plus, RotateCcw, Trash2 } from "lucide-react";
import { withAppLayout } from "@/layouts/AppLayout";
import { ModuleHeader, RowIconButton } from "@/components/app/module";
import { EmptyState, Panel } from "@/components/app/page";
import { usePaged } from "@/components/app/data-table";
import { useConfirmAction } from "@/components/app/confirm-action";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Modal } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Combobox, Select } from "@/components/ui/select";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { submitForm } from "@/lib/http";
import { cn, formatDate, formatMoney } from "@/lib/utils";

/** School shop: sell to students, manage items and stock, returns and stock history. */
export default function Sales({
    session,
    categories,
    items,
    students,
    sales,
    movements,
    totals,
    urls,
}) {
    const [tab, setTab] = useState(
        () => new URLSearchParams(window.location.search).get("tab") || "sell",
    );
    const [confirm, confirmDialog] = useConfirmAction();
    const salesPage = usePaged(sales, 10, "sales");
    const movesPage = usePaged(movements, 10, "entries");
    const reload = () => router.reload({ preserveScroll: true });
    const changeTab = (t) => {
        setTab(t);
        const url = new URL(window.location.href);
        url.searchParams.set("tab", t);
        window.history.replaceState(window.history.state, "", url);
    };

    return (
        <>
            <Head title="Sales & inventory" />
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6">
                <ModuleHeader
                    crumbs={["Finance", "Sales & inventory"]}
                    title="Sales & inventory"
                    session={session}
                />

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Tile
                        label="Sales this year"
                        value={formatMoney(totals.sales)}
                    />
                    <Tile
                        label="Paid"
                        value={formatMoney(totals.paid)}
                        tone="success"
                    />
                    <Tile
                        label="Still owed"
                        value={formatMoney(totals.sales - totals.paid)}
                        tone={
                            totals.sales - totals.paid > 0
                                ? "danger"
                                : undefined
                        }
                    />
                    <Tile
                        label="Stock value"
                        value={formatMoney(totals.stockValue)}
                    />
                </div>

                <Tabs value={tab} onValueChange={changeTab}>
                    <TabsList className="mb-6">
                        <TabsTrigger value="sell">Sell</TabsTrigger>
                        <TabsTrigger
                            value="items"
                            count={totals.lowStock || undefined}
                        >
                            Items &amp; stock
                        </TabsTrigger>
                        <TabsTrigger value="sales" count={sales.length}>
                            Recent sales
                        </TabsTrigger>
                        <TabsTrigger value="history">Stock history</TabsTrigger>
                    </TabsList>

                    <TabsContent value="sell">
                        <SellForm
                            items={items.filter((i) => i.active)}
                            students={students}
                            url={urls.sell}
                            onDone={reload}
                        />
                    </TabsContent>
                    <TabsContent value="items">
                        <ItemsTab
                            items={items}
                            categories={categories}
                            urls={urls}
                            onSaved={reload}
                        />
                    </TabsContent>
                    <TabsContent value="sales">
                        <Panel
                            title="Recent sales"
                            description="Unpaid sales can be returned: the item goes back into stock."
                            flush
                            actions={
                                <Button size="sm" asChild>
                                    <Link href={urls.roster}>
                                        All sales by student
                                    </Link>
                                </Button>
                            }
                        >
                            {sales.length ? (
                                <table className="w-full text-left text-sm">
                                    <thead>
                                        <tr className="border-b border-border bg-canvas text-2xs font-semibold uppercase text-fg-muted">
                                            <th className="h-9 px-4">Date</th>
                                            <th className="h-9 px-3">
                                                Student
                                            </th>
                                            <th className="h-9 px-3">Item</th>
                                            <th className="h-9 px-3 text-right">
                                                Amount
                                            </th>
                                            <th className="h-9 px-3 text-right">
                                                Paid
                                            </th>
                                            <th className="h-9 px-3 text-right">
                                                Balance
                                            </th>
                                            <th className="h-9 w-16 px-4" />
                                        </tr>
                                    </thead>
                                    <tbody className="tabular">
                                        {salesPage.shown.map((s) => (
                                            <tr
                                                key={s.id}
                                                className="border-b border-border last:border-0"
                                            >
                                                <td className="px-4 py-2.5 text-fg-muted">
                                                    {formatDate(
                                                        s.date,
                                                        "dd/MM/yyyy",
                                                    )}
                                                </td>
                                                <td className="px-3 py-2.5">
                                                    <Link
                                                        href={s.invoice_url}
                                                        className="font-medium hover:text-primary hover:underline"
                                                    >
                                                        {s.student}
                                                    </Link>
                                                </td>
                                                <td className="px-3 py-2.5">
                                                    {s.label}
                                                    {s.handed_down && (
                                                        <Badge
                                                            className="ml-2"
                                                            tone="info"
                                                            shape="tag"
                                                        >
                                                            Hand-down
                                                        </Badge>
                                                    )}
                                                </td>
                                                <td className="px-3 py-2.5 text-right">
                                                    {formatMoney(s.amount)}
                                                </td>
                                                <td className="px-3 py-2.5 text-right text-success-fg">
                                                    {formatMoney(s.paid)}
                                                </td>
                                                <td
                                                    className={cn(
                                                        "px-3 py-2.5 text-right font-medium",
                                                        s.balance > 0 &&
                                                            "text-danger-fg",
                                                    )}
                                                >
                                                    {formatMoney(s.balance)}
                                                </td>
                                                <td className="px-4 text-right">
                                                    {s.return_url && (
                                                        <RowIconButton
                                                            icon={RotateCcw}
                                                            title="Return"
                                                            tone="danger"
                                                            onClick={() =>
                                                                confirm({
                                                                    title: `Return ${s.label}?`,
                                                                    description: `It will be removed from ${s.student}’s account and put back into stock.`,
                                                                    confirmLabel:
                                                                        "Return item",
                                                                    method: "delete",
                                                                    url: s.return_url,
                                                                })
                                                            }
                                                        />
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            ) : (
                                <EmptyState compact title="No sales yet" />
                            )}
                            {salesPage.pager}
                        </Panel>
                    </TabsContent>
                    <TabsContent value="history">
                        <Panel
                            title="Stock history"
                            description="Every restock, sale, return and adjustment, newest first."
                            flush
                        >
                            {movements.length ? (
                                <table className="w-full text-left text-sm">
                                    <thead>
                                        <tr className="border-b border-border bg-canvas text-2xs font-semibold uppercase text-fg-muted">
                                            <th className="h-9 px-4">Date</th>
                                            <th className="h-9 px-3">Item</th>
                                            <th className="h-9 px-3">
                                                Movement
                                            </th>
                                            <th className="h-9 px-3 text-right">
                                                Quantity
                                            </th>
                                            <th className="h-9 px-3 text-right">
                                                Cost each
                                            </th>
                                            <th className="h-9 px-3">Note</th>
                                            <th className="h-9 px-4">By</th>
                                        </tr>
                                    </thead>
                                    <tbody className="tabular">
                                        {movesPage.shown.map((m, i) => (
                                            <tr
                                                key={i}
                                                className="border-b border-border last:border-0"
                                            >
                                                <td className="px-4 py-2.5 text-fg-muted">
                                                    {formatDate(
                                                        m.date,
                                                        "dd/MM/yyyy",
                                                    )}
                                                </td>
                                                <td className="px-3 py-2.5 font-medium">
                                                    {m.item}
                                                </td>
                                                <td className="px-3 py-2.5">
                                                    {m.type}
                                                </td>
                                                <td
                                                    className={cn(
                                                        "px-3 py-2.5 text-right font-semibold",
                                                        m.qty > 0
                                                            ? "text-success-fg"
                                                            : "text-danger-fg",
                                                    )}
                                                >
                                                    {m.qty > 0 ? "+" : "−"}
                                                    {Math.abs(m.qty)}
                                                </td>
                                                <td className="px-3 py-2.5 text-right text-fg-muted">
                                                    {m.unit_cost !== null
                                                        ? formatMoney(
                                                              m.unit_cost,
                                                          )
                                                        : "—"}
                                                </td>
                                                <td className="max-w-xs truncate px-3 py-2.5 text-fg-muted">
                                                    {m.note || "—"}
                                                </td>
                                                <td className="px-4 py-2.5 text-fg-muted">
                                                    {m.by || "—"}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            ) : (
                                <EmptyState
                                    compact
                                    title="No stock movements yet"
                                />
                            )}
                            {movesPage.pager}
                        </Panel>
                    </TabsContent>
                </Tabs>
            </div>
            {confirmDialog}
        </>
    );
}

Sales.layout = withAppLayout;

function Tile({ label, value, tone }) {
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

/* ------------------------------------------------------------------ */

function SellForm({ items, students, url, onDone }) {
    const blankLine = { item_id: "", qty: 1, handed_down: false };
    const [studentId, setStudentId] = useState("");
    const [lines, setLines] = useState([blankLine]);
    const [sibling, setSibling] = useState("");
    const [payNow, setPayNow] = useState("");
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState(null);

    const student = students.find((s) => s.id === studentId);
    const itemById = useMemo(
        () => Object.fromEntries(items.map((i) => [String(i.id), i])),
        [items],
    );
    const lineAmount = (l) =>
        l.handed_down || !itemById[l.item_id]
            ? 0
            : itemById[l.item_id].price * (Number(l.qty) || 0);
    const total = lines.reduce((t, l) => t + lineAmount(l), 0);
    const anyHandDown = lines.some((l) => l.handed_down);
    const setLine = (i, k, v) =>
        setLines((ls) => ls.map((l, j) => (j === i ? { ...l, [k]: v } : l)));

    const submit = async (e) => {
        e.preventDefault();
        setError(null);
        const valid = lines.filter((l) => l.item_id && Number(l.qty) > 0);
        if (!studentId) return setError("Choose the student.");
        if (!valid.length) return setError("Add at least one item.");
        if (anyHandDown && !sibling)
            return setError(
                "Choose the brother or sister the books are handed down from.",
            );
        setBusy(true);
        const r = await submitForm(url, {
            student_id: studentId,
            lines: JSON.stringify(
                valid.map((l) => ({
                    item_id: Number(l.item_id),
                    qty: Number(l.qty),
                    handed_down: l.handed_down,
                })),
            ),
            handed_down_from: anyHandDown ? sibling : "",
            pay_now: payNow || 0,
        });
        setBusy(false);
        if (r.ok) {
            toast.success(r.message);
            setLines([blankLine]);
            setPayNow("");
            setSibling("");
            onDone();
        } else {
            setError(r.errors ? Object.values(r.errors)[0] : r.message);
            toast.error(r.message);
        }
    };

    return (
        <form onSubmit={submit} className="grid gap-6 lg:grid-cols-[1fr_320px]">
            <div className="panel flex flex-col gap-5 p-6">
                <div className="flex flex-col gap-1.5">
                    <label className="text-sm font-medium">Student</label>
                    <Combobox
                        value={studentId}
                        onChange={(v) => {
                            setStudentId(v);
                            setSibling("");
                        }}
                        options={students.map((s) => ({
                            value: s.id,
                            label: s.name,
                            hint: s.hint,
                        }))}
                        placeholder="Search for the student"
                        searchPlaceholder="Name or admission number…"
                    />
                    {student && (
                        <p className="text-xs text-fg-muted">
                            {student.siblings.length
                                ? `Brothers and sisters: ${student.siblings.map((s) => s.name).join(", ")}`
                                : "No brothers or sisters at the school."}
                        </p>
                    )}
                </div>

                <div className="flex flex-col gap-2">
                    <span className="text-sm font-medium">Items</span>
                    {lines.map((l, i) => {
                        const item = itemById[l.item_id];
                        return (
                            <div
                                key={i}
                                className="flex flex-wrap items-center gap-2 rounded-lg p-2 shadow-field"
                            >
                                <div className="min-w-[220px] flex-1">
                                    <Select
                                        value={l.item_id}
                                        onChange={(v) =>
                                            setLine(i, "item_id", v)
                                        }
                                        placeholder="Choose item"
                                        options={items.map((it) => ({
                                            value: String(it.id),
                                            label: `${it.name} · ${formatMoney(it.price)} · ${it.stock} in stock`,
                                        }))}
                                    />
                                </div>
                                <Input
                                    className="tabular w-20"
                                    type="number"
                                    min={1}
                                    value={l.qty}
                                    onChange={(e) =>
                                        setLine(i, "qty", e.target.value)
                                    }
                                    aria-label="Quantity"
                                />
                                {student?.siblings.length > 0 && (
                                    <label className="flex items-center gap-1.5 text-sm">
                                        <Checkbox
                                            checked={l.handed_down}
                                            onCheckedChange={(v) =>
                                                setLine(
                                                    i,
                                                    "handed_down",
                                                    v === true,
                                                )
                                            }
                                        />
                                        Handed down
                                    </label>
                                )}
                                <span className="tabular w-24 text-right font-semibold">
                                    {l.handed_down
                                        ? "Free"
                                        : formatMoney(lineAmount(l))}
                                </span>
                                {lines.length > 1 && (
                                    <Button
                                        size="icon-sm"
                                        variant="ghost"
                                        aria-label="Remove line"
                                        onClick={() =>
                                            setLines((ls) =>
                                                ls.filter((_, j) => j !== i),
                                            )
                                        }
                                    >
                                        <Trash2 />
                                    </Button>
                                )}
                                {item &&
                                    !l.handed_down &&
                                    Number(l.qty) > item.stock && (
                                        <p className="w-full text-xs text-danger-fg">
                                            Only {item.stock} in stock.
                                        </p>
                                    )}
                            </div>
                        );
                    })}
                    <Button
                        size="sm"
                        variant="ghost"
                        className="self-start"
                        onClick={() => setLines((ls) => [...ls, blankLine])}
                    >
                        <Plus />
                        Add item
                    </Button>
                </div>

                {anyHandDown && (
                    <div className="flex flex-col gap-1.5">
                        <label className="text-sm font-medium">
                            Handed down from
                        </label>
                        <Select
                            value={sibling}
                            onChange={setSibling}
                            placeholder="Choose brother or sister"
                            options={(student?.siblings ?? []).map((s) => ({
                                value: s.id,
                                label: s.name,
                            }))}
                        />
                        <p className="text-xs text-fg-muted">
                            Recorded on the student’s account at no charge, and
                            no stock is used.
                        </p>
                    </div>
                )}
            </div>

            <div className="panel flex h-fit flex-col gap-4 p-6">
                <div className="flex items-baseline justify-between">
                    <span className="text-sm text-fg-muted">Total</span>
                    <span className="tabular text-2xl font-semibold">
                        {formatMoney(total)}
                    </span>
                </div>
                <div className="flex flex-col gap-1.5">
                    <label className="text-sm font-medium">
                        Paid now (optional)
                    </label>
                    <div className="relative">
                        <Input
                            className="tabular pr-14"
                            type="number"
                            min={0}
                            max={total}
                            value={payNow}
                            onChange={(e) => setPayNow(e.target.value)}
                            placeholder="0"
                        />
                        <button
                            type="button"
                            onClick={() => setPayNow(String(total))}
                            className="absolute right-2 top-1/2 -translate-y-1/2 text-2xs font-semibold uppercase text-primary"
                        >
                            Full
                        </button>
                    </div>
                    <p className="text-xs text-fg-muted">
                        Anything not paid now stays on the student’s optional
                        fees.
                    </p>
                </div>
                {error && <p className="text-sm text-danger-fg">{error}</p>}
                <Button type="submit" variant="primary" loading={busy}>
                    Record sale
                </Button>
            </div>
        </form>
    );
}

/* ------------------------------------------------------------------ */

function ItemsTab({ items, categories, urls, onSaved }) {
    const [edit, setEdit] = useState(null); // null | {} (new) | item
    const [restock, setRestock] = useState(null);
    const { shown, pager } = usePaged(items, 10, "items");

    return (
        <>
            <Panel
                title="Items & stock"
                description="Rows in red are at or below their reorder level."
                flush
                actions={
                    <Button
                        size="sm"
                        variant="primary"
                        onClick={() => setEdit({})}
                    >
                        <Plus />
                        New item
                    </Button>
                }
            >
                {items.length ? (
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b border-border bg-canvas text-2xs font-semibold uppercase text-fg-muted">
                                <th className="h-9 px-4">Item</th>
                                <th className="h-9 px-3">Category</th>
                                <th className="h-9 px-3 text-right">Price</th>
                                <th className="h-9 px-3 text-right">
                                    In stock
                                </th>
                                <th className="h-9 px-3 text-right">
                                    Reorder at
                                </th>
                                <th className="h-9 px-3 text-right">
                                    Sold this year
                                </th>
                                <th className="h-9 w-24 px-4" />
                            </tr>
                        </thead>
                        <tbody className="tabular">
                            {shown.map((i) => {
                                const low =
                                    i.active && i.stock <= i.reorder_level;
                                return (
                                    <tr
                                        key={i.id}
                                        className={cn(
                                            "border-b border-border last:border-0",
                                            !i.active && "opacity-60",
                                        )}
                                    >
                                        <td className="px-4 py-2.5 font-medium">
                                            {i.name}
                                            {!i.active && (
                                                <Badge
                                                    className="ml-2"
                                                    shape="tag"
                                                >
                                                    Inactive
                                                </Badge>
                                            )}
                                        </td>
                                        <td className="px-3 py-2.5 text-fg-muted">
                                            {i.category}
                                        </td>
                                        <td className="px-3 py-2.5 text-right">
                                            {formatMoney(i.price)}
                                        </td>
                                        <td
                                            className={cn(
                                                "px-3 py-2.5 text-right font-semibold",
                                                low && "text-danger-fg",
                                            )}
                                        >
                                            {i.stock}
                                        </td>
                                        <td className="px-3 py-2.5 text-right text-fg-muted">
                                            {i.reorder_level}
                                        </td>
                                        <td className="px-3 py-2.5 text-right">
                                            {i.sold}
                                        </td>
                                        <td className="px-4 text-right">
                                            <div className="flex justify-end gap-0.5">
                                                <RowIconButton
                                                    icon={PackagePlus}
                                                    title="Restock"
                                                    onClick={() =>
                                                        setRestock(i)
                                                    }
                                                />
                                                <RowIconButton
                                                    icon={Pencil}
                                                    title="Edit"
                                                    onClick={() => setEdit(i)}
                                                />
                                            </div>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                ) : (
                    <EmptyState
                        compact
                        title="No items yet"
                        description="Add the uniforms, books and stationery the school sells."
                    />
                )}
                {pager}
            </Panel>
            {edit && (
                <ItemDialog
                    item={edit}
                    categories={categories}
                    urls={urls}
                    onClose={() => setEdit(null)}
                    onSaved={onSaved}
                />
            )}
            {restock && (
                <RestockDialog
                    item={restock}
                    url={urls.restock.replace(":id", restock.id)}
                    onClose={() => setRestock(null)}
                    onSaved={onSaved}
                />
            )}
        </>
    );
}

function ItemDialog({ item, categories, urls, onClose, onSaved }) {
    const isNew = !item.id;
    const [d, setD] = useState({
        name: item.name ?? "",
        category: item.category ?? categories[0],
        price: item.price ?? "",
        reorder_level: item.reorder_level ?? 10,
        active: item.active ?? true,
    });
    const [errors, setErrors] = useState({});
    const [busy, setBusy] = useState(false);
    const save = async () => {
        setBusy(true);
        const payload = { ...d, active: d.active ? 1 : 0 };
        const r = isNew
            ? await submitForm(urls.items, payload)
            : await submitForm(urls.item.replace(":id", item.id), payload, {
                  method: "put",
              });
        setBusy(false);
        if (r.ok) {
            toast.success(isNew ? `${d.name} added` : `${d.name} updated`);
            onClose();
            onSaved();
        } else {
            setErrors(r.errors ?? {});
            toast.error(r.message);
        }
    };
    return (
        <Modal
            open
            onOpenChange={(o) => !o && onClose()}
            title={isNew ? "New item" : `Edit ${item.name}`}
            footer={
                <>
                    <Button variant="ghost" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button variant="primary" loading={busy} onClick={save}>
                        Save
                    </Button>
                </>
            }
        >
            <div className="grid gap-4 sm:grid-cols-2">
                <Labeled
                    label="Name"
                    error={errors.name}
                    className="sm:col-span-2"
                >
                    <Input
                        value={d.name}
                        onChange={(e) => setD({ ...d, name: e.target.value })}
                        placeholder="e.g. School sweater"
                    />
                </Labeled>
                <Labeled label="Category" error={errors.category}>
                    <Select
                        value={d.category}
                        onChange={(v) => setD({ ...d, category: v })}
                        options={categories.map((c) => ({
                            value: c,
                            label: c,
                        }))}
                    />
                </Labeled>
                <Labeled label="Price" error={errors.price}>
                    <Input
                        className="tabular"
                        type="number"
                        min={0}
                        value={d.price}
                        onChange={(e) => setD({ ...d, price: e.target.value })}
                    />
                </Labeled>
                <Labeled label="Reorder at" error={errors.reorder_level}>
                    <Input
                        className="tabular"
                        type="number"
                        min={0}
                        value={d.reorder_level}
                        onChange={(e) =>
                            setD({ ...d, reorder_level: e.target.value })
                        }
                    />
                </Labeled>
                <label className="flex items-center gap-2 self-end pb-2 text-sm">
                    <Checkbox
                        checked={d.active}
                        onCheckedChange={(v) =>
                            setD({ ...d, active: v === true })
                        }
                    />
                    On sale
                </label>
            </div>
            {isNew && (
                <p className="mt-3 text-xs text-fg-muted">
                    New items start with no stock. Use Restock to add what you
                    bought.
                </p>
            )}
        </Modal>
    );
}

function RestockDialog({ item, url, onClose, onSaved }) {
    const [d, setD] = useState({
        qty: "",
        unit_cost: "",
        date: formatDate(new Date(), "yyyy-MM-dd"),
        note: "",
        record_expense: true,
    });
    const [errors, setErrors] = useState({});
    const [busy, setBusy] = useState(false);
    const save = async () => {
        setBusy(true);
        const r = await submitForm(url, {
            ...d,
            record_expense: d.record_expense ? 1 : 0,
        });
        setBusy(false);
        if (r.ok) {
            toast.success(`${item.name}: ${d.qty} added to stock`);
            onClose();
            onSaved();
        } else {
            setErrors(r.errors ?? {});
            toast.error(r.message);
        }
    };
    return (
        <Modal
            open
            onOpenChange={(o) => !o && onClose()}
            title={`Restock ${item.name}`}
            description={`${item.stock} in stock now.`}
            footer={
                <>
                    <Button variant="ghost" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button variant="primary" loading={busy} onClick={save}>
                        Add to stock
                    </Button>
                </>
            }
        >
            <div className="grid gap-4 sm:grid-cols-2">
                <Labeled label="Quantity" error={errors.qty}>
                    <Input
                        className="tabular"
                        type="number"
                        min={1}
                        value={d.qty}
                        onChange={(e) => setD({ ...d, qty: e.target.value })}
                    />
                </Labeled>
                <Labeled label="Cost per item" error={errors.unit_cost}>
                    <Input
                        className="tabular"
                        type="number"
                        min={0}
                        value={d.unit_cost}
                        onChange={(e) =>
                            setD({ ...d, unit_cost: e.target.value })
                        }
                        placeholder="Optional"
                    />
                </Labeled>
                <Labeled label="Date" error={errors.date}>
                    <Input
                        type="date"
                        value={d.date}
                        onChange={(e) => setD({ ...d, date: e.target.value })}
                    />
                </Labeled>
                <Labeled label="Note" error={errors.note}>
                    <Input
                        value={d.note}
                        onChange={(e) => setD({ ...d, note: e.target.value })}
                        placeholder="Supplier, invoice no."
                    />
                </Labeled>
                <label className="flex items-center gap-2 text-sm sm:col-span-2">
                    <Checkbox
                        checked={d.record_expense}
                        onCheckedChange={(v) =>
                            setD({ ...d, record_expense: v === true })
                        }
                    />
                    Record the cost as an expense (Inventory purchases)
                    {d.qty && d.unit_cost ? (
                        <span className="tabular text-fg-muted">
                            · {formatMoney(Number(d.qty) * Number(d.unit_cost))}
                        </span>
                    ) : null}
                </label>
            </div>
        </Modal>
    );
}

function Labeled({ label, error, children, className }) {
    return (
        <div className={cn("flex flex-col gap-1.5", className)}>
            <label className="text-sm font-medium">{label}</label>
            {children}
            {error && <p className="text-xs text-danger-fg">{error}</p>}
        </div>
    );
}
