import { useState } from 'react';
import { router } from '@inertiajs/react';
import { toast } from 'sonner';
import { CheckCheck, History, Undo2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Modal } from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/input';
import { submitForm } from '@/lib/http';
import { formatDate } from '@/lib/utils';

const STATUS = {
    draft: ['Not submitted to HOD', 'neutral'],
    submitted: ['Waiting for HOD approval', 'warning'],
    approved: ['Approved by HOD', 'success'],
    returned: ['Returned for correction', 'danger'],
};

const ASK = {
    submit: { title: 'Submit these marks to the HOD?', label: 'Submit to HOD', note: 'Note for the HOD (optional)', tone: 'primary' },
    withdraw: { title: 'Withdraw these marks?', label: 'Withdraw', note: 'What will you correct? (optional)', tone: 'primary' },
    approve: { title: 'Approve these marks?', label: 'Approve', note: 'Note (optional)', tone: 'primary' },
    return: { title: 'Return for correction?', label: 'Return to teacher', note: 'What needs to be corrected?', tone: 'danger', required: true },
};

/**
 * Heads of department approval of one subject's marks (switch hod_marks_approval):
 * status, the last note, the next step for this user and the history.
 */
export function ApprovalBar({ approval, url, dirty }) {
    const [ask, setAsk] = useState(null);
    const [note, setNote] = useState('');
    const [busy, setBusy] = useState(false);
    const [showHistory, setShowHistory] = useState(false);
    const [label, tone] = STATUS[approval.status] ?? STATUS.draft;
    const a = ask ? ASK[ask] : null;

    const run = async () => {
        if (a.required && !note.trim()) return toast.error('Say what needs to be corrected.');
        setBusy(true);
        const r = await submitForm(url, { action: ask, note });
        setBusy(false);
        if (!r.ok) return toast.error(r.message);
        toast.success(r.message);
        setAsk(null);
        setNote('');
        router.reload({ preserveScroll: true });
        return undefined;
    };

    const open = (action) => {
        if (dirty && (action === 'submit')) return toast.error('Save the marks first.');
        setNote('');
        setAsk(action);
        return undefined;
    };

    return (
        <div className="panel mb-4 flex flex-col gap-2 px-4 py-3">
            <div className="flex flex-wrap items-center gap-3">
                <Badge tone={tone} dot>
                    {label}
                </Badge>
                {approval.hod && <span className="text-sm text-fg-muted">HOD: {approval.hod}</span>}
                <div className="ml-auto flex flex-wrap gap-2">
                    {approval.history.length > 0 && (
                        <Button size="sm" variant="ghost" onClick={() => setShowHistory(true)}>
                            <History />
                            History
                        </Button>
                    )}
                    {approval.canWithdraw && (
                        <Button size="sm" onClick={() => open('withdraw')}>
                            <Undo2 />
                            Withdraw to correct
                        </Button>
                    )}
                    {approval.canReturn && (
                        <Button size="sm" variant="danger" onClick={() => open('return')}>
                            <Undo2 />
                            Return for correction
                        </Button>
                    )}
                    {approval.canApprove && (
                        <Button size="sm" variant="primary" onClick={() => open('approve')}>
                            <CheckCheck />
                            Approve
                        </Button>
                    )}
                    {approval.canSubmit && (
                        <Button size="sm" variant="primary" onClick={() => open('submit')}>
                            Submit to HOD
                        </Button>
                    )}
                </div>
            </div>
            {approval.note && approval.status === 'returned' && (
                <p className="rounded-md bg-danger-soft px-3 py-2 text-sm text-danger-fg">
                    <span className="font-semibold">To correct:</span> {approval.note}
                </p>
            )}

            <Modal
                open={!!a}
                onOpenChange={(o) => !o && !busy && setAsk(null)}
                title={a?.title}
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setAsk(null)} disabled={busy}>
                            Cancel
                        </Button>
                        <Button variant={a?.tone === 'danger' ? 'danger' : 'primary'} onClick={run} loading={busy}>
                            {a?.label}
                        </Button>
                    </>
                }
            >
                <label className="flex flex-col gap-1.5 text-sm font-medium">
                    {a?.note}
                    <Textarea rows={3} value={note} onChange={(e) => setNote(e.target.value)} />
                </label>
            </Modal>

            <Modal open={showHistory} onOpenChange={setShowHistory} title="History of these marks">
                <ol className="flex flex-col gap-3">
                    {approval.history.map((h, i) => (
                        <li key={i} className="text-sm">
                            <div className="font-medium">{h.action}</div>
                            <div className="text-xs text-fg-muted">
                                {h.by} · {formatDate(h.at, 'd MMM yyyy, HH:mm')}
                            </div>
                            {h.note && <div className="mt-0.5 text-fg-muted">{h.note}</div>}
                        </li>
                    ))}
                </ol>
            </Modal>
        </div>
    );
}
