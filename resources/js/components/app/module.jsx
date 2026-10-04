import { Fragment, useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import { toast } from 'sonner';
import {
    ChevronLeft,
    ChevronRight,
    ChevronsUpDown,
    ArrowDown,
    ArrowUp,
    ChevronDown,
    ChevronUp,
    CirclePlus,
    Columns3,
    Copy,
    FileSpreadsheet,
    FileText,
    Info,
    LoaderCircle,
    RefreshCw,
    Rows3,
    Search,
    Send,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { downloadCsv } from '@/lib/use-visit-state';
import { DropdownMenu, DropdownMenuCheckboxItem, DropdownMenuContent, DropdownMenuLabel, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';

/*
 * Module page building blocks — implement the "Create X / Show Xs" pattern from
 * stitch_minimalist_logo_login_page/create_timetable_simple_minimal and
 * manage_timetables_simple_minimal. Used by timetables, classes, sections,
 * subjects, dormitories, exams, grades and fees.
 */

/** "ACADEMICS / SCHEDULE" eyebrow, large title, description and the session pill. */
export function ModuleHeader({ crumbs = [], title, description, session, aside }) {
    return (
        <div className="flex flex-col justify-between gap-3 md:flex-row md:items-end">
            <div className="flex flex-col gap-1">
                {crumbs.length > 0 && (
                    <div className="flex items-center gap-1 text-2xs font-semibold uppercase tracking-wider">
                        {crumbs.map((c, i) => (
                            <Fragment key={c}>
                                {i > 0 && <span className="text-border-strong">/</span>}
                                <span className={i === crumbs.length - 1 ? 'text-primary-hover' : 'text-fg-subtle'}>{c}</span>
                            </Fragment>
                        ))}
                    </div>
                )}
                <h1 className="text-[2rem] font-semibold leading-10 tracking-[-0.025em] text-fg">{title}</h1>
                {description && <p className="max-w-2xl text-base text-fg-muted">{description}</p>}
            </div>
            <div className="flex items-center gap-2">
                {aside}
                {session && (
                    <span className="inline-flex items-center gap-1.5 rounded-full bg-subtle px-3 py-1 text-2xs font-semibold tracking-wide text-fg">
                        {session.replace('-', ' – ')} Year
                    </span>
                )}
            </div>
        </div>
    );
}

/** Two-tab switcher: Create X / Show Xs (count). */
export function ModuleTabs({ value, onChange, createLabel, createText, listLabel, count, canCreate = true, editing }) {
    const tab = (key, icon, label, extra) => {
        const active = value === key;
        return (
            <button
                type="button"
                onClick={() => onChange(key)}
                className={cn(
                    'flex items-center gap-1.5 rounded-md px-5 py-1.5 text-sm font-medium transition-all duration-150 [&_svg]:size-[18px]',
                    active ? 'bg-surface font-semibold text-primary-hover shadow-sm' : 'text-fg-muted hover:bg-surface/60 hover:text-fg',
                )}
            >
                {icon}
                {label}
                {extra}
            </button>
        );
    };
    return (
        <div className="flex w-fit items-center gap-1 rounded-lg bg-muted p-0.5">
            {canCreate && tab('create', <CirclePlus />, editing ? `Edit ${createLabel}` : createText ?? `Create ${createLabel}`)}
            {tab(
                'list',
                <Rows3 />,
                listLabel,
                count !== undefined && (
                    <span className={cn('ml-0.5 rounded-full px-1.5 py-0.5 font-mono text-[10px] font-bold', value === 'list' ? 'bg-primary-soft text-primary-hover' : 'bg-subtle text-fg-muted')}>{count}</span>
                ),
            )}
        </div>
    );
}

/** Keeps the active tab in the URL (?tab=create) so it survives reloads and back/forward. */
export function useModuleTab(initial = 'list') {
    const [tab, setTab] = useState(() => new URLSearchParams(window.location.search).get('tab') || initial);
    const change = (next) => {
        setTab(next);
        const url = new URL(window.location.href);
        if (next === 'list') url.searchParams.delete('tab');
        else url.searchParams.set('tab', next);
        // Keep Inertia's own history entry in sync so redirects "back" land on the right tab.
        const state = window.history.state;
        window.history.replaceState(state?.page ? { ...state, page: { ...state.page, url: url.pathname + url.search } } : state, '', url);
    };
    return [tab, change];
}

/** The white "New X Setup" card with its Reset / Cancel / Create footer. */
export function SetupCard({ title, description, icon: Icon, onSubmit, onReset, onCancel, submitLabel, processing, children, footerNote }) {
    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                onSubmit?.();
            }}
            noValidate
            className="flex flex-col gap-8 rounded-lg bg-surface p-6 shadow-card sm:p-8 md:p-12"
        >
            <div className="flex items-start justify-between">
                <div>
                    <h2 className="text-lg font-semibold text-fg">{title}</h2>
                    {description && <p className="mt-0.5 text-xs text-fg-muted">{description}</p>}
                </div>
                {Icon && <Icon className="size-6 text-border-strong" strokeWidth={1.6} />}
            </div>
            <div className="grid grid-cols-1 gap-6 md:grid-cols-2">{children}</div>
            <div className="mt-2 flex flex-wrap items-center justify-between gap-3 pt-3">
                <div className="flex items-center gap-3">
                    {onReset && (
                        <button type="button" onClick={onReset} className="h-10 rounded-lg px-6 text-sm font-medium text-fg-muted transition-colors hover:bg-muted hover:text-fg">
                            Reset
                        </button>
                    )}
                    {footerNote}
                </div>
                <div className="flex items-center gap-2">
                    {onCancel && (
                        <button type="button" onClick={onCancel} className="h-10 rounded-lg bg-surface px-6 text-sm font-medium text-fg shadow-card transition-colors hover:bg-muted">
                            Cancel
                        </button>
                    )}
                    <button
                        type="submit"
                        disabled={processing}
                        className="flex h-10 items-center gap-1.5 rounded-lg bg-primary px-8 text-sm font-medium text-white shadow-btn transition-colors hover:bg-primary-hover disabled:opacity-70"
                    >
                        {processing ? <LoaderCircle className="size-[18px] animate-spin" /> : <Send className="size-[18px]" />}
                        {processing ? 'Saving…' : submitLabel}
                    </button>
                </div>
            </div>
        </form>
    );
}

