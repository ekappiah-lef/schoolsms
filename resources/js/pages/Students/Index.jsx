import { useMemo } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import {
    Download,
    Eye,
    GraduationCap,
    KeyRound,
    MoreHorizontal,
    Pencil,
    ScrollText,
    Trash2,
    Undo2,
    UserPlus,
    Users,
    X,
} from 'lucide-react';
import { withAppLayout } from '@/layouts/AppLayout';
import { DataTable, Pagination, SearchInput } from '@/components/app/data-table';
import { EmptyState, PageHeader } from '@/components/app/page';
import { useConfirmAction } from '@/components/app/confirm-action';
import { Avatar } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Select } from '@/components/ui/select';
import { Segmented } from '@/components/ui/tabs';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { downloadCsv, useVisitState } from '@/lib/use-visit-state';
import { formatNumber } from '@/lib/utils';

export default function StudentsIndex({ variant, students, summary, filters, classes, sections, urls }) {
    const graduated = variant === 'graduated';
    const busy = useVisitState();
    const [confirm, confirmDialog] = useConfirmAction();

    const apply = (changes) => {
        const next = { ...filters, ...changes };
        if (!('page' in changes)) delete next.page;
        // Keep the URL clean: drop defaults and empty values.
        const params = Object.fromEntries(
            Object.entries(next).filter(([k, v]) => v !== '' && v !== null && v !== undefined && !(k === 'per_page' && v === 10)),
        );
        router.get(urls.index, params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['students', 'summary', 'filters'],
        });
    };

    const sectionOptions = useMemo(
        () => sections.filter((s) => String(s.class_id) === String(filters.class)).map((s) => ({ value: s.id, label: s.name })),
        [sections, filters.class],
    );
    const hasFilters = filters.q || filters.class || filters.section || filters.gender;

    const columns = useMemo(
        () => [
            {
                id: 'name',
                header: 'Student',
                meta: { sortKey: 'name', className: 'min-w-[220px]' },
                cell: ({ row }) => {
                    const s = row.original;
                    return (
                        <div className="flex items-center gap-3">
                            <Avatar src={s.photo} name={s.name} size="md" />
                            <div className="min-w-0">
                                <Link href={s.urls.show} className="block truncate font-medium text-fg hover:text-primary" onClick={(e) => e.stopPropagation()}>
                                    {s.name}
                                </Link>
                            </div>
                        </div>
                    );
                },
            },
            {
                id: 'adm_no',
                header: 'Admission no.',
                meta: { sortKey: 'adm_no', label: 'Admission no.', hideBelow: 'sm', className: 'tabular whitespace-nowrap text-sm text-fg-muted' },
                cell: ({ row }) => row.original.adm_no,
            },
            {
                id: 'class',
                header: 'Class',
                meta: { sortKey: 'class', label: 'Class', className: 'whitespace-nowrap' },
                cell: ({ row }) => (
                    <span className="inline-flex items-center gap-1.5">
                        <Badge tone="outline" shape="tag">
                            {row.original.class}
                        </Badge>
                        {row.original.section && <span className="text-sm text-fg-muted">{row.original.section}</span>}
                    </span>
                ),
            },
            {
                id: 'gender',
                header: 'Gender',
                meta: { label: 'Gender', hideBelow: 'lg', className: 'text-sm text-fg-muted' },
                cell: ({ row }) => row.original.gender || '—',
            },
            {
                id: 'parent',
                header: 'Parent / guardian',
                meta: { label: 'Parent / guardian', hideBelow: 'xl', className: 'max-w-[200px] truncate text-sm' },
                cell: ({ row }) => row.original.parent || <span className="text-fg-subtle">Not linked</span>,
            },
            graduated
                ? {
                      id: 'grad_date',
                      header: 'Graduated',
                      meta: { sortKey: 'grad_date', label: 'Graduated', hideBelow: 'md', className: 'tabular whitespace-nowrap text-sm text-fg-muted' },
                      cell: ({ row }) => row.original.grad_date || '—',
                  }
                : {
                      id: 'year',
                      header: 'Admission year',
                      meta: { sortKey: 'year', label: 'Admission year', hideBelow: 'md', className: 'tabular text-sm text-fg-muted' },
                      cell: ({ row }) => row.original.year_admitted || '—',
                  },
            {
                id: 'actions',
                header: () => <span className="sr-only">Actions</span>,
                enableHiding: false,
                meta: { className: 'w-12 text-right', headerClassName: 'w-12' },
                cell: ({ row }) => <RowActions student={row.original} confirm={confirm} />,
            },
        ],
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [graduated],
    );

    const title = graduated ? 'Graduated students' : 'Students';

    return (
        <>
            <Head title={title} />
            <PageHeader
                breadcrumbs={[{ label: 'Academics' }, { label: 'Students', href: urls.active }, ...(graduated ? [{ label: 'Graduated' }] : [])]}
                title={title}
             
                actions={
                    <>
                        {urls.promotion && !graduated && (
                            <Button asChild>
                                <Link href={urls.promotion}>
                                    <GraduationCap />
                                    Promote students
                                </Link>
                            </Button>
                        )}
                        {urls.create && (
                            <Button variant="primary" asChild>
                                <Link href={urls.create}>
                                    <UserPlus />
                                    Admit student
                                </Link>
                            </Button>
                        )}
                    </>
                }
            />

            {urls.graduated && (
                <Segmented
                    className="mb-4"
                    value={variant}
                    onChange={(v) => router.visit(v === 'graduated' ? urls.graduated : urls.active)}
                    options={[
                        { value: 'active', label: `Enrolled ${formatNumber(summary.enrolled ?? 0)}`, icon: <Users /> },
                        { value: 'graduated', label: `Graduated ${formatNumber(summary.graduated ?? 0)}`, icon: <GraduationCap /> },
                    ]}
                />
            )}

            <DataTable
                id={`students-${variant}`}
                columns={columns}
                data={students.data}
                sort={filters.sort}
                dir={filters.dir}
                onSortChange={(sort, dir) => apply({ sort, dir })}
                loading={busy}
                selectable
                onRowClick={(s, e) => {
                    if (e.target.closest('button, a, [role="menuitem"], [role="checkbox"]')) return;
                    router.visit(s.urls.show);
                }}
                bulkActions={(rows) => (
                    <Button
                        size="xs"
                        onClick={() =>
                            downloadCsv(`students-${new Date().toISOString().slice(0, 10)}.csv`, CSV_COLUMNS, rows)
                        }
                    >
                        <Download />
                        Export CSV
                    </Button>
                )}
                toolbar={
                    <>
                        <SearchInput value={filters.q} onChange={(q) => apply({ q })} placeholder="Search students…" />
                        <Select
                            size="sm"
                            className="min-w-[128px] flex-1 sm:w-[150px] sm:flex-none"
                            value={filters.class}
                            onChange={(v) => apply({ class: v, section: '' })}
                            options={classes.map((c) => ({ value: c.id, label: c.name }))}
                            clearable
                            clearLabel="All classes"
                            placeholder="All classes"
                        />
                        <Select
                            size="sm"
                            className="min-w-[128px] flex-1 sm:w-[130px] sm:flex-none"
                            value={filters.section}
                            onChange={(v) => apply({ section: v })}
                            options={sectionOptions}
                            clearable
                            clearLabel="All sections"
                            placeholder={filters.class ? 'All sections' : 'Section'}
                            disabled={!filters.class}
                        />
                        <Select
                            size="sm"
                            className="min-w-[128px] flex-1 sm:w-[140px] sm:flex-none"
                            value={filters.gender}
                            onChange={(v) => apply({ gender: v })}
                            options={[
                                { value: 'Male', label: `Male (${summary.male})` },
                                { value: 'Female', label: `Female (${summary.female})` },
                                ...(summary.unspecified ? [{ value: 'none', label: `Not recorded (${summary.unspecified})` }] : []),
                            ]}
                            clearable
                            clearLabel="Any gender"
                            placeholder="Any gender"
                        />
                        {hasFilters && (
                            <Button size="sm" variant="ghost" onClick={() => apply({ q: '', class: '', section: '', gender: '' })}>
                                <X />
                                Clear
                            </Button>
                        )}
                    </>
                }
                empty={
                    <EmptyState
                        icon={Users}
                        title={hasFilters ? 'No students match these filters' : graduated ? 'No graduated students yet' : 'No students enrolled yet'}
                        description={hasFilters ? 'Try a different search or clear the filters.' : undefined}
                        action={
                            hasFilters ? (
                                <Button size="sm" onClick={() => apply({ q: '', class: '', section: '', gender: '' })}>
                                    Clear filters
                                </Button>
                            ) : urls.create && !graduated ? (
                                <Button size="sm" variant="primary" asChild>
                                    <Link href={urls.create}>Admit the first student</Link>
                                </Button>
                            ) : null
                        }
                    />
                }
                footer={
                    <Pagination
                        meta={students.meta}
                        noun="students"
                        onPageChange={(page) => apply({ page })}
                        onPerPageChange={(per_page) => apply({ per_page })}
                    />
                }
            />
            {confirmDialog}
        </>
    );
}

