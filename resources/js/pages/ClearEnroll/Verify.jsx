import { useState } from 'react';
import { Head } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { withAppLayout } from '@/layouts/AppLayout';
import { InfoCallout, ModuleHeader, fieldInput } from '@/components/app/module';
import { EmptyState, Panel } from '@/components/app/page';
import { Button } from '@/components/ui/button';
import http from '@/lib/http';
import { cn, formatDate, formatMoney } from '@/lib/utils';

const STATUS = {
    FLAGGED: ['Flagged', 'bg-danger-soft text-danger-fg', 'Owes fees at a school. Hold the admission until it is cleared.'],
    CLEAR: ['Cleared', 'bg-success-soft text-success-fg', 'Found on ClearEnroll with no unpaid fees.'],
    NOT_FOUND: ['Not found', 'bg-subtle text-fg-muted', 'No record on ClearEnroll.'],
    ENGAGED: ['Engaged', 'bg-success-soft text-success-fg', 'Teacher found, not flagged by any school.'],
};
const TEACHER_STATUS = { FLAGGED: 'Flagged', ENGAGED: 'Engaged', CLEARED: 'Cleared' };

/** Search ClearEnroll for a student or a teacher (through ClearEnroll's API). */
export default function ClearEnrollVerify({ kind, connected, urls }) {
    const [q, setQ] = useState('');
    const [busy, setBusy] = useState(false);
    const [res, setRes] = useState(null);
    const [error, setError] = useState('');
    const isTeacher = kind === 'teacher';

    const search = async (e) => {
        e?.preventDefault();
        if (q.trim().length < 2) return;
        setBusy(true);
        setError('');
        try {
            const { data } = await http.post(urls.search, { kind, query: q.trim() });
            setRes(data);
        } catch (err) {
            setRes(null);
            setError(err.response?.data?.message || 'ClearEnroll could not search right now.');
        }
        setBusy(false);
    };
    const st = res ? STATUS[res.status] ?? [res.status, 'bg-subtle text-fg-muted', ''] : null;

    return (
        <>
            <Head title={isTeacher ? 'Verify teacher' : 'Verify student'} />
            <div className="mx-auto flex w-full max-w-5xl flex-col gap-6">
                <ModuleHeader crumbs={['ClearEnroll', isTeacher ? 'Verify teacher' : 'Verify student']} title={isTeacher ? 'Verify teacher' : 'Verify student'} />

                {!connected && <InfoCallout>ClearEnroll is not connected yet. Ask the administrator to add the ClearEnroll key to the server settings.</InfoCallout>}

                <form onSubmit={search} className="flex flex-col gap-3 rounded-lg bg-surface p-5 shadow-card sm:flex-row">
                    <input
                        className={cn(fieldInput, 'flex-1')}
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        placeholder={isTeacher ? 'Teacher’s name, phone or Ghana Card number' : 'Student’s name, parent’s name or phone, Ghana Card, or date of birth (dd-mm-yyyy)'}
                        disabled={!connected}
                        autoFocus
                    />
                    <Button type="submit" variant="primary" disabled={!connected || busy || q.trim().length < 2}>
                        <Search />
                        {busy ? 'Searching…' : 'Search ClearEnroll'}
                    </Button>
                </form>

                {error && <InfoCallout>{error}</InfoCallout>}

                {res && (
                    <>
                        <div className={cn('flex flex-col gap-1 rounded-lg px-5 py-4', st[1])}>
                            <span className="text-lg font-semibold">{st[0]}</span>
                            <span className="text-sm">{st[2]}</span>
                        </div>

                        {!isTeacher && (res.flags ?? []).length > 0 && (
                            <Panel title="Unpaid fees reported" flush>
                                <table className="w-full text-left text-sm">
                                    <thead>
                                        <tr className="border-b border-border bg-canvas text-2xs font-semibold uppercase text-fg-muted">
                                            <th className="h-9 px-5">Student</th>
                                            <th className="h-9 px-3">Reported by</th>
                                            <th className="h-9 px-3">Parent</th>
                                            <th className="h-9 px-3">Reason</th>
                                            <th className="h-9 px-5 text-right">Owed</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {res.flags.map((f) => (
                                            <tr key={f.id} className="border-b border-border last:border-0">
                                                <td className="px-5 py-2.5 font-medium">{f.student}</td>
                                                <td className="px-3 py-2.5">{f.reported_by}</td>
                                                <td className="px-3 py-2.5">
                                                    {f.parent || '—'}
                                                    <div className="text-xs text-fg-muted">{f.parent_phone}</div>
                                                </td>
                                                <td className="px-3 py-2.5 text-fg-muted">{f.reason}</td>
                                                <td className="tabular px-5 py-2.5 text-right font-semibold text-danger-fg">
                                                    {f.currency} {formatMoney(Number(f.amount_owed))}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </Panel>
                        )}

                        {!isTeacher && (res.students ?? []).length > 0 && (
                            <Panel title={`Students found (${res.students.length})`} flush>
                                <ul className="divide-y divide-border">
                                    {res.students.map((s) => (
                                        <li key={s.id} className="flex flex-wrap items-center gap-x-6 gap-y-1 px-5 py-3 text-sm">
                                            <span className="font-medium">{s.name}</span>
                                            <span className="text-fg-muted">{s.date_of_birth ? formatDate(s.date_of_birth, 'dd/MM/yyyy') : ''}</span>
                                            <span className="text-fg-muted">{s.gender}</span>
                                            <span className="text-fg-muted">{s.school}</span>
                                            {s.parent_name && (
                                                <span className="ml-auto text-fg-muted">
                                                    Parent: {s.parent_name} {s.parent_phone ? `· ${s.parent_phone}` : ''}
                                                </span>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            </Panel>
                        )}

                        {isTeacher && (res.teachers ?? []).length > 0 && (
                            <Panel title={`Teachers found (${res.teachers.length})`} flush>
                                <ul className="divide-y divide-border">
                                    {res.teachers.map((t) => (
                                        <li key={t.id} className="flex flex-col gap-1 px-5 py-3 text-sm">
                                            <div className="flex flex-wrap items-center gap-3">
                                                <span className="font-medium">{[t.first_name, t.other_names, t.last_name].filter(Boolean).join(' ')}</span>
                                                <span className={cn('rounded px-1.5 py-0.5 text-2xs font-semibold uppercase', t.status === 'FLAGGED' ? 'bg-danger-soft text-danger-fg' : 'bg-success-soft text-success-fg')}>
                                                    {TEACHER_STATUS[t.status] ?? t.status}
                                                </span>
                                                <span className="text-fg-muted">{t.qualification}</span>
                                            </div>
                                            {t.status === 'FLAGGED' && (
                                                <div className="text-fg-muted">
                                                    Flagged by {t.school}
                                                    {t.reason ? `: ${t.reason}` : ''}
                                                </div>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            </Panel>
                        )}

                        {res.status === 'NOT_FOUND' && <EmptyState compact title="No record on ClearEnroll" description="Check the spelling or try the parent’s phone number." />}
                    </>
                )}
            </div>
        </>
    );
}

ClearEnrollVerify.layout = withAppLayout;
