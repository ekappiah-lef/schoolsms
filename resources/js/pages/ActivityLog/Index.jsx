import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { withAppLayout } from '@/layouts/AppLayout';
import { ModuleHeader } from '@/components/app/module';
import { EmptyState } from '@/components/app/page';
import { Pagination } from '@/components/app/data-table';
import { DatePicker } from '@/components/ui/date-picker';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { formatDate } from '@/lib/utils';

/** Who did what and when (super admin). */
export default function ActivityLog({ filters, users, rows, meta, urls }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const go = (change) => router.get(urls.self, { ...filters, page: undefined, ...change }, { preserveScroll: true, preserveState: true });

    return (
        <>
            <Head title="Activity log" />
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6">
                <ModuleHeader crumbs={['Administration', 'Activity log']} title="Activity log" />

                <div className="flex flex-wrap items-end gap-3">
                    <div className="w-full sm:w-44">
                        <span className="mb-1.5 block text-sm font-medium">From</span>
                        <DatePicker value={filters.from} onChange={(v) => v && go({ from: v })} fromYear={2024} defaultMonth={new Date()} />
                    </div>
                    <div className="w-full sm:w-44">
                        <span className="mb-1.5 block text-sm font-medium">To</span>
                        <DatePicker value={filters.to} onChange={(v) => v && go({ to: v })} fromYear={2024} defaultMonth={new Date()} />
                    </div>
                    <div className="w-full sm:w-56">
                        <span className="mb-1.5 block text-sm font-medium">User</span>
                        <Select value={filters.user ?? ''} onChange={(v) => go({ user: v || null })} options={users} clearable clearLabel="Everyone" placeholder="Everyone" />
                    </div>
                    <form
                        className="w-full sm:w-64"
                        onSubmit={(e) => {
                            e.preventDefault();
                            go({ search: search || null });
                        }}
                    >
                        <span className="mb-1.5 block text-sm font-medium">Search</span>
                        <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Action, name, details" />
                    </form>
                </div>

                <div className="panel overflow-hidden">
                    {rows.length ? (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="border-b border-border bg-canvas text-2xs font-semibold uppercase text-fg-muted">
                                        <th className="h-9 px-4">When</th>
                                        <th className="h-9 px-3">Who</th>
                                        <th className="h-9 px-3">Action</th>
                                        <th className="h-9 px-4">Details</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {rows.map((r) => (
                                        <tr key={r.id} className="border-b border-border align-top last:border-0">
                                            <td className="tabular whitespace-nowrap px-4 py-2.5 text-fg-muted">{formatDate(r.at, 'd MMM yyyy, HH:mm:ss')}</td>
                                            <td className="px-3 py-2.5">
                                                <div className="font-medium">{r.user ?? '—'}</div>
                                                {r.role && <div className="text-xs text-fg-muted">{r.role}</div>}
                                            </td>
                                            <td className="px-3 py-2.5">
                                                {r.action}
                                                {r.status >= 400 && <span className="ml-1.5 text-xs text-danger-fg">({r.status})</span>}
                                            </td>
                                            <td className="max-w-xl px-4 py-2.5 text-fg-muted">{r.details}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <EmptyState compact title="Nothing in this period" />
                    )}
                    <Pagination meta={meta} onPageChange={(p) => router.get(urls.self, { ...filters, page: p }, { preserveScroll: true, preserveState: true })} noun="entries" />
                </div>
            </div>
        </>
    );
}

ActivityLog.layout = withAppLayout;
