import { useEffect, useMemo, useRef, useState } from "react";
import {
    flexRender,
    getCoreRowModel,
    useReactTable,
} from "@tanstack/react-table";
import {
    ArrowDown,
    ArrowUp,
    ChevronLeft,
    ChevronRight,
    ChevronsUpDown,
    Columns3,
    Search,
    X,
} from "lucide-react";
import { cn, formatNumber } from "@/lib/utils";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { Select } from "@/components/ui/select";
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";

const hideBelow = {
    sm: "hidden sm:table-cell",
    md: "hidden md:table-cell",
    lg: "hidden lg:table-cell",
    xl: "hidden xl:table-cell",
};

function readVisibility(key) {
    try {
        return JSON.parse(window.localStorage.getItem(key) || "{}");
    } catch {
        return {};
    }
}

/**
 * Server-driven data table.
 *
 * - Sorting and pagination are handled by Laravel (`sort`, `dir`, `onSortChange`).
 * - Column visibility is remembered per table in localStorage.
 * - `selectable` adds a checkbox column; `bulkActions(selectedRows, clear)` renders the action bar.
 * - column.meta: { hideBelow: 'md' | 'lg', className, headerClassName, sortKey, label }
 */
export function DataTable({
    id,
    columns,
    data,
    getRowId = (row) => row.id,
    sort,
    dir,
    onSortChange,
    selectable = false,
    bulkActions,
    onRowClick,
    loading = false,
    empty,
    toolbar,
    footer,
}) {
    const storageKey = `table:${id}:columns`;
    const [columnVisibility, setColumnVisibility] = useState(() =>
        id ? readVisibility(storageKey) : {},
    );
    const [rowSelection, setRowSelection] = useState({});

    useEffect(() => {
        if (!id) return;
        try {
            window.localStorage.setItem(
                storageKey,
                JSON.stringify(columnVisibility),
            );
        } catch {
            /* storage unavailable — visibility just won't persist */
        }
    }, [columnVisibility, id, storageKey]);

    // Selection belongs to the current page of results.
    useEffect(() => setRowSelection({}), [data]);

    const allColumns = useMemo(() => {
        if (!selectable) return columns;
        return [
            {
                id: "__select",
                enableHiding: false,
                header: ({ table }) => (
                    <Checkbox
                        aria-label="Select all rows on this page"
                        checked={
                            table.getIsAllRowsSelected()
                                ? true
                                : table.getIsSomeRowsSelected()
                                  ? "indeterminate"
                                  : false
                        }
                        onCheckedChange={(v) =>
                            table.toggleAllRowsSelected(!!v)
                        }
                    />
                ),
                cell: ({ row }) => (
                    <Checkbox
                        aria-label="Select row"
                        checked={row.getIsSelected()}
                        onCheckedChange={(v) => row.toggleSelected(!!v)}
                        onClick={(e) => e.stopPropagation()}
                    />
                ),
                meta: { className: "w-10 pr-0", headerClassName: "w-10 pr-0" },
            },
            ...columns,
        ];
    }, [columns, selectable]);

    const table = useReactTable({
        data,
        columns: allColumns,
        getRowId,
        state: { columnVisibility, rowSelection },
        onColumnVisibilityChange: setColumnVisibility,
        onRowSelectionChange: setRowSelection,
        enableRowSelection: selectable,
        getCoreRowModel: getCoreRowModel(),
        manualSorting: true,
        manualPagination: true,
    });

    const selected = table.getSelectedRowModel().rows.map((r) => r.original);
    const hideable = table
        .getAllLeafColumns()
        .filter((c) => c.getCanHide() && c.columnDef.meta?.label);

    const toggleSort = (key) => {
        if (!onSortChange || !key) return;
        onSortChange(key, sort === key && dir === "asc" ? "desc" : "asc");
    };

    return (
        <div className="panel relative flex min-w-0 flex-col">
            {(toolbar || hideable.length > 0) && (
                <div className="flex flex-wrap items-center gap-2 border-b border-border px-3 py-2.5">
                    <div className="flex min-w-0 flex-1 flex-wrap items-center gap-2">
                        {toolbar}
                    </div>
                    {hideable.length > 0 && (
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    aria-label="Choose columns"
                                >
                                    <Columns3 />
                                    <span className="hidden sm:inline">
                                        Columns
                                    </span>
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent>
                                <DropdownMenuLabel>
                                    Visible columns
                                </DropdownMenuLabel>
                                {hideable.map((col) => (
                                    <DropdownMenuCheckboxItem
                                        key={col.id}
                                        checked={col.getIsVisible()}
                                        onCheckedChange={(v) =>
                                            col.toggleVisibility(!!v)
                                        }
                                        onSelect={(e) => e.preventDefault()}
                                    >
                                        {col.columnDef.meta.label}
                                    </DropdownMenuCheckboxItem>
                                ))}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    )}
                </div>
            )}

            {selectable && selected.length > 0 && (
                <div className="flex flex-wrap items-center gap-2 border-b border-primary/20 bg-primary-soft px-3 py-2 text-sm">
                    <span className="font-medium text-primary-hover">
                        {selected.length} selected
                    </span>
                    <div className="flex flex-wrap items-center gap-2">
                        {bulkActions?.(selected, () => setRowSelection({}))}
                    </div>
                    <Button
                        size="xs"
                        variant="ghost"
                        className="ml-auto"
                        onClick={() => setRowSelection({})}
                    >
                        <X />
                        Clear
                    </Button>
                </div>
            )}

            <div className="scrollbar-thin relative min-w-0 overflow-x-auto">
                {loading && (
                    <div className="absolute inset-x-0 top-0 z-10 h-0.5 overflow-hidden bg-primary/10">
                        <div className="h-full w-1/3 animate-progress bg-primary" />
                    </div>
                )}
                <table className="w-full border-collapse text-left">
                    <thead>
                        {table.getHeaderGroups().map((hg) => (
                            <tr
                                key={hg.id}
                                className="border-b border-border bg-canvas"
                            >
                                {hg.headers.map((header) => {
                                    const meta =
                                        header.column.columnDef.meta ?? {};
                                    const sortKey = meta.sortKey;
                                    const active = sortKey && sort === sortKey;
                                    return (
                                        <th
                                            key={header.id}
                                            scope="col"
                                            aria-sort={
                                                active
                                                    ? dir === "asc"
                                                        ? "ascending"
                                                        : "descending"
                                                    : undefined
                                            }
                                            className={cn(
                                                "h-9 whitespace-nowrap px-3 text-2xs font-semibold uppercase text-fg-muted first:pl-4 last:pr-4",
                                                meta.hideBelow &&
                                                    hideBelow[meta.hideBelow],
                                                meta.headerClassName,
                                            )}
                                        >
                                            {header.isPlaceholder ? null : sortKey ? (
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        toggleSort(sortKey)
                                                    }
                                                    className={cn(
                                                        "focus-ring -mx-1 inline-flex items-center gap-1 rounded px-1 uppercase hover:text-fg",
                                                        active && "text-fg",
                                                    )}
                                                >
                                                    {flexRender(
                                                        header.column.columnDef
                                                            .header,
                                                        header.getContext(),
                                                    )}
                                                    {active ? (
                                                        dir === "asc" ? (
                                                            <ArrowUp className="size-3" />
                                                        ) : (
                                                            <ArrowDown className="size-3" />
                                                        )
                                                    ) : (
                                                        <ChevronsUpDown className="size-3 opacity-50" />
                                                    )}
                                                </button>
                                            ) : (
                                                flexRender(
                                                    header.column.columnDef
                                                        .header,
                                                    header.getContext(),
                                                )
                                            )}
                                        </th>
                                    );
                                })}
                            </tr>
                        ))}
                    </thead>
                    <tbody
                        className={cn(
                            "transition-opacity",
                            loading && "opacity-60",
                        )}
                    >
                        {table.getRowModel().rows.map((row) => (
                            <tr
                                key={row.id}
                                data-state={
                                    row.getIsSelected() ? "selected" : undefined
                                }
                                onClick={
                                    onRowClick
                                        ? (e) => onRowClick(row.original, e)
                                        : undefined
                                }
                                className={cn(
                                    "border-b border-border transition-colors last:border-b-0 hover:bg-muted data-[state=selected]:bg-primary-soft/50",
                                    onRowClick && "cursor-pointer",
                                )}
                            >
                                {row.getVisibleCells().map((cell) => {
                                    const meta =
                                        cell.column.columnDef.meta ?? {};
                                    return (
                                        <td
                                            key={cell.id}
                                            className={cn(
                                                "h-12 px-3 align-middle text-base first:pl-4 last:pr-4",
                                                meta.hideBelow &&
                                                    hideBelow[meta.hideBelow],
                                                meta.className,
                                            )}
                                        >
                                            {flexRender(
                                                cell.column.columnDef.cell,
                                                cell.getContext(),
                                            )}
                                        </td>
                                    );
                                })}
                            </tr>
                        ))}
                    </tbody>
                </table>
                {data.length === 0 && !loading && empty}
            </div>
            {footer}
        </div>
    );
}

