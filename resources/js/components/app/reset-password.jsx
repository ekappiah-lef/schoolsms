import { useState } from 'react';
import { Eye, EyeOff } from 'lucide-react';
import { toast } from 'sonner';
import { Modal } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { fieldInput } from '@/components/app/module';
import { submitForm } from '@/lib/http';
import { cn } from '@/lib/utils';

/**
 * Set a user's password: the person resetting types the new password and can see it.
 * const [resetPassword, resetDialog] = useResetPassword();  resetPassword({ name, url });  …{resetDialog}
 */
export function useResetPassword() {
    const [target, setTarget] = useState(null);
    const [password, setPassword] = useState('');
    const [show, setShow] = useState(true);
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);
    const [sms, setSms] = useState(true);

    const open = (t) => {
        setTarget(t);
        setPassword('');
        setShow(true);
        setError('');
        setSms(true);
    };
    const close = () => !busy && setTarget(null);

    const save = async (e) => {
        e?.preventDefault();
        if (password.length < 6) {
            setError('Use at least 6 characters.');
            return;
        }
        setBusy(true);
        const r = await submitForm(target.url, { password, send_sms: sms ? 1 : 0 }, { method: 'put' });
        setBusy(false);
        if (r.ok) {
            (r.message?.includes('SMS not sent') ? toast.warning : toast.success)(r.message);
            setTarget(null);
        } else {
            setError(r.errors?.password || r.message);
        }
    };

    const dialog = (
        <Modal
            open={!!target}
            onOpenChange={(o) => !o && close()}
            title={target ? `New password for ${target.name}` : ''}
            footer={
                <>
                    <Button onClick={close} disabled={busy}>
                        Cancel
                    </Button>
                    <Button variant="primary" onClick={save} disabled={busy || !password}>
                        {busy ? 'Saving…' : 'Set password'}
                    </Button>
                </>
            }
        >
            <form onSubmit={save} className="flex flex-col gap-1.5">
                <label htmlFor="new-password" className="text-sm font-medium text-fg">
                    New password
                </label>
                <div className="relative">
                    <input
                        id="new-password"
                        className={cn(fieldInput, 'pr-11')}
                        type={show ? 'text' : 'password'}
                        autoComplete="new-password"
                        autoFocus
                        value={password}
                        onChange={(e) => {
                            setPassword(e.target.value);
                            setError('');
                        }}
                        aria-invalid={!!error || undefined}
                    />
                    <button
                        type="button"
                        onClick={() => setShow((s) => !s)}
                        className="absolute inset-y-0 right-0 flex items-center px-3 text-fg-subtle hover:text-fg"
                        aria-label={show ? 'Hide password' : 'Show password'}
                    >
                        {show ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                    </button>
                </div>
                {error && <p className="text-xs text-danger">{error}</p>}
                <label className="mt-2 flex cursor-pointer items-center gap-2 text-sm">
                    <input type="checkbox" className="size-4" checked={sms} onChange={(e) => setSms(e.target.checked)} />
                    Send by SMS
                </label>
            </form>
        </Modal>
    );

    return [open, dialog];
}
