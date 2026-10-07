import { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { Download, ExternalLink } from 'lucide-react';
import { withAppLayout } from '@/layouts/AppLayout';
import { InfoCallout, ModuleHeader } from '@/components/app/module';
import { EmptyState, Panel } from '@/components/app/page';
import { usePaged } from '@/components/app/data-table';
import { Button } from '@/components/ui/button';
import { Segmented } from '@/components/ui/tabs';
import { cn, formatDate, formatMoney } from '@/lib/utils';

const TONE = { cleared: 'bg-success-soft text-success-fg', flagged: 'bg-danger-soft text-danger-fg', not_found: 'bg-subtle text-fg-muted', not_checked: 'bg-warning-soft text-warning-fg' };

/** ClearEnroll inside the school system: the portal, students who left owing (to upload), and admission checks. */
export default function ClearEnroll({ portal, debtors, admissions, urls }) {
    const [tab, setTab] = useState('portal');
    const { shown, pager } = usePaged(debtors, 10, 'students');
    const total = debtors.reduce((a, d) => a + d.owed, 0);

    return (
        <>
            <Head title="ClearEnroll" />
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6">
                <ModuleHeader
                    crumbs={['Academics', 'ClearEnroll']}
                    title="ClearEnroll"
                    aside={
                        portal ? (
                            <Button asChild>
                                <a href={portal} target="_blank" rel="noreferrer">
                                    <ExternalLink />
                                    Open in new tab
                                </a>
                            </Button>
                        ) : null
                    }
                />
                <Segmented
                    value={tab}
                    onChange={setTab}
                    options={[
                        { value: 'portal', label: 'Portal' },
                        { value: 'debtors', label: `Left owing (${debtors.length})` },
                        { value: 'admissions', label: 'Admission checks' },
                    ]}
                />

                {tab === 'portal' &&
                    (portal ? (
                        <div className="overflow-hidden rounded-lg bg-surface shadow-card">
                            <iframe src={portal} title="ClearEnroll portal" className="h-[75vh] w-full border-0" />
                            <p className="border-t border-border px-4 py-2 text-xs text-fg-muted">
                                If the portal does not show here, use <span className="font-medium">Open in new tab</span>.
                            </p>
                        </div>
                    ) : (
                        <InfoCallout>
                            The ClearEnroll portal address has not been set.{' '}
                            {urls.settings ? (
                                <Link href={urls.settings} className="font-medium text-primary hover:underline">
                                    Add it in Settings
                                </Link>
                            ) : (
                                'Ask the super admin to add it in Settings.'
                            )}
                        </InfoCallout>
                    ))}

                {tab === 'debtors' && (
                    <Panel
                        title="Students who left owing"
                        description={debtors.length ? `${debtors.length} students · ${formatMoney(total)} owed. Download the file and upload it on ClearEnroll.` : undefined}
                        flush
                        actions={
                            debtors.length ? (
                                <Button size="sm" variant="primary" asChild>
                                    <a href={urls.export}>
                                        <Download />
                                        Download for ClearEnroll
                                    </a>
                                </Button>
                            ) : null
                        }
                    >
                        {debtors.length ? (
                            <div className="scrollbar-thin overflow-x-auto">
                                <table className="w-full text-left text-sm">
                                    <thead>
                                        <tr className="border-b border-border bg-canvas text-2xs font-semibold uppercase text-fg-muted">
                                            <th className="h-10 px-5">Student</th>
                                            <th className="h-10 px-3">Last class</th>
                                            <th className="h-10 px-3">Left</th>
                                            <th className="h-10 px-3">Parent</th>
                                            <th className="h-10 px-5 text-right">Owed</th>
                                        </tr>
                                    </thead>
                                    <tbody className="tabular">
                                        {shown.map((d) => (
                                            <tr key={d.id} className="border-b border-border last:border-0">
                                                <td className="px-5 py-2.5">
                                                    <Link href={d.url} className="font-medium hover:text-primary hover:underline">
                                                        {d.name}
                                                    </Link>
                                                    <div className="text-xs text-fg-muted">{d.adm_no}</div>
                                                </td>
                                                <td className="px-3 py-2.5">{d.class || '—'}</td>
                                                <td className="px-3 py-2.5 text-fg-muted">{d.left}</td>
                                                <td className="px-3 py-2.5">
                                                    <div>{d.parent || '—'}</div>
                                                    <div className="text-xs text-fg-muted">{d.phone}</div>
                                                </td>
                                                <td className="px-5 py-2.5 text-right font-semibold text-danger-fg">{formatMoney(d.owed)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                                {pager}
                            </div>
                        ) : (
                            <EmptyState compact title="No student has left owing" description="Students who have completed (graduated) with unpaid fees appear here." />
                        )}
                    </Panel>
                )}

                {tab === 'admissions' && (
                    <Panel title="ClearEnroll checks made at admission" flush>
                        {admissions.length ? (
                            <ul className="divide-y divide-border">
                                {admissions.map((a, i) => (
                                    <li key={i} className="flex items-center gap-3 px-5 py-2.5 text-sm">
                                        <span className="flex-1 font-medium">{a.name}</span>
                                        <span className={cn('rounded px-1.5 py-0.5 text-2xs font-semibold uppercase', TONE[a.key])}>{a.status}</span>
                                        <span className="tabular w-24 text-right text-xs text-fg-muted">{a.date ? formatDate(a.date, 'dd/MM/yyyy') : ''}</span>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <EmptyState compact title="No checks recorded yet" description="The result of the ClearEnroll check is recorded on Admit student." />
                        )}
                    </Panel>
                )}
            </div>
        </>
    );
}

ClearEnroll.layout = withAppLayout;