/** "Showing 1–25 of 77" + page size + pager, for Laravel paginator meta. */
export function Pagination({
    meta,
    onPageChange,
    onPerPageChange,
    noun = "results",
}) {
    if (!meta || meta.total === 0) return null;
    const {
        current_page: page,
        last_page: last,
        from,
        to,
        total,
        per_page: perPage,
    } = meta;

    const pages = [];
    for (let p = 1; p <= last; p++) {
        if (p === 1 || p === last || Math.abs(p - page) <= 1) pages.push(p);
        else if (pages[pages.length - 1] !== "…") pages.push("…");
    }

    return (
        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border px-4 py-2.5 text-sm text-fg-muted">
            <div className="flex items-center gap-3">
                <span className="tabular">
                    Showing <span className="font-medium text-fg">{from}</span>–
                    <span className="font-medium text-fg">{to}</span> of{" "}
                    <span className="font-medium text-fg">
                        {formatNumber(total)}
                    </span>{" "}
                    {noun}
                </span>
                {onPerPageChange && (
                    <div className="hidden items-center gap-2 sm:flex">
                        <span className="text-fg-subtle">·</span>
                        <Select
                            size="sm"
                            className="h-7 w-[72px]"
                            value={perPage}
                            onChange={(v) => onPerPageChange(Number(v))}
                            options={[10, 25, 50, 100].map((n) => ({
                                value: n,
                                label: String(n),
                            }))}
                        />
                        <span>per page</span>
                    </div>
                )}
            </div>
            {last > 1 && (
                <nav
                    className="flex items-center gap-1"
                    aria-label="Pagination"
                >
                    <Button
                        size="icon-sm"
                        variant="ghost"
                        disabled={page <= 1}
                        onClick={() => onPageChange(page - 1)}
                        aria-label="Previous page"
                    >
                        <ChevronLeft />
                    </Button>
                    {pages.map((p, i) =>
                        p === "…" ? (
                            <span
                                key={`gap-${i}`}
                                className="px-1 text-fg-subtle"
                            >
                                …
                            </span>
                        ) : (
                            <button
                                key={p}
                                type="button"
                                onClick={() => onPageChange(p)}
                                aria-current={p === page ? "page" : undefined}
                                className={cn(
                                    "focus-ring tabular h-7 min-w-7 rounded-md px-2 text-sm font-medium transition-colors",
                                    p === page
                                        ? "bg-primary text-white"
                                        : "text-fg-muted hover:bg-subtle hover:text-fg",
                                )}
                            >
                                {p}
                            </button>
                        ),
                    )}
                    <Button
                        size="icon-sm"
                        variant="ghost"
                        disabled={page >= last}
                        onClick={() => onPageChange(page + 1)}
                        aria-label="Next page"
                    >
                        <ChevronRight />
                    </Button>
                </nav>
            )}
        </div>
    );
}

