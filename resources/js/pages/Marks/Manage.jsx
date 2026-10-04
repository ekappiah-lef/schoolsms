import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { toast } from 'sonner';
import { Check, Download, Info, RotateCcw, Table2 } from 'lucide-react';
import { withAppLayout } from '@/layouts/AppLayout';
import { PageHeader, Panel } from '@/components/app/page';
import { MarkSelector } from '@/components/marks/mark-selector';
import { Avatar } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Tooltip } from '@/components/ui/tooltip';
import { submitForm } from '@/lib/http';
import { downloadCsv } from '@/lib/use-visit-state';
import { cn, ordinal } from '@/lib/utils';

// Maximum scores per column, as on the original score sheet.
const COLUMNS = [
    { key: 't1', label: '1st CA', max: 20 },
    { key: 't2', label: '2nd CA', max: 20 },
    { key: 'exm', label: 'Exam', max: 60 },
];

const toValue = (v) => (v === null || v === undefined ? '' : String(v));

function gradeFor(total, grades, general) {
    if (!(total >= 1)) return null;
    const find = (list) => list.find((g) => total >= g.from && total <= g.to);
    return (grades.length ? find(grades) : null) ?? find(general) ?? null;
}

function gradeTone(name = '') {
    const g = name.toUpperCase()[0];
    if (g === 'A' || g === 'B') return 'success';
    if (g === 'C') return 'info';
    if (g === 'D' || g === 'E') return 'warning';
    return 'danger';
}