StudentsIndex.layout = withAppLayout;

const CSV_COLUMNS = [
    { label: 'Name', value: (r) => r.name },
    { label: 'Admission no.', value: (r) => r.adm_no },
    { label: 'Class', value: (r) => r.class },
    { label: 'Section', value: (r) => r.section },
    { label: 'Gender', value: (r) => r.gender },
    { label: 'Email', value: (r) => r.email },
    { label: 'Phone', value: (r) => r.phone },
    { label: 'Parent', value: (r) => r.parent },
    { label: 'Year admitted', value: (r) => r.year_admitted },
];

function RowActions({ student, confirm }) {
    const u = student.urls;
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon-sm" aria-label={`Actions for ${student.name}`} onClick={(e) => e.stopPropagation()}>
                    <MoreHorizontal />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent onClick={(e) => e.stopPropagation()}>
                <DropdownMenuItem asChild>
                    <Link href={u.show}>
                        <Eye />
                        View profile
                    </Link>
                </DropdownMenuItem>
                {u.edit && (
                    <DropdownMenuItem asChild>
                        <Link href={u.edit}>
                            <Pencil />
                            Edit details
                        </Link>
                    </DropdownMenuItem>
                )}
                <DropdownMenuItem asChild>
                    <a href={u.marksheet}>
                        <ScrollText />
                        Marksheet
                    </a>
                </DropdownMenuItem>
                {(u.reset_pass || u.not_graduated) && <DropdownMenuSeparator />}
                {u.reset_pass && (
                    <DropdownMenuItem
                        onSelect={() =>
                            confirm({
                                title: 'Reset password?',
                                description: `${student.name}'s password will be reset to the default student password. They should change it after signing in.`,
                                confirmLabel: 'Reset password',
                                tone: 'primary',
                                url: u.reset_pass,
                            })
                        }
                    >
                        <KeyRound />
                        Reset password
                    </DropdownMenuItem>
                )}
                {u.not_graduated && (
                    <DropdownMenuItem
                        onSelect={() =>
                            confirm({
                                title: 'Mark as not graduated?',
                                description: `${student.name} will return to the enrolled list for the current year.`,
                                confirmLabel: 'Restore student',
                                tone: 'primary',
                                method: 'put',
                                url: u.not_graduated,
                            })
                        }
                    >
                        <Undo2 />
                        Not graduated
                    </DropdownMenuItem>
                )}
                {u.destroy && (
                    <>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            destructive
                            onSelect={() =>
                                confirm({
                                    title: `Delete ${student.name}?`,
                                    description: 'This permanently removes the student, their account and uploaded files. This cannot be undone.',
                                    confirmLabel: 'Delete student',
                                    method: 'delete',
                                    url: u.destroy,
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
    );
}
