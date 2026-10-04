import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { Medal, MoreHorizontal, Pencil, Trash2 } from 'lucide-react';
import { withAppLayout } from '@/layouts/AppLayout';
import { Field, InfoCallout, ModuleHeader, ModuleTabs, NativeSelect, SetupCard, fieldInput, useModuleTab } from '@/components/app/module';
import { EmptyState } from '@/components/app/page';
import { SearchInput, usePaged } from '@/components/app/data-table';
import { Button } from '@/components/ui/button';
import { Select } from '@/components/ui/select';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { useConfirmAction } from '@/components/app/confirm-action';
import { useModuleForm } from '@/lib/use-module-form';
import { cn } from '@/lib/utils';

/** Grades — Create grade / Show grades (GradeController; store & update redirect back with a flash). */
export default function GradesIndex({ session, canDelete, types, remarks, grades, editing, urls }) {
    const [tab, setTab] = useModuleTab(editing ? 'create' : 'list');
    const [confirm, confirmDialog] = useConfirmAction();
    const form = useModuleForm({
        mode: 'redirect',
        initial: { name: '', class_type_id: '', mark_from: '', mark_to: '', remark: '' },
        editing,
        storeUrl: urls.store,
        indexUrl: urls.index,
        onCreated: () => setTab('list'),
        validate: (d) => {
            const e = {};
            if (!d.name.trim()) e.name = 'Enter the grade letter.';
            if (d.mark_from === '' || Number.isNaN(Number(d.mark_from))) e.mark_from = 'Enter the lowest mark.';
            if (d.mark_to === '' || Number.isNaN(Number(d.mark_to))) e.mark_to = 'Enter the highest mark.';
            if (!e.mark_from && !e.mark_to && Number(d.mark_from) > Number(d.mark_to)) e.mark_to = 'Must be at least the lowest mark.';
            return e;
        },
    });

    return (
        <>
            <Head title="Grading Scales" />
            <div className={cn('mx-auto flex w-full flex-col gap-6', tab === 'create' ? 'max-w-4xl' : 'max-w-7xl')}>
                <ModuleHeader
                    crumbs={['Academics', 'Examinations']}
                    title="Grading Scales"
                    description="Define score ranges, grades, and remarks used when evaluating student results. Class-specific scales override the default grading scale."
                    session={session}
                />
                <ModuleTabs value={tab} onChange={(t) => (editing && t === 'list' ? router.visit(urls.index) : setTab(t))} createLabel="Grade" createText="Add Grade" listLabel="View Grades" count={grades.length} editing={!!editing} />

                {tab === 'create' ? (
                    <div className="flex flex-col gap-3">
                        <SetupCard
                            title={editing ? `Edit grade ${editing.name}` : 'New Grade Setup'}
                            description="A total score between the lowest and highest mark receives this grade."
                            icon={Medal}
                            onSubmit={form.submit}
                            onReset={form.reset}
                            onCancel={() => (editing ? router.visit(urls.index) : setTab('list'))}
                            submitLabel={editing ? 'Save Grade' : 'Add Grade'}
                            processing={form.processing}
                        >
                            <Field label="Grade" required aside="e.g. A1, B, C4" error={form.errors.name}>
                                <input className={fieldInput} value={form.data.name} onChange={(e) => form.set('name', e.target.value)} placeholder="e.g., A" aria-invalid={!!form.errors.name || undefined} autoFocus />
                            </Field>
                            <Field label="Class Type" hint="All Classes is the default scale; a class type overrides it." error={form.errors.class_type_id}>
                                <NativeSelect value={form.data.class_type_id} onChange={(v) => form.set('class_type_id', v)}>
                                    <option value="">All Classes (default scale)</option>
                                    {types.map((t) => (
                                        <option key={t.id} value={t.id}>
                                            {t.name}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </Field>
                            <Field label="Lowest Mark" required error={form.errors.mark_from}>
                                <input className={fieldInput} type="number" min={0} max={100} value={form.data.mark_from} onChange={(e) => form.set('mark_from', e.target.value)} placeholder="e.g., 70" aria-invalid={!!form.errors.mark_from || undefined} />
                            </Field>
                            <Field label="Highest Mark" required error={form.errors.mark_to}>
                                <input className={fieldInput} type="number" min={0} max={100} value={form.data.mark_to} onChange={(e) => form.set('mark_to', e.target.value)} placeholder="e.g., 100" aria-invalid={!!form.errors.mark_to || undefined} />
                            </Field>
                            <Field label="Remark" span={2} error={form.errors.remark}>
                                <NativeSelect value={form.data.remark} onChange={(v) => form.set('remark', v)}>
                                    <option value="">Select remark…</option>
                                    {remarks.map((r) => (
                                        <option key={r} value={r}>
                                            {r}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </Field>
                        </SetupCard>
                        <InfoCallout>After changing the grading scale, use Recalculate totals under Examinations to re-grade marks that were already entered.</InfoCallout>
                    </div>
                ) : (
                    <GradeList grades={grades} types={types} canDelete={canDelete} confirm={confirm} />
                )}
            </div>
            {confirmDialog}
        </>
    );
}

GradesIndex.layout = withAppLayout;

/** Grading scale: search and filter by class type; class-specific bands override the default ("All Classes"). */
function GradeList({ grades, types, canDelete, confirm }) {
    const [q, setQ] = useState('');
    const [type, setType] = useState('');
    const rows = grades.filter((g) => {
        if (q && !`${g.name} ${g.remark ?? ''} ${g.type ?? 'All Classes'}`.toLowerCase().includes(q.trim().toLowerCase())) return false;
        if (type === 'all') return !g.type;
        if (type) return g.type === type;
        return true;
    });
    const { shown, pager, offset } = usePaged(rows, 10, 'grades');

    return (
        <div className="flex flex-col gap-3">
            <p className="text-sm text-fg-muted">Class-specific grading scales take priority over the default scale (All Classes).</p>
            <div className="overflow-hidden rounded-lg bg-surface shadow-card">
                <div className="flex flex-wrap items-center gap-3 border-b border-border px-5 py-4">
                    <h2 className="mr-2 font-semibold">Grading Scale</h2>
                    <SearchInput value={q} onChange={setQ} placeholder="Search grades..." className="w-full sm:w-64" delay={0} />
                    <Select
                        size="sm"
                        className="w-48"
                        value={type}
                        onChange={setType}
                        options={[{ value: 'all', label: 'All Classes (default)' }, ...types.map((t) => ({ value: t.name, label: t.name }))]}
                        clearable
                        clearLabel="All class types"
                        placeholder="All class types"
                    />
                </div>
                {rows.length ? (
                    <div className="scrollbar-thin overflow-x-auto">
                        <table className="w-full text-left">
                            <thead>
                                <tr className="border-b border-border bg-canvas text-2xs font-semibold uppercase text-fg-muted">
                                    <th className="h-10 w-12 px-5">#</th>
                                    <th className="h-10 px-3">Grade</th>
                                    <th className="h-10 px-3">Score range</th>
                                    <th className="h-10 px-3">Remark</th>
                                    <th className="h-10 px-3">Applies to</th>
                                    <th className="h-10 w-20 px-5 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {shown.map((r, i) => (
                                    <tr key={r.urls.edit} className="border-b border-border last:border-0 hover:bg-muted/60">
                                        <td className="tabular px-5 py-3 text-sm text-fg-muted">{offset + i + 1}</td>
                                        <td className="px-3 py-3">
                                            <span className="inline-flex min-w-9 justify-center rounded-md bg-primary-soft/60 px-2 py-1 font-semibold text-primary-hover">{r.name}</span>
                                        </td>
                                        <td className="tabular px-3 py-3 text-sm">
                                            {r.from} – {r.to}
                                        </td>
                                        <td className="px-3 py-3 text-sm">{r.remark || <span className="text-fg-subtle">—</span>}</td>
                                        <td className="px-3 py-3 text-sm">{r.type ?? 'All Classes'}</td>
                                        <td className="px-5 py-3 text-right">
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button variant="ghost" size="icon-sm" aria-label={`Actions for grade ${r.name}`}>
                                                        <MoreHorizontal />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuItem onSelect={() => router.visit(r.urls.edit)}>
                                                        <Pencil />
                                                        Edit grade
                                                    </DropdownMenuItem>
                                                    {canDelete && (
                                                        <>
                                                            <DropdownMenuSeparator />
                                                            <DropdownMenuItem
                                                                className="text-danger-fg"
                                                                onSelect={() =>
                                                                    confirm({
                                                                        title: `Delete grade ${r.name}?`,
                                                                        description: 'Marks in this range will no longer receive a grade until they are recalculated. This cannot be undone.',
                                                                        confirmLabel: 'Delete grade',
                                                                        method: 'delete',
                                                                        url: r.urls.destroy,
                                                                    })
                                                                }
                                                            >
                                                                <Trash2 />
                                                                Delete
                                                            </DropdownMenuItem>
                                                        </>
                                                    )}
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        {pager}
                    </div>
                ) : (
                    <EmptyState icon={Medal} title={grades.length ? 'No grades match these filters' : 'No grades yet'} />
                )}
            </div>
        </div>
    );
}
