import { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { CircleCheck, Mail, MessageSquare, UserPlus } from 'lucide-react';
import { withAppLayout } from '@/layouts/AppLayout';
import { InfoCallout, ModuleHeader, ModuleTabs } from '@/components/app/module';
import { Button } from '@/components/ui/button';
import StudentForm from '@/components/students/student-form';
import { NoticeList } from '@/components/students/notice-list';

export default function StudentCreate({ options, urls }) {
    const { app } = usePage().props;
    const [done, setDone] = useState(null);
    const [formKey, setFormKey] = useState(0);

    return (
        <>
            <Head title="Admit student" />
            <div className="mx-auto flex w-full max-w-5xl flex-col gap-6">
                <ModuleHeader
                    crumbs={['Academics', 'Admissions']}
                    title="Student Admission"
                    session={app.session}
                />
                <ModuleTabs value="create" onChange={(t) => t === 'list' && router.visit(urls.index)} createLabel="Student" listLabel="View Students" />

                {done ? (
                    <Admitted
                        result={done}
                        onAnother={() => {
                            setDone(null);
                            setFormKey((k) => k + 1);
                            window.scrollTo({ top: 0 });
                        }}
                        onList={() => router.visit(urls.index)}
                    />
                ) : (
                    <>
                        <StudentForm
                            key={formKey}
                            mode="create"
                            options={options}
                            submitUrl={urls.store}
                            onCancel={() => router.visit(urls.index)}
                            onSaved={(data, resp) => {
                                setDone({ name: [data.first_name, data.middle_name, data.last_name].filter(Boolean).join(' '), ...resp });
                                window.scrollTo({ top: 0 });
                            }}
                        />
                        <InfoCallout>The student signs in with the generated admission number and the default student password, and should change it on first login.</InfoCallout>
                    </>
                )}
            </div>
        </>
    );
}

function Admitted({ result, onAnother, onList }) {
    const notices = result.notices ?? [];
    const sent = notices.filter((n) => n.status === 'sent').length;

    return (
        <div className="panel overflow-hidden">
            <div className="flex flex-col gap-4 p-6 sm:flex-row sm:items-start">
                <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-success-soft text-success">
                    <CircleCheck className="size-5" />
                </span>
                <div className="min-w-0 flex-1">
                    <h2 className="text-lg font-semibold">{result.name} has been admitted</h2>
                    <p className="mt-1 text-sm text-fg-muted">The student record and portal account were created, and school fees and optional services were billed for this term.</p>
                    {result.parent_login && (
                        <p className="mt-3 rounded-lg bg-muted/60 px-3 py-2 text-sm">
                            <UserPlus className="mr-1.5 inline size-4 text-fg-muted" />
                            New parent account: username <span className="font-medium tabular">{result.parent_login}</span>, default password <span className="font-medium">parent</span>. Ask them to change it after signing in.
                        </p>
                    )}
                </div>
            </div>

            <div className="border-t border-border px-6 py-5">
                <div className="mb-3 flex items-center gap-2 text-sm font-medium">
                    <Mail className="size-4 text-fg-subtle" />
                    <MessageSquare className="size-4 text-fg-subtle" />
                    Admission notices
                    {notices.length > 0 && (
                        <span className="text-fg-muted">
                            · {sent} of {notices.length} delivered
                        </span>
                    )}
                </div>
                {notices.length ? (
                    <NoticeList notices={notices} />
                ) : (
                    <p className="text-sm text-fg-muted">No notices were sent. You can send them later from the student’s profile.</p>
                )}
            </div>

            <div className="flex flex-wrap justify-end gap-2 border-t border-border bg-muted/40 px-6 py-3">
                <Button variant="ghost" onClick={onList}>
                    All students
                </Button>
                <Button variant="secondary" onClick={onAnother}>
                    Admit another
                </Button>
                {result.student_url && (
                    <Button variant="primary" onClick={() => router.visit(result.student_url)}>
                        Open profile
                    </Button>
                )}
            </div>
        </div>
    );
}

StudentCreate.layout = withAppLayout;