export default function MarksManage({ context, rows, grades, generalGrades, exams, classes, current, urls }) {
    const initial = useMemo(() => Object.fromEntries(rows.map((r) => [r.id, { t1: toValue(r.t1), t2: toValue(r.t2), exm: toValue(r.exm) }])), [rows]);
    const [values, setValues] = useState(initial);
    const [saving, setSaving] = useState(false);
    const inputs = useRef({});

    useEffect(() => setValues(initial), [initial]);

    const dirtyIds = rows.filter((r) => COLUMNS.some((c) => values[r.id]?.[c.key] !== initial[r.id]?.[c.key])).map((r) => r.id);
    const dirty = dirtyIds.length > 0;

    const errors = useMemo(() => {
        const e = {};
        rows.forEach((r) =>
            COLUMNS.forEach((c) => {
                const raw = values[r.id]?.[c.key];
                if (raw === '' || raw === undefined) return;
                const n = Number(raw);
                if (!Number.isFinite(n) || n < 0 || n > c.max) e[`${r.id}.${c.key}`] = `0 – ${c.max}`;
            }),
        );
        return e;
    }, [values, rows]);
    const hasErrors = Object.keys(errors).length > 0;

    const byId = useMemo(() => Object.fromEntries(rows.map((r) => [r.id, r])), [rows]);

    // Unchanged rows show the stored total/grade (what report cards use);
    // edited rows show a live preview of what the server will calculate.
    const computed = (id) => {
        const v = values[id] ?? {};
        const row = byId[id];
        const edited = COLUMNS.some((c) => v[c.key] !== initial[id]?.[c.key]);
        if (!edited && row) {
            const stored = row.total === null || row.total === undefined ? null : Number(row.total);
            return { total: stored, grade: row.grade ? { name: row.grade, remark: gradeFor(stored, grades, generalGrades)?.remark } : null };
        }
        const nums = COLUMNS.map((c) => (v[c.key] === '' ? null : Number(v[c.key])));
        if (nums.every((n) => n === null)) return { total: null, grade: null };
        const total = nums.reduce((a, n) => a + (n || 0), 0);
        return { total, grade: gradeFor(total, grades, generalGrades) };
    };

    const stats = useMemo(() => {
        const totals = rows.map((r) => computed(r.id).total).filter((t) => t !== null);
        if (!totals.length) return null;
        const avg = totals.reduce((a, b) => a + b, 0) / totals.length;
        return { entered: totals.length, avg: Math.round(avg * 10) / 10, high: Math.max(...totals), low: Math.min(...totals) };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [values, rows]);

    const setCell = (id, key, raw) => setValues((s) => ({ ...s, [id]: { ...s[id], [key]: raw.replace(/[^\d]/g, '').slice(0, 3) } }));

    // Spreadsheet navigation: Enter/↓ next student, ↑ previous, ←/→ across columns at the edges.
    const focusCell = (rowIndex, colIndex) => {
        const row = rows[rowIndex];
        if (!row) return;
        const el = inputs.current[`${row.id}.${COLUMNS[colIndex].key}`];
        el?.focus();
        el?.select();
    };
    const onKeyDown = (e, rowIndex, colIndex) => {
        const el = e.currentTarget;
        if (e.key === 'Enter' || e.key === 'ArrowDown') {
            e.preventDefault();
            focusCell(rowIndex + 1, colIndex);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            focusCell(rowIndex - 1, colIndex);
        } else if (e.key === 'ArrowRight' && el.selectionStart === el.value.length && colIndex < COLUMNS.length - 1) {
            e.preventDefault();
            focusCell(rowIndex, colIndex + 1);
        } else if (e.key === 'ArrowLeft' && el.selectionStart === 0 && colIndex > 0) {
            e.preventDefault();
            focusCell(rowIndex, colIndex - 1);
        }
    };

    const save = useCallback(async () => {
        if (!dirty || hasErrors || saving) return;
        const over = rows.filter((r) => (computed(r.id).total ?? 0) > 100);
        if (over.length) {
            toast.error(`${over.length} student(s) total more than 100. Fix those rows before saving.`);
            return;
        }
        setSaving(true);
        // The endpoint expects every student's fields (t1_{id}, t2_{id}, exm_{id}).
        const payload = {};
        rows.forEach((r) => COLUMNS.forEach((c) => (payload[`${c.key}_${r.id}`] = values[r.id]?.[c.key] ?? '')));
        const result = await submitForm(urls.update, payload, { method: 'put' });
        setSaving(false);
        if (result.ok) {
            toast.success('Marks saved. Grades and positions updated.');
            router.reload({ only: ['rows'], preserveScroll: true });
        } else {
            toast.error(result.message);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [dirty, hasErrors, saving, values, rows, urls.update]);

    // Ctrl/⌘ + S saves; warn before leaving with unsaved scores.
    useEffect(() => {
        const onKey = (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
                e.preventDefault();
                save();
            }
        };
        const onUnload = (e) => {
            if (!dirty) return;
            e.preventDefault();
            e.returnValue = '';
        };
        window.addEventListener('keydown', onKey);
        window.addEventListener('beforeunload', onUnload);
        const off = router.on('before', (event) => {
            if (dirty && event.detail.visit.method === 'get' && !event.detail.visit.only?.length && !window.confirm('You have unsaved scores. Leave without saving?')) return false;
            return undefined;
        });
        return () => {
            window.removeEventListener('keydown', onKey);
            window.removeEventListener('beforeunload', onUnload);
            off();
        };
    }, [save, dirty]);

    const title = `${context.subject} · ${context.class} ${context.section}`;

    return (
        <>
            <Head title={`Marks · ${title}`} />
            <PageHeader
                breadcrumbs={[{ label: 'Academics' }, { label: 'Examinations' }, { label: 'Marks entry' }]}
                title={title}
                meta={
                    <Badge tone="outline">
                        {context.exam} · {context.year}
                    </Badge>
                }
                description="Type scores and press Enter to move down. Totals and grades update as you type; positions are recalculated when you save."
                actions={
                    <>
                        {urls.tabulation && (
                            <Button asChild>
                                <a href={urls.tabulation}>
                                    <Table2 />
                                    Tabulation
                                </a>
                            </Button>
                        )}
                        <Button
                            onClick={() =>
                                downloadCsv(`marks-${context.subject}-${context.class}${context.section}.csv`, [
                                    { label: 'Name', value: (r) => r.name },
                                    { label: 'Admission no.', value: (r) => r.adm_no },
                                    ...COLUMNS.map((c) => ({ label: `${c.label} (${c.max})`, value: (r) => values[r.id]?.[c.key] })),
                                    { label: 'Total', value: (r) => computed(r.id).total },
                                    { label: 'Grade', value: (r) => computed(r.id).grade?.name },
                                ], rows)
                            }
                        >
                            <Download />
                            Export
                        </Button>
                    </>
                }
            />

            <div className="panel mb-4 px-4 py-3">
                <MarkSelector compact exams={exams} classes={classes} current={current} urls={urls} />
            </div>

            <Panel flush className="relative">
                <div className="flex flex-wrap items-center gap-x-6 gap-y-1 border-b border-border px-4 py-2.5 text-sm">
                    <span className="text-fg-muted">
                        <span className="font-medium text-fg">{rows.length}</span> students
                    </span>
                    {stats && (
                        <>
                            <span className="text-fg-muted">
                                Entered <span className="tabular font-medium text-fg">{stats.entered}</span>
                            </span>
                            <span className="text-fg-muted">
                                Average <span className="tabular font-medium text-fg">{stats.avg}</span>
                            </span>
                            <span className="text-fg-muted">
                                Highest <span className="tabular font-medium text-fg">{stats.high}</span>
                            </span>
                            <span className="text-fg-muted">
                                Lowest <span className="tabular font-medium text-fg">{stats.low}</span>
                            </span>
                        </>
                    )}
                    <Tooltip content={[...(grades.length ? grades : generalGrades)].sort((a, b) => b.from - a.from).map((g) => `${g.name}: ${g.from}–${g.to}`).join(' · ')}>
                        <span className="ml-auto inline-flex cursor-help items-center gap-1 text-xs text-fg-muted">
                            <Info className="size-3.5" />
                            Grade bands
                        </span>
                    </Tooltip>
                </div>

                <div className="scrollbar-thin overflow-x-auto">
                    <table className="w-full text-left">
                        <thead>
                            <tr className="border-b border-border bg-canvas text-2xs font-semibold uppercase text-fg-muted">
                                <th className="h-9 w-10 px-4 text-right">#</th>
                                <th className="h-9 px-3">Student</th>
                                {COLUMNS.map((c) => (
                                    <th key={c.key} className="h-9 w-28 px-2 text-center">
                                        {c.label} <span className="font-normal text-fg-subtle">/{c.max}</span>
                                    </th>
                                ))}
                                <th className="h-9 w-20 px-3 text-right">Total</th>
                                <th className="h-9 w-32 px-3">Grade</th>
                                <th className="hidden h-9 w-20 px-4 text-right md:table-cell">Pos.</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((r, ri) => {
                                const { total, grade } = computed(r.id);
                                const changed = dirtyIds.includes(r.id);
                                return (
                                    <tr key={r.id} className={cn('border-b border-border last:border-0', changed ? 'bg-warning-soft/50' : 'hover:bg-muted')}>
                                        <td className="tabular px-4 text-right text-sm text-fg-subtle">{ri + 1}</td>
                                        <td className="px-3 py-1.5">
                                            <div className="flex items-center gap-2.5">
                                                <Avatar src={r.photo} name={r.name} size="sm" />
                                                <div className="min-w-0">
                                                    <div className="truncate text-sm font-medium">{r.name}</div>
                                                    <div className="tabular truncate text-xs text-fg-muted">{r.adm_no}</div>
                                                </div>
                                            </div>
                                        </td>
                                        {COLUMNS.map((c, ci) => {
                                            const key = `${r.id}.${c.key}`;
                                            const err = errors[key];
                                            return (
                                                <td key={c.key} className="px-2 py-1.5">
                                                    <input
                                                            ref={(el) => (inputs.current[key] = el)}
                                                            title={err ? `Enter ${err}` : undefined}
                                                            value={values[r.id]?.[c.key] ?? ''}
                                                            onChange={(e) => setCell(r.id, c.key, e.target.value)}
                                                            onKeyDown={(e) => onKeyDown(e, ri, ci)}
                                                            onFocus={(e) => e.target.select()}
                                                            inputMode="numeric"
                                                            aria-label={`${c.label} for ${r.name}`}
                                                            aria-invalid={err ? true : undefined}
                                                            className={cn(
                                                                'tabular h-8 w-full rounded-md border bg-surface text-center text-base transition-colors focus:border-primary focus:shadow-focus focus:outline-none',
                                                                err ? 'border-danger bg-danger-soft text-danger-fg' : 'border-border hover:border-border-strong',
                                                            )}
                                                        />
                                                </td>
                                            );
                                        })}
                                        <td className={cn('tabular px-3 text-right font-semibold', total > 100 && 'text-danger-fg')}>{total ?? <span className="text-fg-subtle">—</span>}</td>
                                        <td className="px-3">
                                            {grade ? (
                                                <span className="inline-flex items-center gap-1.5">
                                                    <Badge tone={gradeTone(grade.name)} shape="tag">
                                                        {grade.name}
                                                    </Badge>
                                                    <span className="truncate text-xs text-fg-muted">{grade.remark}</span>
                                                </span>
                                            ) : (
                                                <span className="text-fg-subtle">—</span>
                                            )}
                                        </td>
                                        <td className="tabular hidden px-4 text-right text-sm text-fg-muted md:table-cell">{changed ? '…' : ordinal(r.position)}</td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>

                {/* Save bar */}
                <div className="sticky bottom-0 flex flex-wrap items-center justify-between gap-3 rounded-b-lg border-t border-border bg-surface/95 px-4 py-3 backdrop-blur">
                    <span className="text-sm text-fg-muted">
                        {hasErrors ? (
                            <span className="text-danger-fg">Some scores are out of range.</span>
                        ) : dirty ? (
                            <>
                                <span className="font-medium text-fg">{dirtyIds.length}</span> unsaved {dirtyIds.length === 1 ? 'row' : 'rows'}
                                <span className="ml-2 hidden text-fg-subtle sm:inline">Ctrl + S to save</span>
                            </>
                        ) : (
                            'All changes saved'
                        )}
                    </span>
                    <div className="flex gap-2">
                        {dirty && (
                            <Button variant="ghost" onClick={() => setValues(initial)} disabled={saving}>
                                <RotateCcw />
                                Discard
                            </Button>
                        )}
                        <Button variant="primary" onClick={save} disabled={!dirty || hasErrors} loading={saving}>
                            {!saving && <Check />}
                            {saving ? 'Saving…' : 'Save marks'}
                        </Button>
                    </div>
                </div>
            </Panel>
        </>
    );
}

MarksManage.layout = withAppLayout;
