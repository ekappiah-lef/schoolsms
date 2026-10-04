import { Head, Link } from '@inertiajs/react';
import { BookOpen, Mail, MapPin, Pencil, Phone } from 'lucide-react';
import { withAppLayout } from '@/layouts/AppLayout';
import { Breadcrumbs, DescriptionList, EmptyState, Panel } from '@/components/app/page';
import { Avatar } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/utils';

/** Profile of a staff member: administrator, teacher, accountant, librarian… */
export default function UserShow({ user, subjects, urls }) {
    return (
        <>
            <Head title={user.name} />
            <div className="mb-5">
                <Breadcrumbs items={[{ label: 'People' }, urls.back ? { label: 'Users', href: urls.back } : { label: 'Users' }, { label: 'Profile' }]} />
                <div className="mt-3 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div className="flex min-w-0 items-center gap-4">
                        <Avatar src={user.photo} name={user.name} size="xl" />
                        <div className="min-w-0">
                            <div className="flex flex-wrap items-center gap-2">
                                <h1 className="truncate text-2xl font-semibold">{user.name}</h1>
                                <Badge tone="info">{user.type}</Badge>
                            </div>
                            <div className="mt-1 text-base text-fg-muted">{user.staff_code || user.username}</div>
                        </div>
                    </div>
                    {urls.edit && (
                        <Button variant="primary" asChild>
                            <Link href={urls.edit}>
                                <Pencil />
                                Edit
                            </Link>
                        </Button>
                    )}
                </div>
            </div>

            <div className="grid gap-6 xl:grid-cols-3">
                <div className="space-y-6">
                    <Panel title="Contact">
                        <ul className="space-y-2 text-sm">
                            <Row icon={Phone} value={[user.phone, user.phone2].filter(Boolean).join(' · ')} href={user.phone ? `tel:${user.phone}` : null} />
                            <Row icon={Mail} value={user.email} href={user.email ? `mailto:${user.email}` : null} />
                            <Row icon={MapPin} value={user.address} />
                        </ul>
                    </Panel>
                    {user.type === 'Teacher' && (
                        <Panel title={`Subjects (${subjects.length})`}>
                            {subjects.length ? (
                                <ul className="-my-1 divide-y divide-border text-sm">
                                    {subjects.map((s, i) => (
                                        <li key={i} className="flex justify-between gap-3 py-2">
                                            <span className="font-medium">{s.name}</span>
                                            <span className="text-fg-muted">{s.class}</span>
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <EmptyState compact icon={BookOpen} title="No subjects assigned" />
                            )}
                        </Panel>
                    )}
                </div>
                <div className="xl:col-span-2">
                    <Panel title="Details">
                        <DescriptionList
                            columns={2}
                            items={[
                                { label: 'Gender', value: user.gender },
                                { label: 'Date of birth', value: user.dob ? formatDate(user.dob, 'dd/MM/yyyy') : null },
                                { label: 'Date of employment', value: user.emp_date ? formatDate(user.emp_date, 'dd/MM/yyyy') : null },
                                { label: 'Login ID', value: user.username },
                                { label: 'Blood group', value: user.blood_group },
                                { label: 'Nationality', value: user.nationality },
                                { label: 'State', value: user.state },
                                { label: 'LGA', value: user.lga },
                            ]}
                        />
                    </Panel>
                </div>
            </div>
        </>
    );
}

UserShow.layout = withAppLayout;

function Row({ icon: Icon, value, href }) {
    if (!value) return null;
    return (
        <li className="flex items-start gap-2.5">
            <Icon className="mt-0.5 size-4 shrink-0 text-fg-subtle" />
            {href ? (
                <a href={href} className="break-all hover:text-primary">
                    {value}
                </a>
            ) : (
                <span className="break-words">{value}</span>
            )}
        </li>
    );
}
