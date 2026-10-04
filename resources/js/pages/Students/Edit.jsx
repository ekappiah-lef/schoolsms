import { Head, router } from '@inertiajs/react';
import { withAppLayout } from '@/layouts/AppLayout';
import { PageHeader } from '@/components/app/page';
import { Avatar } from '@/components/ui/avatar';
import StudentForm from '@/components/students/student-form';

export default function StudentEdit({ student, options, urls }) {
    return (
        <>
            <Head title={`Edit ${student.name}`} />
            <PageHeader
                breadcrumbs={[{ label: 'Academics' }, { label: 'Students' }, { label: student.name, href: urls.show }, { label: 'Edit' }]}
                title="Edit student"
                meta={
                    <span className="inline-flex items-center gap-2 text-base text-fg-muted">
                        <Avatar src={student.photo} name={student.name} size="sm" />
                        {student.name} · <span className="tabular">{student.adm_no}</span>
                    </span>
                }
            />
            <div className="max-w-6xl">
                <StudentForm
                    mode="edit"
                    options={options}
                    initial={{ ...student.values, adm_no_display: student.adm_no }}
                    initialSections={student.sections}
                    initialLgas={student.lgas}
                    currentPhoto={student.photo}
                    submitUrl={urls.update}
                    onCancel={() => router.visit(urls.show)}
                    onSaved={() => router.visit(urls.show)}
                />
            </div>
        </>
    );
}

StudentEdit.layout = withAppLayout;
