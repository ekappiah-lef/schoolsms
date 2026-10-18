import { Panel } from '@/components/app/page';
import { Badge } from '@/components/ui/badge';
import { formatDate } from '@/lib/utils';

/** A school's state in the Control Centre. */
export function StatusBadge({ status, healthy }) {
    if (status === 'creating') return <Badge tone="info">Being created…</Badge>;
    if (status === 'setting_up') return <Badge tone="info">Setting up…</Badge>;
    if (status === 'error') return <Badge tone="danger">Problem</Badge>;
    if (healthy === false) return <Badge tone="danger">Down</Badge>;
    return <Badge tone="success">Running</Badge>;
}

/** The jobs the Control Centre asked the server to do, newest first. */
export function JobLog({ jobs, showSchool = false }) {
    return (
        <Panel title="Recent jobs" flush>
            <table className="w-full text-sm">
                <tbody>
                    {jobs.map((j) => (
                        <tr key={j.id} className="border-b border-border last:border-0">
                            <td className="whitespace-nowrap px-5 py-2 text-fg-muted">{formatDate(j.at, 'd MMM HH:mm')}</td>
                            <td className="px-3 py-2 font-medium">{j.type}</td>
                            {showSchool && <td className="px-3 py-2 text-fg-muted">{j.school || ''}</td>}
                            <td className="px-3 py-2">
                                {j.status === 'pending' ? <Badge tone="info">Working…</Badge> : j.status === 'done' ? <Badge tone="success">Done</Badge> : <Badge tone="danger">Failed</Badge>}
                            </td>
                            <td className="px-5 py-2 text-xs text-danger-fg">{j.error || ''}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </Panel>
    );
}
