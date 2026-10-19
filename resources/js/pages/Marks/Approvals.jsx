import { Head, Link, router } from '@inertiajs/react';
import { withAppLayout } from '@/layouts/AppLayout';
import { ModuleHeader, RegistryCard } from '@/components/app/module';
import { Badge } from '@/components/ui/badge';
import { Segmented } from '@/components/ui/tabs';
import { formatDate } from '@/lib/utils';

const STATUS = { submitted: ['Waiting', 'warning'], approved: ['Approved', 'success'], returned: ['Returned', 'danger'] };

/** Subject marks submitted to the HOD: open one to check it, then approve or return it for correction. */
export default function Approvals({ status, counts, rows, urls }) {
    return (
        <>
            <Head title="Marks approval" />
            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6">
                <ModuleHeader crumbs={['Examinations', 'Marks approval']} title="Marks approval" />

                <Segmented
                    value={status}
                    onChange={(v) => router.get(urls.self, { status: v }, { preserveScroll: true })}
                    options={[
                        { value: 'submitted', label: `Waiting (${counts.submitted})` },
                        { value: 'returned', label: `Returned (${counts.returned})` },
                        { value: 'approved', label: `Approved (${counts.approved})` },
                        { value: 'all', label: `All (${counts.all})` },
                    ]}
                />

                <RegistryCard
                    title="Subject marks"
                    rows={rows}
                    searchText={(r) => `${r.subject} ${r.class} ${r.exam} ${r.teacher ?? ''} ${r.department ?? ''}`}
                    exportName="marks-approval"
                    emptyText={status === 'submitted' ? 'Nothing is waiting for approval.' : 'No marks here.'}
                    columns={[
                        {
                            key: 'subject',
                            header: 'Subject',
                            sort: (r) => r.subject,
                            exportValue: (r) => r.subject,
                            cell: (r) => (
                                <Link href={r.url} className="font-semibold hover:text-primary">
                                    {r.subject}
                                </Link>
                            ),
                        },
                        { key: 'class', header: 'Class', sort: (r) => r.class, exportValue: (r) => r.class, cell: (r) => r.class },
                        { key: 'exam', header: 'Exam', sort: (r) => r.exam, exportValue: (r) => r.exam, cell: (r) => r.exam },
                        { key: 'teacher', header: 'Teacher', sort: (r) => r.teacher, exportValue: (r) => r.teacher, cell: (r) => r.teacher ?? '—' },
                        {
                            key: 'status',
                            header: 'Status',
                            sort: (r) => r.status,
                            exportValue: (r) => STATUS[r.status]?.[0],
                            cell: (r) => (
                                <div className="flex flex-col gap-0.5">
                                    <Badge tone={STATUS[r.status]?.[1]}>{STATUS[r.status]?.[0]}</Badge>
                                    {r.status === 'returned' && r.note && <span className="max-w-xs truncate text-xs text-fg-muted">{r.note}</span>}
                                </div>
                            ),
                        },
                        {
                            key: 'when',
                            header: 'Updated',
                            sort: (r) => r.reviewed_at ?? r.submitted_at,
                            exportValue: (r) => r.reviewed_at ?? r.submitted_at,
                            cell: (r) => <span className="text-sm text-fg-muted">{formatDate(r.reviewed_at ?? r.submitted_at, 'd MMM, HH:mm')}</span>,
                        },
                        {
                            key: 'open',
                            header: '',
                            cell: (r) => (
                                <Link href={r.url} className="text-sm font-medium text-primary hover:underline">
                                    {r.status === 'submitted' ? 'Check' : 'Open'}
                                </Link>
                            ),
                        },
                    ]}
                />
            </div>
        </>
    );
}

Approvals.layout = withAppLayout;
