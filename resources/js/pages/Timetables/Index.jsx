import { Head, router } from '@inertiajs/react';
import { CalendarCheck, CalendarDays, CalendarRange, CalendarCog, Clock3, Eye, Pencil, Printer, RefreshCcw, Trash2, UsersRound } from 'lucide-react';
import { withAppLayout } from '@/layouts/AppLayout';
import { Field, InfoCallout, ModuleHeader, ModuleTabs, NativeSelect, RegistryCard, RowIconButton, SetupCard, SummaryTile, fieldInput, useModuleTab } from '@/components/app/module';
import { useConfirmAction } from '@/components/app/confirm-action';
import { useModuleForm } from '@/lib/use-module-form';
import { cn, timeAgo } from '@/lib/utils';

/*
 * Timetables — implements create_timetable_simple_minimal (Create tab) and
 * manage_timetables_simple_minimal (Show tab). Backed by TimeTableController:
 * ttr.store / ttr.update (JSON), ttr.destroy, ttr.manage (period editor).
 */
export default function TimetablesIndex({ session, canCreate, records, summary, classGroups, exams, editing, urls }) {
    const [tab, setTab] = useModuleTab(editing ? 'create' : records.length || !canCreate ? 'list' : 'create');
    const [confirm, confirmDialog] = useConfirmAction();
    const form = useModuleForm({
        initial: { name: '', my_class_id: '', exam_id: '' },
        editing,
        storeUrl: urls.store,
        indexUrl: urls.index,
        only: ['records', 'summary'],
        onCreated: () => setTab('list'),
        validate: (d) => ({
            ...(d.name.trim().length < 3 ? { name: 'Enter a name of at least 3 characters.' } : {}),
            ...(!d.my_class_id ? { my_class_id: 'Choose a class.' } : {}),
        }),
    });

    const published = records.filter((r) => r.periods > 0 && r.year === session).length;

    return (
        <>
            <Head title="Timetables" />
            <div className={cn('mx-auto flex w-full flex-col gap-6', tab === 'create' ? 'max-w-4xl' : 'max-w-7xl')}>
                <ModuleHeader
                    crumbs={['Academics', 'Schedule']}
                    title="Timetables"
                    description="Create and assign class or exam schedules for the active academic year."
                    session={session}
                    aside={
                        tab === 'list' && (
                            <span className="flex items-center gap-1.5 rounded-lg bg-surface px-3 py-1.5 shadow-card">
                                <CalendarCheck className="size-[18px] text-success" />
                                <span className="text-2xs font-semibold text-fg-muted">Active calendars:</span>
                                <span className="font-mono text-xs font-bold text-fg">{published} Scheduled</span>
                            </span>
                        )
                    }
                />

                <ModuleTabs value={tab} onChange={(t) => (editing && t === 'list' ? router.visit(urls.index) : setTab(t))} createLabel="Timetable" listLabel="Show Timetables" count={records.length} canCreate={canCreate} editing={!!editing} />

                {tab === 'create' && canCreate ? (
                    <div className="flex flex-col gap-3">
                        <SetupCard
                            title={editing ? 'Edit Timetable' : 'New Timetable Setup'}
                            description={editing ? 'Update the name, class or type of this timetable.' : 'Fill in the basic properties to generate an editable matrix grid.'}
                            icon={CalendarDays}
                            onSubmit={form.submit}
                            onReset={form.reset}
                            onCancel={() => (editing ? router.visit(urls.index) : setTab('list'))}
                            submitLabel={editing ? 'Save Timetable' : 'Create Timetable'}
                            processing={form.processing}
                        >
                            <Field label="Timetable Name" required aside="Clear & descriptive" error={form.errors.name} span={2}>
                                <input
                                    className={fieldInput}
                                    value={form.data.name}
                                    onChange={(e) => form.set('name', e.target.value)}
                                    placeholder="e.g., JHS 3 First Term Timetable"
                                    aria-invalid={!!form.errors.name || undefined}
                                    autoFocus
                                />
                            </Field>
                            <Field label="Class / Grade" required error={form.errors.my_class_id}>
                                <NativeSelect value={form.data.my_class_id} onChange={(v) => form.set('my_class_id', v)} invalid={!!form.errors.my_class_id}>
                                    <option value="" disabled>
                                        Select class or grade
                                    </option>
                                    {classGroups.map((g) => (
                                        <optgroup key={g.label} label={g.label}>
                                            {g.options.map((o) => (
                                                <option key={o.id} value={o.id}>
                                                    {o.name}
                                                </option>
                                            ))}
                                        </optgroup>
                                    ))}
                                </NativeSelect>
                            </Field>
                            <Field label="Type (Class or Exam)" required error={form.errors.exam_id}>
                                <NativeSelect value={form.data.exam_id} onChange={(v) => form.set('exam_id', v)}>
                                    <option value="">Class Timetable</option>
                                    {exams.length > 0 && (
                                        <optgroup label="Exam Timetable">
                                            {exams.map((e) => (
                                                <option key={e.id} value={e.id}>
                                                    {e.name}
                                                </option>
                                            ))}
                                        </optgroup>
                                    )}
                                </NativeSelect>
                            </Field>
                            <Field label="Academic Year" hint="Timetables are created for the active year.">
                                <NativeSelect value={editing?.year ?? session} onChange={() => {}} disabled>
                                    <option value={editing?.year ?? session}>
                                        {(editing?.year ?? session).replace('-', ' - ')} {(editing?.year ?? session) === session ? '(Active Year)' : ''}
                                    </option>
                                </NativeSelect>
                            </Field>
                            <Field label="Term / Trimester" hint={form.data.exam_id ? 'Taken from the selected exam.' : 'Class timetables apply to the whole year.'}>
                                <NativeSelect value={form.data.exam_id ? String(exams.find((e) => String(e.id) === String(form.data.exam_id))?.term ?? '') : ''} onChange={() => {}} disabled>
                                    <option value="">All terms</option>
                                    {[1, 2, 3].map((t) => (
                                        <option key={t} value={t}>
                                            Term {t}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </Field>
                        </SetupCard>
                        <InfoCallout>
                            {editing ? (
                                <>
                                    Period slots and subjects are edited in the{' '}
                                    <a href={editing.manage} className="font-medium text-primary hover:underline">
                                        matrix editor
                                    </a>
                                    .
                                </>
                            ) : (
                                'Once created, you can instantly define specific period slots and subject allocations in the matrix editor (Manage).'
                            )}
                        </InfoCallout>
                    </div>
                ) : (
                    <>
                        <RegistryCard
                            title="Manage TimeTables"
                            rows={records}
                            exportName={`timetables-${session}`}
                            searchText={(r) => `${r.name} ${r.class} ${r.exam ?? ''} ${r.year}`}
                            emptyText="No timetables yet."
                            columns={[
                                { key: 'sn', header: 'S/N', headerClassName: 'w-16' },
                                {
                                    key: 'name',
                                    header: 'Name',
                                    sort: (r) => r.name,
                                    exportValue: (r) => r.name,
                                    cell: (r) => (
                                        <div className="flex items-center gap-3">
                                            <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary-soft/70 text-primary-hover">
                                                {r.type === 'exam' ? <CalendarCheck className="size-[18px]" /> : <CalendarDays className="size-[18px]" />}
                                            </div>
                                            <div className="min-w-0">
                                                <div className="truncate font-semibold uppercase text-fg">{r.name}</div>
                                                <div className="flex items-center gap-1 text-xs text-fg-muted">
                                                    <span className={cn('size-1.5 rounded-full', r.periods ? 'bg-success' : 'bg-border-strong')} />
                                                    {r.periods ? `Scheduled · ${r.periods} periods` : 'Draft · no periods yet'}
                                                </div>
                                            </div>
                                        </div>
                                    ),
                                },
                                {
                                    key: 'class',
                                    header: 'Class',
                                    sort: (r) => r.class,
                                    exportValue: (r) => r.class,
                                    cell: (r) => <span className="rounded bg-subtle px-2 py-0.5 text-xs font-semibold text-fg">{r.class}</span>,
                                },
                                {
                                    key: 'type',
                                    header: 'Type',
                                    sort: (r) => r.type,
                                    exportValue: (r) => (r.type === 'exam' ? r.exam : 'Class TimeTable'),
                                    cell: (r) =>
                                        r.type === 'exam' ? (
                                            <span className="inline-flex items-center gap-1 rounded-full bg-[#6ffbbe] px-2 py-0.5 text-2xs font-semibold text-success-fg">
                                                <CalendarCheck className="size-3.5" />
                                                {r.exam ?? 'Exam'} TimeTable
                                            </span>
                                        ) : (
                                            <span className="inline-flex items-center gap-1 rounded-full bg-info-soft px-2 py-0.5 text-2xs font-semibold text-info-fg">
                                                <CalendarRange className="size-3.5" />
                                                Class TimeTable
                                            </span>
                                        ),
                                },
                                { key: 'year', header: 'Year', sort: (r) => r.year, exportValue: (r) => r.year, cell: (r) => <span className="font-mono text-xs text-fg">{r.year}</span> },
                                {
                                    key: 'action',
                                    header: 'Action',
                                    headerClassName: 'text-right',
                                    className: 'text-right',
                                    cell: (r) => (
                                        <div className="flex items-center justify-end gap-0.5">
                                            <RowIconButton icon={Eye} title="View grid" href={r.urls.show} tone="primary" />
                                            <RowIconButton icon={Printer} title="Print" href={r.urls.print} />
                                            {r.urls.manage && <RowIconButton icon={CalendarCog} title="Manage periods" href={r.urls.manage} tone="primary" />}
                                            {r.urls.edit && <RowIconButton icon={Pencil} title="Edit" onClick={() => router.visit(r.urls.edit)} />}
                                            {r.urls.destroy && (
                                                <RowIconButton
                                                    icon={Trash2}
                                                    title="Delete"
                                                    tone="danger"
                                                    onClick={() =>
                                                        confirm({
                                                            title: `Delete "${r.name}"?`,
                                                            description: `This removes the timetable and its ${r.periods} scheduled periods. This cannot be undone.`,
                                                            confirmLabel: 'Delete timetable',
                                                            method: 'delete',
                                                            url: r.urls.destroy,
                                                        })
                                                    }
                                                />
                                            )}
                                        </div>
                                    ),
                                },
                            ]}
                        />
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <SummaryTile icon={Clock3} label="Total periods scheduled" value={`${summary.periods} periods`} />
                            <SummaryTile icon={UsersRound} label="Class allocation" value={`${summary.classesCovered} of ${summary.classes} classes`} />
                            <SummaryTile icon={RefreshCcw} label="Last updated" value={summary.updated ? timeAgo(summary.updated) : '—'} />
                        </div>
                    </>
                )}
            </div>
            {confirmDialog}
        </>
    );
}

TimetablesIndex.layout = withAppLayout;
