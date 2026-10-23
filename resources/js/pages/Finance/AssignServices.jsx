import { useEffect, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { toast } from 'sonner';
import { withAppLayout } from '@/layouts/AppLayout';
import { ModuleHeader } from '@/components/app/module';
import { Panel } from '@/components/app/page';
import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/select';
import { ServicesPicker, EMPTY_SERVICES } from '@/components/students/services-picker';
import { submitForm } from '@/lib/http';

/** Pick a student and give them feeding, bus or extra-curricular (AssignServicesController). */
export default function AssignServices({ session, students, student, catalogue, urls }) {
    const [services, setServices] = useState({ ...EMPTY_SERVICES, ...(student?.services ?? {}) });
    const [busy, setBusy] = useState(false);
    useEffect(() => setServices({ ...EMPTY_SERVICES, ...(student?.services ?? {}) }), [student?.id]);

    const choose = (id) => router.get(urls.self, id ? { student: id } : {}, { preserveScroll: true });
    const save = async () => {
        if (services.bus_direction && !services.bus_route_id) {
            toast.error('Choose the bus location, or set the bus to “No bus”.');
            return;
        }
        setBusy(true);
        const r = await submitForm(urls.save, { student: student.id, services: JSON.stringify(services) });
        setBusy(false);
        if (r.ok) {
            toast.success(r.message || 'Saved.');
            router.reload({ only: ['student'], preserveScroll: true });
        } else {
            toast.error(r.message);
        }
    };

    return (
        <>
            <Head title="Assign services" />
            <div className="mx-auto flex w-full max-w-5xl flex-col gap-6">
                <ModuleHeader crumbs={['Finance', 'Services', 'Assign services']} title="Assign services" session={session} />

                <div className="w-full sm:max-w-md">
                    <Combobox value={student?.id ?? ''} onChange={choose} options={students} placeholder="Choose a student" searchPlaceholder="Search name or admission no." />
                </div>

                {student && (
                    <Panel
                        title={
                            <span>
                                {student.name}
                                <span className="ml-2 text-sm font-normal text-fg-muted">{student.class}</span>
                            </span>
                        }
                        actions={
                            <Button size="sm" variant="ghost" asChild>
                                <Link href={student.invoice}>Invoice</Link>
                            </Button>
                        }
                        footer={
                            <div className="flex justify-end">
                                <Button variant="primary" loading={busy} onClick={save}>
                                    Save
                                </Button>
                            </div>
                        }
                    >
                        <ServicesPicker catalogue={catalogue} value={services} onChange={setServices} mode="edit" classId={student.class_id} />
                    </Panel>
                )}
            </div>
        </>
    );
}

AssignServices.layout = withAppLayout;
