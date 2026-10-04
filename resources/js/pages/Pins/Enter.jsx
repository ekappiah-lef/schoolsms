import { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { KeyRound } from 'lucide-react';
import { withAppLayout } from '@/layouts/AppLayout';
import { Field, ModuleHeader, fieldInput } from '@/components/app/module';
import { Panel } from '@/components/app/page';
import { Button } from '@/components/ui/button';

const PATTERN = /^[A-Z0-9]{5}-[A-Z0-9]{5}-[A-Z0-9]{6}$/;

/** Parents and students enter an exam pin before they can see a student's results. */
export default function PinEnter({ student, urls }) {
    const errors = usePage().props.errors ?? {};
    const [code, setCode] = useState('');
    const [processing, setProcessing] = useState(false);
    const ok = PATTERN.test(code);

    const submit = (e) => {
        e.preventDefault();
        if (!ok) return;
        router.post(urls.verify, { pin_code: code }, { onStart: () => setProcessing(true), onFinish: () => setProcessing(false) });
    };

    return (
        <>
            <Head title="Enter exam pin" />
            <form onSubmit={submit} className="mx-auto flex w-full max-w-xl flex-col gap-6">
                <ModuleHeader crumbs={['Results', 'Exam pin']} title="Enter exam pin" description={`A pin is needed to view ${student}'s results.`} />
                <Panel
                    title={student}
                    footer={
                        <div className="flex justify-end">
                            <Button type="submit" variant="primary" disabled={!ok || processing}>
                                <KeyRound />
                                {processing ? 'Checking…' : 'View results'}
                            </Button>
                        </div>
                    }
                >
                    <Field label="Exam pin" required error={errors.pin_code} hint="Format XXXXX-XXXXX-XXXXXX. Get a pin from the school office.">
                        <input
                            className={`${fieldInput} font-mono uppercase tracking-wider`}
                            value={code}
                            onChange={(e) => setCode(e.target.value.toUpperCase().replace(/[^A-Z0-9-]/g, '').slice(0, 18))}
                            placeholder="XXXXX-XXXXX-XXXXXX"
                            autoComplete="off"
                            autoFocus
                        />
                    </Field>
                </Panel>
            </form>
        </>
    );
}

PinEnter.layout = withAppLayout;
