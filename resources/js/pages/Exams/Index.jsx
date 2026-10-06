import { useMemo, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { Eye, MoreHorizontal, NotebookPen, Pencil, Trash2 } from 'lucide-react';
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

/** Exams — Create exam / Show exams (ExamController; store & update redirect back with a flash). */
export default function ExamsIndex({ session, canDelete, exams, editing, urls }) {
    const [tab, setTab] = useModuleTab(editing ? 'create' : 'list');
    const [confirm, confirmDialog] = useConfirmAction();
    const form = useModuleForm({
        mode: 'redirect',
        initial: { name: '', term: '' },
        editing,
        storeUrl: urls.store,
        indexUrl: urls.index,
        onCreated: () => setTab('list'),
        validate: (d) => ({
            ...(!d.name.trim() ? { name: 'Enter the exam name.' } : {}),
            ...(!d.term ? { term: 'Choose the term.' } : {}),
        }),
    });

    return (
        <>
            <Head title="Examinations" />
            <div className={cn('mx-auto flex w-full flex-col gap-6', tab === 'create' ? 'max-w-4xl' : 'max-w-7xl')}>
                <ModuleHeader
                    crumbs={['Academics', 'Examinations']}
                    title="Examinations"
                    session={session}
                />
                <ModuleTabs value={tab} onChange={(t) => (editing && t === 'list' ? router.visit(urls.index) : setTab(t))} createLabel="Examination" listLabel="View Examinations" count={exams.length} editing={!!editing} />

                {tab === 'create' ? (
                    <div className="flex flex-col gap-3">
                        <SetupCard
                            title={editing ? `Edit ${editing.name}` : 'New Exam Setup'}
                            description={editing ? `Year ${editing.year}` : 'The exam is created for the active year.'}
                            icon={NotebookPen}
                            onSubmit={form.submit}
                            onReset={form.reset}
                            onCancel={() => (editing ? router.visit(urls.index) : setTab('list'))}
                            submitLabel={editing ? 'Save Exam' : 'Create Exam'}
                            processing={form.processing}
                        >
                            <Field label="Exam Name" required aside="e.g. First Term Examination" error={form.errors.name} span={2}>
                                <input className={fieldInput} value={form.data.name} onChange={(e) => form.set('name', e.target.value)} placeholder="e.g., First Term Examination" aria-invalid={!!form.errors.name || undefined} autoFocus />
                            </Field>
                            <Field label="Term" required error={form.errors.term}>
                                <NativeSelect value={form.data.term} onChange={(v) => form.set('term', v)} invalid={!!form.errors.term}>
                                    <option value="" disabled>
                                        Select term
                                    </option>
                                    {[1, 2, 3].map((t) => (
                                        <option key={t} value={t}>
                                            Term {t}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </Field>
                            <Field label="Academic Year">
                                <NativeSelect value="s" onChange={() => {}} disabled>
                                    <option value="s">{(editing?.year ?? session).replace('-', ' - ')}</option>
                                </NativeSelect>
                            </Field>
                        </SetupCard>
                        <InfoCallout>The term decides which total column (1st, 2nd or 3rd term) marks are recorded in on report sheets.</InfoCallout>
                    </div>
                ) : (
                    <ExamList exams={exams} canDelete={canDelete} confirm={confirm} />
                )}
            </div>
            {confirmDialog}
        </>
    );
}

ExamsIndex.layout = withAppLayout;

/** Examinations list: search, year and term filters; the name opens the exam overview. */
function ExamList({ exams, canDelete, confirm }) {
    const [q, setQ] = useState('');
    const [year, setYear] = useState('');
    const [term, setTerm] = useState('');
    const years = useMemo(() => [...new Set(exams.map((e) => e.year))].map((y) => ({ value: y, label: y.replace('-', ' – ') })), [exams]);
    const rows = exams.filter((e) => (!q || e.name.toLowerCase().includes(q.trim().toLowerCase())) && (!year || e.year === year) && (!term || String(e.term) === term));
    const { shown, pager, offset } = usePaged(rows, 10, 'examinations');

    return (
        <div className="overflow-hidden rounded-lg bg-surface shadow-card">
            <div className="flex flex-wrap items-center gap-3 border-b border-border px-5 py-4">
                <SearchInput value={q} onChange={setQ} placeholder="Search examinations..." className="w-full sm:w-72" delay={0} />
                <Select size="sm" className="w-44" value={year} onChange={setYear} options={years} clearable clearLabel="All academic years" placeholder="Academic year" />
                <Select
                    size="sm"
                    className="w-36"
                    value={term}
                    onChange={setTerm}
                    options={[1, 2, 3].map((t) => ({ value: String(t), label: `Term ${t}` }))}
                    clearable
                    clearLabel="All terms"
                    placeholder="Term"
                />
            </div>
            {rows.length ? (
                <div className="scrollbar-thin overflow-x-auto">
                    <table className="w-full text-left">
                        <thead>
                            <tr className="border-b border-border bg-canvas text-2xs font-semibold uppercase text-fg-muted">
                                <th className="h-10 w-12 px-5">#</th>
                                <th className="h-10 px-3">Examination</th>
                                <th className="h-10 px-3">Term</th>
                                <th className="h-10 px-3">Avg. score</th>
                                <th className="h-10 px-3">Academic year</th>
                                <th className="h-10 w-20 px-5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {shown.map((r, i) => (
                                <tr key={r.id} onClick={() => router.visit(r.urls.show)} className="cursor-pointer border-b border-border last:border-0 hover:bg-muted/60">
                                    <td className="tabular px-5 py-3 text-sm text-fg-muted">{offset + i + 1}</td>
                                    <td className="px-3 py-3">
                                        <Link href={r.urls.show} className="font-semibold hover:text-primary" onClick={(e) => e.stopPropagation()}>
                                            {r.name}
                                        </Link>
                                        <div className="text-xs text-fg-muted">{r.results ? `${r.results} student results` : 'No marks yet'}</div>
                                    </td>
                                    <td className="px-3 py-3">
                                        <span className="rounded-full bg-info-soft px-2 py-0.5 text-2xs font-semibold text-info-fg">Term {r.term}</span>
                                    </td>
                                    <td className="tabular px-3 py-3 text-sm">{r.average !== null ? `${r.average}%` : <span className="text-fg-subtle">—</span>}</td>
                                    <td className="tabular px-3 py-3 text-sm">{r.year.replace('-', ' – ')}</td>
                                    <td className="px-5 py-3 text-right" onClick={(e) => e.stopPropagation()}>
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button variant="ghost" size="icon-sm" aria-label={`Actions for ${r.name}`}>
                                                    <MoreHorizontal />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuItem asChild>
                                                    <Link href={r.urls.show}>
                                                        <Eye />
                                                        Open examination
                                                    </Link>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem asChild>
                                                    <Link href={r.urls.edit}>
                                                        <Pencil />
                                                        Edit examination
                                                    </Link>
                                                </DropdownMenuItem>
                                                {canDelete && (
                                                    <>
                                                        <DropdownMenuSeparator />
                                                        <DropdownMenuItem
                                                            className="text-danger-fg"
                                                            onSelect={() =>
                                                                confirm({
                                                                    title: `Delete ${r.name} (${r.year})?`,
                                                                    description: r.results
                                                                        ? `${r.results} student results are recorded for this examination and may be removed. This cannot be undone.`
                                                                        : 'This cannot be undone.',
                                                                    confirmLabel: 'Delete examination',
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
                <EmptyState icon={NotebookPen} title={exams.length ? 'No examinations match these filters' : 'No examinations yet'} />
            )}
        </div>
    );
}
