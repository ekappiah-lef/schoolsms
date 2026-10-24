import { Head } from '@inertiajs/react';
import { withAppLayout } from '@/layouts/AppLayout';
import { ModuleHeader } from '@/components/app/module';
import { ClassTypes } from '@/components/app/class-types';

/** Class types (Preschool, Lower Grade, Junior High …) on their own page (ClassTypeController). */
export default function ClassTypesPage({ session, types, urls, canDelete }) {
    return (
        <>
            <Head title="Class types" />
            <div className="mx-auto flex w-full max-w-5xl flex-col gap-6">
                <ModuleHeader crumbs={['Administration', 'Classes', 'Class types']} title="Class types" session={session} />
                <ClassTypes types={types} urls={urls} canDelete={canDelete} />
            </div>
        </>
    );
}

ClassTypesPage.layout = withAppLayout;
