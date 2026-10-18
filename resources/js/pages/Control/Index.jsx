import { useEffect } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { toast } from 'sonner';
import { ExternalLink, Link2, Plus } from 'lucide-react';
import { withAppLayout } from '@/layouts/AppLayout';
import { ModuleHeader } from '@/components/app/module';
import { EmptyState, Panel } from '@/components/app/page';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { submitForm } from '@/lib/http';
import { formatDate } from '@/lib/utils';
import { JobLog, StatusBadge } from './parts';

/** LEF Control Centre: every school, with its version, health and last backup. */
export default function ControlIndex({ schools, unregistered, jobs, urls }) {
    const busy = jobs.some((j) => j.status === 'pending') || schools.some((s) => ['creating', 'setting_up'].includes(s.status));
    // While something is being created or changed, refresh every few seconds.
    useEffect(() => {
        if (!busy) return undefined;
        const t = setInterval(() => router.reload({ only: ['schools', 'jobs', 'unregistered'] }), 5000);
        return () => clearInterval(t);
    }, [busy]);

    const register = async (slug) => {
        const r = await submitForm(urls.register, { slug });
        r.ok ? toast.success(r.message) : toast.error(r.message);
        router.reload();
    };

    return (
        <>
            <Head title="Schools" />
            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6">
                <ModuleHeader
                    crumbs={['Control Centre', 'Schools']}
                    title="Schools"
                    aside={
                        <Button variant="primary" asChild>
                            <Link href={urls.create}>
                                <Plus />
                                New school
                            </Link>
                        </Button>
                    }
                />

                {unregistered.length > 0 && (
                    <Panel title="Existing schools not yet connected">
                        <div className="flex flex-wrap gap-2">
                            {unregistered.map((u) => (
                                <Button key={u.slug} onClick={() => register(u.slug)}>
                                    <Link2 />
                                    Connect {u.name}
                                </Button>
                            ))}
                        </div>
                    </Panel>
                )}

                <Panel title={`${schools.length} ${schools.length === 1 ? 'school' : 'schools'}`} flush>
                    {schools.length ? (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-border bg-canvas text-left text-2xs font-semibold uppercase text-fg-muted">
                                        <th className="h-9 px-5">School</th>
                                        <th className="h-9 px-3">Status</th>
                                        <th className="h-9 px-3">Version</th>
                                        <th className="h-9 px-3 text-right">Students</th>
                                        <th className="h-9 px-3">Messages</th>
                                        <th className="h-9 px-5">Last backup</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {schools.map((s) => (
                                        <tr key={s.slug} onClick={() => router.visit(s.urls.show)} className="cursor-pointer border-b border-border last:border-0 hover:bg-muted/50">
                                            <td className="px-5 py-3">
                                                <div className="font-semibold">{s.name}</div>
                                                {s.url && (
                                                    <a href={s.url} target="_blank" rel="noreferrer" onClick={(e) => e.stopPropagation()} className="inline-flex items-center gap-1 text-xs text-fg-muted hover:text-primary">
                                                        {s.url.replace('https://', '')}
                                                        <ExternalLink className="size-3" />
                                                    </a>
                                                )}
                                            </td>
                                            <td className="px-3 py-3">
                                                <StatusBadge status={s.status} healthy={s.healthy} />
                                            </td>
                                            <td className="px-3 py-3 font-mono text-xs">{s.version || '—'}</td>
                                            <td className="tabular px-3 py-3 text-right">{s.students ?? '—'}</td>
                                            <td className="px-3 py-3">{s.demo === null || s.demo === undefined ? '—' : s.demo ? <Badge tone="warning">Demo mode</Badge> : <Badge tone="success">Live</Badge>}</td>
                                            <td className="px-5 py-3 text-fg-muted">{s.last_backup ? formatDate(s.last_backup, 'd MMM yyyy HH:mm') : '—'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <EmptyState compact title="No schools yet" action={<Button asChild><Link href={urls.create}>Create the first school</Link></Button>} />
                    )}
                </Panel>

                {jobs.length > 0 && <JobLog jobs={jobs} showSchool />}
            </div>
        </>
    );
}

ControlIndex.layout = withAppLayout;