/** Field label row: "Timetable Name *" with an optional hint on the right. */
export function Field({ label, required, aside, error, hint, span = 1, children }) {
    return (
        <div className={cn('flex min-w-0 flex-col gap-1.5', span === 2 && 'md:col-span-2')}>
            <label className="flex items-center justify-between text-sm font-medium text-fg">
                <span>
                    {label} {required && <span className="text-danger">*</span>}
                </span>
                {aside && <span className="text-xs font-normal text-fg-subtle">{aside}</span>}
            </label>
            {children}
            {error ? <p className="text-xs text-danger">{error}</p> : hint ? <p className="text-xs text-fg-muted">{hint}</p> : null}
        </div>
    );
}

/** Native select styled like the Stitch fields (grouped options supported). */
export function NativeSelect({ value, onChange, children, invalid, disabled, className }) {
    return (
        <div className="relative">
            <select
                value={value ?? ''}
                onChange={(e) => onChange(e.target.value)}
                disabled={disabled}
                aria-invalid={invalid || undefined}
                className={cn(
                    'h-11 w-full cursor-pointer appearance-none rounded-lg border-0 bg-surface pl-3.5 pr-10 text-base text-fg shadow-field focus:shadow-[0_0_0_2px_rgb(var(--primary)/0.35)] focus:outline-none disabled:cursor-not-allowed disabled:bg-muted disabled:text-fg-muted aria-[invalid=true]:shadow-[0_0_0_1.5px_rgb(var(--danger))]',
                    className,
                )}
            >
                {children}
            </select>
            <ChevronDown className="pointer-events-none absolute right-3.5 top-1/2 size-5 -translate-y-1/2 text-fg-subtle" />
        </div>
    );
}

export const fieldInput =
    'h-11 w-full rounded-lg border-0 bg-surface px-3.5 text-base text-fg shadow-field placeholder:text-fg-subtle focus:shadow-[0_0_0_2px_rgb(var(--primary)/0.35)] focus:outline-none disabled:bg-muted disabled:text-fg-muted aria-[invalid=true]:shadow-[0_0_0_1.5px_rgb(var(--danger))]';

export function InfoCallout({ children }) {
    return (
        <div className="flex items-center gap-3 rounded-lg bg-muted p-3 text-fg-muted">
            <Info className="size-5 shrink-0 text-primary" />
            <p className="text-xs leading-relaxed">{children}</p>
        </div>
    );
}