/** Debounced search box. Press "/" anywhere to focus it. */
export function SearchInput({
    value,
    onChange,
    placeholder = "Search…",
    delay = 300,
    className,
}) {
    const [text, setText] = useState(value ?? "");
    const ref = useRef(null);
    const first = useRef(true);

    useEffect(() => setText(value ?? ""), [value]);

    useEffect(() => {
        if (first.current) {
            first.current = false;
            return undefined;
        }
        if (text === (value ?? "")) return undefined;
        const t = setTimeout(() => onChange(text), delay);
        return () => clearTimeout(t);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [text]);

    useEffect(() => {
        const onKey = (e) => {
            if (
                e.key === "/" &&
                !["INPUT", "TEXTAREA", "SELECT"].includes(
                    document.activeElement?.tagName,
                )
            ) {
                e.preventDefault();
                ref.current?.focus();
            }
        };
        window.addEventListener("keydown", onKey);
        return () => window.removeEventListener("keydown", onKey);
    }, []);

    return (
        <div className={cn("relative w-full sm:w-72", className)}>
            <Search className="pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-fg-subtle" />
            <input
                ref={ref}
                type="search"
                value={text}
                onChange={(e) => setText(e.target.value)}
                onKeyDown={(e) => {
                    if (e.key === "Enter") onChange(text);
                    if (e.key === "Escape" && text) {
                        setText("");
                        onChange("");
                    }
                }}
                placeholder={placeholder}
                className="h-8 w-full rounded-md border border-border bg-muted pl-8 pr-8 text-sm text-fg placeholder:text-fg-subtle transition-colors hover:border-border-strong focus:border-primary focus:bg-surface focus:shadow-focus focus:outline-none [&::-webkit-search-cancel-button]:hidden"
            />
            {text ? (
                <button
                    type="button"
                    onClick={() => {
                        setText("");
                        onChange("");
                    }}
                    className="absolute right-2 top-1/2 -translate-y-1/2 rounded p-0.5 text-fg-subtle hover:bg-subtle hover:text-fg"
                    aria-label="Clear search"
                >
                    <X className="size-3.5" />
                </button>
            ) : (
                <kbd className="pointer-events-none absolute right-2 top-1/2 hidden h-[18px] -translate-y-1/2 items-center rounded border border-border-strong bg-surface px-1.5 text-[10px] font-medium text-fg-subtle shadow-[0_1px_0_rgb(var(--border-strong))] sm:inline-flex">
                    /
                </kbd>
            )}
        </div>
    );
}

/**
 * Client-side paging for lists already on the page: 10 rows per page by default.
 * Returns the rows to show and a <Pagination> to put under the table (hidden when
 * everything fits on one page). Goes back to page 1 when the list changes.
 */
export function usePaged(rows, initialPerPage = 10, noun = "records") {
    const [page, setPage] = useState(1);
    const [perPage, setPerPage] = useState(initialPerPage);
    const total = rows.length;
    const last = Math.max(1, Math.ceil(total / perPage));
    // Back to page 1 when filters change the list (not on every render: callers may pass a fresh array).
    useEffect(() => setPage(1), [total]);
    const current = Math.min(page, last);
    const shown = rows.slice((current - 1) * perPage, current * perPage);
    const pager =
        total > 10 ? (
            <Pagination
                meta={{
                    current_page: current,
                    last_page: last,
                    from: (current - 1) * perPage + 1,
                    to: Math.min(current * perPage, total),
                    total,
                    per_page: perPage,
                }}
                onPageChange={setPage}
                onPerPageChange={(n) => {
                    setPerPage(n);
                    setPage(1);
                }}
                noun={noun}
            />
        ) : null;
    return { shown, pager, offset: (current - 1) * perPage };
}