/** Small white stat tile used under registry tables ("Total hours scheduled" etc.). */
export function SummaryTile({ icon: Icon, label, value }) {
    return (
        <div className="flex items-center gap-4 rounded-lg bg-surface p-5 shadow-card">
            <div className="flex size-11 shrink-0 items-center justify-center rounded-lg bg-muted text-primary-hover">
                <Icon className="size-5" />
            </div>
            <div className="min-w-0">
                <div className="text-2xs font-semibold uppercase tracking-wider text-fg-muted">{label}</div>
                <div className="truncate text-xl font-semibold text-fg">{value}</div>
            </div>
        </div>
    );
}

/**
 * Registry table card: "● Manage X" header, filter box, Show N, Copy / Excel / PDF,
 * column visibility, sortable S/N table and pagination — all client-side
 * (these lists are small).
 *
 * columns: [{ key, header, cell(row), sort?(row) , className, exportValue?(row) }]
 */
export function RegistryCard({ title, rows, columns, searchText, exportName, emptyText = 'No records yet', actions }) {
    const [query, setQuery] = useState('');
    const [perPage, setPerPage] = useState(10);
    const [page, setPage] = useState(1);
    const [sort, setSort] = useState({ key: null, dir: 'asc' });
    const [hidden, setHidden] = useState({});
    const [collapsed, setCollapsed] = useState(false);
    const [refreshing, setRefreshing] = useState(false);

    const visible = columns.filter((c) => !hidden[c.key]);

    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();
        let list = rows.map((r, i) => ({ ...r, __sn: i + 1 }));
        if (q) list = list.filter((r) => searchText(r).toLowerCase().includes(q));
        if (sort.key) {
            const col = columns.find((c) => c.key === sort.key);
            const get = col?.sort ?? ((r) => r.__sn);
            list = [...list].sort((a, b) => {
                const x = get(a);
                const y = get(b);
                const cmp = typeof x === 'number' && typeof y === 'number' ? x - y : String(x ?? '').localeCompare(String(y ?? ''), undefined, { numeric: true });
                return sort.dir === 'asc' ? cmp : -cmp;
            });
        }
        return list;
    }, [rows, query, sort, columns, searchText]);

    const pages = Math.max(1, Math.ceil(filtered.length / perPage));
    const current = Math.min(page, pages);
    const shown = filtered.slice((current - 1) * perPage, current * perPage);
    const from = filtered.length ? (current - 1) * perPage + 1 : 0;
    const to = Math.min(current * perPage, filtered.length);

    const exportCols = columns.filter((c) => c.exportValue).map((c) => ({ label: typeof c.header === 'string' ? c.header : c.key, value: c.exportValue }));
    const asText = () => [exportCols.map((c) => c.label).join('\t'), ...filtered.map((r) => exportCols.map((c) => c.value(r) ?? '').join('\t'))].join('\n');

    const toggleSort = (key) => setSort((s) => ({ key, dir: s.key === key && s.dir === 'asc' ? 'desc' : 'asc' }));

    return (
        <div className="flex w-full flex-col overflow-hidden rounded-lg bg-surface shadow-card">
            <div className="flex items-center justify-between px-6 py-3">
                <div className="flex items-center gap-2">
                    <span className="size-2 rounded-full bg-primary" />
                    <h2 className="text-lg font-semibold tracking-tight">{title}</h2>
                </div>
                <div className="flex items-center gap-0.5 text-fg-muted">
                    <button
                        type="button"
                        title="Reload records"
                        onClick={() => {
                            setRefreshing(true);
                            router.reload({ preserveScroll: true, onFinish: () => setRefreshing(false) });
                        }}
                        className="rounded-md p-1.5 hover:bg-muted hover:text-fg"
                    >
                        <RefreshCw className={cn('size-[18px]', refreshing && 'animate-spin')} />
                    </button>
                    <button type="button" title={collapsed ? 'Expand' : 'Collapse'} onClick={() => setCollapsed((c) => !c)} className="rounded-md p-1.5 hover:bg-muted hover:text-fg">
                        {collapsed ? <ChevronDown className="size-[18px]" /> : <ChevronUp className="size-[18px]" />}
                    </button>
                </div>
            </div>

            {!collapsed && (
                <>
                    <div className="flex flex-col items-stretch justify-between gap-3 bg-canvas px-6 py-3 lg:flex-row lg:items-center">
                        <div className="relative w-full lg:w-80">
                            <Search className="pointer-events-none absolute left-3 top-1/2 size-[18px] -translate-y-1/2 text-fg-subtle" />
                            <input
                                value={query}
                                onChange={(e) => {
                                    setQuery(e.target.value);
                                    setPage(1);
                                }}
                                placeholder="Type to filter..."
                                className="h-9 w-full rounded-lg border-0 bg-surface pl-9 pr-3 text-sm shadow-field placeholder:text-fg-subtle focus:shadow-[0_0_0_2px_rgb(var(--primary)/0.35)] focus:outline-none"
                            />
                        </div>
                        <div className="flex flex-wrap items-center justify-between gap-2 lg:justify-end">
                            <div className="flex items-center gap-1.5">
                                <label className="text-2xs font-semibold text-fg-muted">Show:</label>
                                <div className="relative">
                                    <select
                                        value={perPage}
                                        onChange={(e) => {
                                            setPerPage(Number(e.target.value));
                                            setPage(1);
                                        }}
                                        className="h-9 cursor-pointer appearance-none rounded-lg border-0 bg-surface py-0 pl-2.5 pr-7 font-mono text-xs shadow-field focus:outline-none"
                                    >
                                        {[10, 25, 50, 100].map((n) => (
                                            <option key={n} value={n}>
                                                {n}
                                            </option>
                                        ))}
                                    </select>
                                    <ChevronDown className="pointer-events-none absolute right-1.5 top-1/2 size-4 -translate-y-1/2 text-fg-subtle" />
                                </div>
                            </div>
                            <div className="hidden h-5 w-px bg-border sm:block" />
                            <div className="flex items-center gap-0.5 rounded-lg bg-surface p-0.5 shadow-card">
                                {exportCols.length > 0 && (
                                    <>
                                        <ToolButton
                                            icon={Copy}
                                            label="Copy"
                                            onClick={async () => {
                                                try {
                                                    await navigator.clipboard.writeText(asText());
                                                    toast.success(`${filtered.length} rows copied to the clipboard`);
                                                } catch {
                                                    toast.error('Copy is not available in this browser');
                                                }
                                            }}
                                        />
                                        <ToolButton icon={FileSpreadsheet} label="Excel" onClick={() => downloadCsv(`${exportName}.csv`, exportCols, filtered)} />
                                        <ToolButton icon={FileText} label="PDF" onClick={() => printTable(title, exportCols, filtered)} />
                                    </>
                                )}
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <button type="button" className="flex items-center gap-1 rounded-md px-2 py-1 text-2xs font-semibold text-fg-muted transition-all hover:bg-muted hover:text-fg">
                                            <Columns3 className="size-4" />
                                            Visibility
                                            <ChevronDown className="size-3.5" />
                                        </button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent>
                                        <DropdownMenuLabel>Columns</DropdownMenuLabel>
                                        {columns
                                            .filter((c) => typeof c.header === 'string' && c.header)
                                            .map((c) => (
                                                <DropdownMenuCheckboxItem
                                                    key={c.key}
                                                    checked={!hidden[c.key]}
                                                    onSelect={(e) => e.preventDefault()}
                                                    onCheckedChange={(v) => setHidden((h) => ({ ...h, [c.key]: !v }))}
                                                >
                                                    {c.header}
                                                </DropdownMenuCheckboxItem>
                                            ))}
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>
                            {actions}
                        </div>
                    </div>

                    <div className="w-full overflow-x-auto">
                        <table className="w-full border-collapse text-left">
                            <thead>
                                <tr className="select-none bg-canvas text-2xs font-semibold uppercase tracking-wider text-fg-muted">
                                    {visible.map((c) => (
                                        <th key={c.key} scope="col" className={cn('px-4 py-2 first:pl-6 last:pr-6', c.headerClassName)}>
                                            {c.sort || c.key === 'sn' ? (
                                                <button type="button" onClick={() => toggleSort(c.key)} className="group flex items-center gap-1 uppercase transition-colors hover:text-fg">
                                                    {c.header}
                                                    {sort.key === c.key ? (
                                                        sort.dir === 'asc' ? (
                                                            <ArrowUp className="size-3.5 text-primary" />
                                                        ) : (
                                                            <ArrowDown className="size-3.5 text-primary" />
                                                        )
                                                    ) : (
                                                        <ChevronsUpDown className="size-3.5 text-fg-subtle group-hover:text-primary" />
                                                    )}
                                                </button>
                                            ) : (
                                                c.header
                                            )}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {shown.map((r) => (
                                    <tr key={r.id ?? r.__sn} className="transition-colors hover:bg-muted/60">
                                        {visible.map((c) => (
                                            <td key={c.key} className={cn('px-4 py-3.5 text-sm first:pl-6 last:pr-6', c.className)}>
                                                {c.key === 'sn' ? <span className="tabular text-fg-muted">{r.__sn}</span> : c.cell(r)}
                                            </td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        {!shown.length && <div className="px-6 py-12 text-center text-sm text-fg-muted">{query ? 'No records match your filter.' : emptyText}</div>}
                    </div>

                    <div className="flex flex-wrap items-center justify-between gap-3 px-6 py-3">
                        <span className="text-xs text-fg-muted">
                            Showing {from} to {to} of {filtered.length} entries
                        </span>
                        <div className="flex items-center gap-1">
                            <PagerButton disabled={current <= 1} onClick={() => setPage(current - 1)} label="Previous page">
                                <ChevronLeft className="size-4" />
                            </PagerButton>
                            {Array.from({ length: pages }, (_, i) => i + 1)
                                .filter((p) => p === 1 || p === pages || Math.abs(p - current) <= 1)
                                .map((p, i, arr) => (
                                    <Fragment key={p}>
                                        {i > 0 && p - arr[i - 1] > 1 && <span className="px-1 text-fg-subtle">…</span>}
                                        <button
                                            type="button"
                                            onClick={() => setPage(p)}
                                            className={cn('size-8 rounded-md font-mono text-xs font-semibold', p === current ? 'bg-primary text-white shadow-sm' : 'text-fg-muted hover:bg-muted')}
                                        >
                                            {p}
                                        </button>
                                    </Fragment>
                                ))}
                            <PagerButton disabled={current >= pages} onClick={() => setPage(current + 1)} label="Next page">
                                <ChevronRight className="size-4" />
                            </PagerButton>
                        </div>
                    </div>
                </>
            )}
        </div>
    );
}

function ToolButton({ icon: Icon, label, onClick }) {
    return (
        <button type="button" onClick={onClick} className="flex items-center gap-1 rounded-md px-2 py-1 text-2xs font-semibold text-fg-muted transition-all hover:bg-muted hover:text-primary-hover">
            <Icon className="size-4" />
            {label}
        </button>
    );
}

function PagerButton({ children, disabled, onClick, label }) {
    return (
        <button type="button" aria-label={label} disabled={disabled} onClick={onClick} className="flex size-8 items-center justify-center rounded-md text-fg-muted hover:bg-muted disabled:opacity-40">
            {children}
        </button>
    );
}

/** Opens a clean printable table (users can "Save as PDF" from the print dialog). */
function printTable(title, cols, rows) {
    const esc = (v) => String(v ?? '').replace(/[&<>]/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' })[ch]);
    const w = window.open('', '_blank');
    if (!w) return toast.error('Allow pop-ups to export PDF');
    w.document.write(`<!doctype html><title>${esc(title)}</title>
<style>body{font-family:Inter,Arial,sans-serif;padding:24px;color:#131b2e}h1{font-size:18px}table{border-collapse:collapse;width:100%;font-size:12px}th,td{border-bottom:1px solid #e2e7ff;padding:6px 8px;text-align:left}th{background:#f2f3ff;text-transform:uppercase;font-size:10px;letter-spacing:.04em}</style>
<h1>${esc(title)}</h1><table><thead><tr><th>#</th>${cols.map((c) => `<th>${esc(c.label)}</th>`).join('')}</tr></thead><tbody>
${rows.map((r, i) => `<tr><td>${i + 1}</td>${cols.map((c) => `<td>${esc(c.value(r))}</td>`).join('')}</tr>`).join('')}
</tbody></table><script>window.onload=()=>{window.print()}</script>`);
    w.document.close();
    return undefined;
}

/** Icon-only row actions (view / edit / delete) as in the design. */
export function RowIconButton({ icon: Icon, title, onClick, href, tone = 'default' }) {
    const cls = cn(
        'inline-flex rounded p-1 text-fg-muted transition-colors [&_svg]:size-[18px]',
        tone === 'danger' ? 'hover:text-danger' : tone === 'primary' ? 'hover:text-primary' : 'hover:text-fg',
    );
    return href ? (
        <a href={href} title={title} aria-label={title} className={cls}>
            <Icon />
        </a>
    ) : (
        <button type="button" title={title} aria-label={title} onClick={onClick} className={cls}>
            <Icon />
        </button>
    );
}
