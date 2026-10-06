import { useEffect, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import {
    BookOpenCheck,
    KeyRound,
    LockKeyhole,
    Mail,
    MapPin,
    MoreHorizontal,
    Pencil,
    Phone,
    Receipt,
    ScrollText,
    UserRound,
    Wallet,
} from 'lucide-react';
import { withAppLayout } from '@/layouts/AppLayout';
import { DescriptionList, EmptyState, Panel } from '@/components/app/page';
import { Breadcrumbs } from '@/components/app/page';
import { Meter } from '@/components/app/charts';
import { useConfirmAction } from '@/components/app/confirm-action';
import { Avatar } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Segmented, Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Skeleton } from '@/components/ui/skeleton';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { cn, formatDate, formatMoney, ordinal, parseDate } from '@/lib/utils';
import { submitForm } from '@/lib/http';
import { toast } from 'sonner';
import { FeeTotals, OptionalFeesInvoice, PaymentHistory, SchoolFeesInvoice, TermInvoice, sumRows } from '@/components/fees/fee-statement';
import { NoticeList } from '@/components/students/notice-list';
import { differenceInYears } from 'date-fns';

export default function StudentShow({ student, guardian, siblings = [], urls, tabs, fees, invoice, notices, results, latestResult, resultsLocked }) {
    const initialTab = new URLSearchParams(window.location.search).get('tab') || 'overview';
    const [tab, setTab] = useState(initialTab);
    const [confirm, confirmDialog] = useConfirmAction();

    const changeTab = (value) => {
        setTab(value);
        const params = value === 'overview' ? {} : { tab: value };
        // Only the results tab needs extra data from the server; the rest just update the URL.
        router.get(window.location.pathname, params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: value === 'results' && !results ? ['results'] : ['student'],
        });
    };

    const age = (() => {
        const d = parseDate(student.dob);
        return d ? differenceInYears(new Date(), d) : null;
    })();

    return (
        <>
            <Head title={student.name} />

            {/* Profile header */}
            <div className="mb-5">
                <Breadcrumbs
                    items={[
                        { label: 'Academics' },
                        urls.back ? { label: student.status === 'graduated' ? 'Graduated' : 'Students', href: urls.back } : { label: 'Students' },
                        { label: 'Profile' },
                    ]}
                />
                <div className="mt-3 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div className="flex min-w-0 items-center gap-4">
                        <Avatar src={student.photo} name={student.name} size="3xl" />
                        <div className="min-w-0">
                            <div className="flex flex-wrap items-center gap-2">
                                <h1 className="truncate text-2xl font-semibold">{student.name}</h1>
                                {student.status === 'graduated' ? (
                                    <Badge tone="info" dot>
                                        Graduated {student.grad_date ?? ''}
                                    </Badge>
                                ) : (
                                    <Badge >
                                    </Badge>
                                )}
                            </div>
                            <div className="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-base text-fg-muted">
                                <span className="tabular">{student.adm_no}</span>
                                <span className="text-fg-subtle">•</span>
                                <span>
                                    {student.class} {student.section}
                                </span>
                                {student.session && (
                                    <>
                                        <span className="text-fg-subtle">•</span>
                                        <span>{student.session}</span>
                                    </>
                                )}
                            </div>
                        </div>
                    </div>
                    <div className="flex shrink-0 flex-wrap items-center gap-2">
                        <Button asChild>
                            <a href={urls.marksheet}>
                                <ScrollText />
                                Marksheet
                            </a>
                        </Button>
                        {urls.invoice && (
                            <Button asChild>
                                <a href={urls.invoice}>
                                    <Wallet />
                                    Payments
                                </a>
                            </Button>
                        )}
                        {urls.edit && (
                            <Button variant="primary" asChild>
                                <Link href={urls.edit}>
                                    <Pencil />
                                    Edit
                                </Link>
                            </Button>
                        )}
                        {urls.reset_pass && (
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button size="icon" aria-label="More actions" className="size-9">
                                        <MoreHorizontal />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent>
                                    <DropdownMenuItem
                                        onSelect={() =>
                                            confirm({
                                                title: 'Reset password?',
                                                description: `${student.name}'s password will be reset to the default student password.`,
                                                confirmLabel: 'Reset password',
                                                tone: 'primary',
                                                url: urls.reset_pass,
                                            })
                                        }
                                    >
                                        <KeyRound />
                                        Reset password
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        )}
                    </div>
                </div>
            </div>

            <Tabs value={tab} onValueChange={changeTab}>
                <TabsList className="mb-6">
                    <TabsTrigger value="overview">Overview</TabsTrigger>
                    <TabsTrigger value="guardian">Guardian</TabsTrigger>
                    {tabs.fees && (
                        <TabsTrigger value="fees" count={fees ? [...fees.school.records, ...fees.optional.charges].filter((r) => r.balance > 0).length || undefined : undefined}>
                            Fees
                        </TabsTrigger>
                    )}
                    <TabsTrigger value="results">Results</TabsTrigger>
                </TabsList>

                <TabsContent value="overview">
                    <div className="grid gap-6 xl:grid-cols-3">
                        <div className="space-y-6 xl:col-span-2">
                            <Panel title="Personal information">
                                <DescriptionList
                                    columns={3}
                                    items={[
                                        { label: 'Full name', value: student.name },
                                        { label: 'Gender', value: student.gender },
                                        { label: 'Date of birth', value: student.dob ? `${formatDate(student.dob, 'dd/MM/yyyy')}${age !== null ? ` (${age} yrs)` : ''}` : null },
                                        { label: 'Birth place', value: student.details?.birth_place },
                                        { label: 'Religion', value: student.details?.religion },
                                        { label: 'Blood group', value: student.blood_group },
                                        { label: 'Nationality', value: student.nationality },
                                        { label: 'State / LGA', value: [student.state, student.lga].filter(Boolean).join(' · ') },
                                        { label: 'Email', value: student.email },
                                        { label: 'Phone', value: [student.phone, student.phone2].filter(Boolean).join(' · ') },
                                        { label: 'Address', value: student.address },
                                    ]}
                                />
                            </Panel>
                            {student.details && <HealthPanel d={student.details} />}
                            <Panel title="Academic information">
                                <DescriptionList
                                    columns={3}
                                    items={[
                                        { label: 'Admission number', value: student.adm_no },
                                        { label: 'Class', value: student.class },
                                        { label: 'Section', value: student.section },
                                        { label: 'Date of admission', value: student.admission_date ? formatDate(student.admission_date, 'dd/MM/yyyy') : student.year_admitted },
                                        { label: 'Previous school', value: student.details?.past_school },
                                        { label: 'Fee discount', value: student.discount ? `${student.discount.name} (${student.discount.percent}% of tuition)` : 'None' },
                                        { label: 'Year', value: student.session },
                                        { label: 'Sport house', value: student.house },
                                        { label: 'Dormitory', value: student.dorm ? `${student.dorm}${student.dorm_room_no ? ` · Room ${student.dorm_room_no}` : ''}` : null },
                                        { label: 'Login ID', value: student.username },
                                    ]}
                                />
                            </Panel>
                        </div>

                        <div className="space-y-6">
                            <GuardianCard guardian={guardian} canEdit={!!urls.edit} compact onOpen={() => changeTab('guardian')} />
                            <SiblingsCard siblings={siblings} />
                            {fees && <FeeSummary fees={fees} onOpen={() => changeTab('fees')} />}
                            <Panel
                                title="Latest result"
                                actions={
                                    <Button size="xs" variant="ghost" onClick={() => changeTab('results')}>
                                        All results
                                    </Button>
                                }
                            >
                                {resultsLocked ? (
                                    <LockedResults marksheet={urls.marksheet} compact />
                                ) : latestResult ? (
                                    <div>
                                        <div className="text-sm text-fg-muted">
                                            {latestResult.exam} · {latestResult.year}
                                        </div>
                                        <div className="mt-3 grid grid-cols-3 gap-3">
                                            <Mini label="Average" value={`${latestResult.average}%`} />
                                            <Mini label="Class avg." value={latestResult.class_average !== null ? `${latestResult.class_average}%` : '—'} />
                                            <Mini label="Position" value={ordinal(latestResult.position)} />
                                        </div>
                                    </div>
                                ) : (
                                    <p className="text-sm text-fg-muted">No exam results recorded yet.</p>
                                )}
                            </Panel>
                        </div>
                    </div>
                </TabsContent>

                <TabsContent value="guardian">
                    <div className="grid gap-6 xl:grid-cols-3">
                        <div className="space-y-6">
                            <GuardianCard guardian={guardian} canEdit={!!urls.edit} />
                            <SiblingsCard siblings={siblings} />
                        </div>
                        <div className="space-y-6 xl:col-span-2">
                            {guardian?.details && <FamilyPanel d={guardian.details} />}
                            {notices && <NoticesPanel notices={notices} notifyUrl={urls.notify} hasParent={!!guardian} />}
                        </div>
                    </div>
                </TabsContent>

                {tabs.fees && (
                    <TabsContent value="fees">
                        <FeesTab fees={fees} invoice={invoice} invoiceUrl={urls.invoice} sendUrl={urls.send_invoice} />
                    </TabsContent>
                )}

                <TabsContent value="results">
                    {resultsLocked ? (
                        <Panel>
                            <LockedResults marksheet={urls.marksheet} />
                        </Panel>
                    ) : results === undefined || results === null ? (
                        <ResultsSkeleton />
                    ) : (
                        <ResultsTab results={results} marksheet={urls.marksheet} />
                    )}
                </TabsContent>
            </Tabs>
            {confirmDialog}
        </>
    );
}

StudentShow.layout = withAppLayout;

function Mini({ label, value }) {
    return (
        <div className="rounded-md border border-border bg-muted px-2.5 py-2">
            <div className="text-2xs font-semibold uppercase text-fg-subtle">{label}</div>
            <div className="tabular mt-0.5 text-md font-semibold">{value}</div>
        </div>
    );
}

/** Brothers and sisters at the school (same parent), with their class. */
function SiblingsCard({ siblings }) {
    if (!siblings.length) return null;
    return (
        <Panel title={siblings.length === 1 ? 'Sibling' : `Siblings (${siblings.length})`}>
            <ul className="-my-1 divide-y divide-border">
                {siblings.map((s) => (
                    <li key={s.url}>
                        <Link href={s.url} className="flex items-center gap-3 py-2 hover:text-primary">
                            <Avatar src={s.photo} name={s.name} size="md" />
                            <div className="min-w-0 flex-1">
                                <div className="truncate text-sm font-medium">{s.name}</div>
                                <div className="text-xs text-fg-muted">{s.class || '—'}</div>
                            </div>
                        </Link>
                    </li>
                ))}
            </ul>
        </Panel>
    );
}

function GuardianCard({ guardian, compact, onOpen, canEdit }) {
    return (
        <Panel
            title="Parent / guardian"
            actions={
                compact && guardian ? (
                    <Button size="xs" variant="ghost" onClick={onOpen}>
                        Details
                    </Button>
                ) : null
            }
        >
            {guardian ? (
                <div>
                    <div className="flex items-center gap-3">
                        <Avatar src={guardian.photo} name={guardian.name} size="lg" />
                        <div className="min-w-0">
                            <Link href={guardian.url} className="block truncate font-medium hover:text-primary">
                                {guardian.name}
                            </Link>
                            <div className="text-sm text-fg-muted">Parent account</div>
                        </div>
                    </div>
                    <ul className="mt-4 space-y-2 text-sm">
                        <ContactRow icon={Phone} value={[guardian.phone, guardian.phone2].filter(Boolean).join(' · ')} href={guardian.phone ? `tel:${guardian.phone}` : null} />
                        <ContactRow icon={Mail} value={guardian.email} href={guardian.email ? `mailto:${guardian.email}` : null} />
                        {!compact && <ContactRow icon={MapPin} value={guardian.address} />}
                    </ul>
                </div>
            ) : (
                <EmptyState compact icon={UserRound} title="No parent linked" description={canEdit ? 'Link a parent account from Edit details.' : undefined} className="py-4" />
            )}
        </Panel>
    );
}

function ContactRow({ icon: Icon, value, href }) {
    return (
        <li className="flex items-start gap-2.5">
            <Icon className="mt-0.5 size-4 shrink-0 text-fg-subtle" />
            {value ? (
                href ? (
                    <a href={href} className="break-all hover:text-primary">
                        {value}
                    </a>
                ) : (
                    <span>{value}</span>
                )
            ) : (
                <span className="text-fg-subtle">Not provided</span>
            )}
        </li>
    );
}

function FeeSummary({ fees, onOpen }) {
    const { amount, paid, balance } = fees.totals;
    const school = fees.school.totals;
    const optional = fees.optional.totals;
    return (
        <Panel
            title="Fees"
            actions={
                <Button size="xs" variant="ghost" onClick={onOpen}>
                    Details
                </Button>
            }
        >
            {amount > 0 ? (
                <>
                    <div className="flex items-baseline justify-between">
                        <span className="text-sm text-fg-muted">Balance, all years</span>
                        <span className={cn('tabular text-xl font-semibold', balance > 0 ? 'text-danger-fg' : 'text-success-fg')}>{formatMoney(balance)}</span>
                    </div>
                    <div className="mt-3">
                        <Meter value={paid} max={amount} />
                    </div>
                    <dl className="tabular mt-3 space-y-1.5 text-sm">
                        <div className="flex justify-between">
                            <dt className="text-fg-muted">School fees</dt>
                            <dd>{formatMoney(school.balance)} due</dd>
                        </div>
                        <div className="flex justify-between">
                            <dt className="text-fg-muted">Optional fees</dt>
                            <dd>{formatMoney(optional.balance)} due</dd>
                        </div>
                    </dl>
                </>
            ) : (
                <p className="text-sm text-fg-muted">No fees billed to this student yet.</p>
            )}
        </Panel>
    );
}

function FeesTab({ fees, invoice, invoiceUrl, sendUrl }) {
    const [confirm, confirmDialog] = useConfirmAction();
    const all = [...fees.school.records, ...fees.optional.charges];
    const years = [...new Set(all.map((r) => r.year))];
    const [year, setYear] = useState(years[0] ?? 'all');
    const pick = (rows) => (year === 'all' ? rows : rows.filter((r) => r.year === year));
    const school = pick(fees.school.records);
    const optional = pick(fees.optional.charges);
    const reload = () => router.reload({ only: ['fees'], preserveScroll: true });

    if (!all.length) {
        return (
            <Panel>
                <EmptyState
                    icon={Wallet}
                    title="No fees billed"
                    description="School fees are billed once they are set up for the student’s class; optional services are chosen at admission or on the edit page."
                    action={
                        invoiceUrl && (
                            <Button size="sm" asChild>
                                <a href={invoiceUrl}>Open payments</a>
                            </Button>
                        )
                    }
                />
            </Panel>
        );
    }

    const st = sumRows(school);
    const ot = sumRows(optional);

    return (
        <div className="space-y-6">
            <div className="flex flex-wrap items-center justify-between gap-3">
                {years.length > 1 ? (
                    <Segmented size="sm" value={year} onChange={setYear} options={[...years.map((y) => ({ value: y, label: y })), { value: 'all', label: 'All years' }]} />
                ) : (
                    <span className="text-sm text-fg-muted">Year {years[0]}</span>
                )}
                <span className="tabular text-sm text-fg-muted">
                    Accumulated balance, all years:{' '}
                    <span className={cn('font-semibold', fees.totals.balance > 0 ? 'text-danger-fg' : 'text-success-fg')}>{formatMoney(fees.totals.balance)}</span>
                </span>
            </div>
            <TermInvoice invoice={invoice} sendUrl={sendUrl} />
            <FeeTotals
                school={st}
                optional={ot}
                overall={{ amount: st.amount + ot.amount, paid: st.paid + ot.paid, balance: st.balance + ot.balance }}
                scopeLabel={year === 'all' ? 'Total, all years' : `Total ${year}`}
            />
            <SchoolFeesInvoice
                records={school}
                onPaid={reload}
                confirm={confirm}
                actions={
                    invoiceUrl && (
                        <Button size="sm" asChild>
                            <a href={invoiceUrl}>
                                <Receipt />
                                Payments page
                            </a>
                        </Button>
                    )
                }
            />
            <OptionalFeesInvoice charges={optional} onPaid={reload} confirm={confirm} />
            <PaymentHistory school={school} optional={optional} />
            {confirmDialog}
        </div>
    );
}

function HealthPanel({ d }) {
    const insurance = [d.insurance_provider, d.insurance_category].filter(Boolean).join(' · ');
    return (
        <Panel title="Health">
            <DescriptionList
                columns={3}
                items={[
                    { label: 'Height', value: d.height },
                    { label: 'Weight', value: d.weight },
                    { label: 'Allergies', value: d.allergies },
                    { label: 'Medical conditions', value: d.medical_conditions },
                    { label: 'Insurance', value: insurance },
                    { label: 'Card number', value: d.insurance_card_no },
                    { label: 'Coverage limit', value: d.insurance_coverage_limit },
                    { label: 'Valid until', value: d.insurance_valid_until ? formatDate(d.insurance_valid_until, 'dd/MM/yyyy') : null },
                    { label: 'Family doctor', value: [d.doctor_name, d.doctor_hospital, d.doctor_contact].filter(Boolean).join(' · ') },
                ]}
            />
        </Panel>
    );
}

export function FamilyPanel({ d }) {
    const person = (p) => [
        { label: 'Name', value: d[`${p}_name`] },
        { label: 'Contact', value: d[`${p}_phone`] },
        { label: 'Ghana card #', value: d[`${p}_ghana_card`] },
        { label: 'Occupation', value: d[`${p}_occupation`] },
        { label: 'Email', value: d[`${p}_email`] },
        { label: 'Place of work', value: d[`${p}_workplace`] },
        { label: 'Residential address', value: d[`${p}_residential_address`] },
        { label: 'Postal address', value: d[`${p}_postal_address`] },
    ];
    return (
        <>
            {d.father_name && (
                <Panel title="Father">
                    <DescriptionList columns={2} items={person('father')} />
                </Panel>
            )}
            {d.mother_name && (
                <Panel title="Mother">
                    <DescriptionList columns={2} items={person('mother')} />
                </Panel>
            )}
            {d.guardian_name && (
                <Panel title="Guardian / nanny">
                    <DescriptionList
                        columns={2}
                        items={[
                            { label: 'Name', value: d.guardian_name },
                            { label: 'Relationship with ward', value: d.guardian_relationship },
                            { label: 'Contact', value: d.guardian_phone },
                            { label: 'Ghana card #', value: d.guardian_ghana_card },
                            { label: 'Residential address', value: d.guardian_address },
                        ]}
                    />
                </Panel>
            )}
        </>
    );
}

function NoticesPanel({ notices, notifyUrl, hasParent }) {
    const [sending, setSending] = useState(false);
    const resend = async () => {
        setSending(true);
        const r = await submitForm(notifyUrl, {});
        setSending(false);
        if (r.ok) toast.success(r.message);
        else toast.error(r.message);
        router.reload({ only: ['notices'], preserveScroll: true });
    };
    return (
        <Panel
            title="Admission notices"
            description="Admission letter and fees link sent to the parent by email and SMS"
            actions={
                notifyUrl && hasParent ? (
                    <Button size="sm" onClick={resend} loading={sending}>
                        {notices.length ? 'Send again' : 'Send now'}
                    </Button>
                ) : null
            }
        >
            {notices.length ? <NoticeList notices={notices} /> : <p className="text-sm text-fg-muted">No notices have been sent for this student.</p>}
        </Panel>
    );
}

function FeeFigure({ label, value, tone }) {
    return (
        <div className="px-4 py-3.5">
            <div className="overline-label">{label}</div>
            <div className={cn('tabular mt-1.5 text-xl font-semibold', tone === 'danger' && 'text-danger-fg', tone === 'success' && 'text-success-fg')}>{value}</div>
        </div>
    );
}

function LockedResults({ marksheet, compact }) {
    return (
        <EmptyState
            compact={compact}
            icon={LockKeyhole}
            title="Results are locked"
            description="Results are PIN-protected this term. Open the marksheet to enter a result PIN."
            action={
                <Button size="sm" asChild>
                    <a href={marksheet}>Open marksheet</a>
                </Button>
            }
            className={compact ? 'py-2' : undefined}
        />
    );
}

function ResultsSkeleton() {
    return (
        <div className="space-y-4">
            <Skeleton className="h-9 w-72" />
            <Skeleton className="h-56 w-full" />
        </div>
    );
}

function ResultsTab({ results, marksheet }) {
    const exams = results.exams ?? [];
    const [selected, setSelected] = useState(exams[0] ? `${exams[0].exam_id}` : null);
    useEffect(() => {
        if (!selected && exams[0]) setSelected(`${exams[0].exam_id}`);
    }, [exams, selected]);

    if (!exams.length) {
        return (
            <Panel>
                <EmptyState icon={BookOpenCheck} title="No exam results yet" description="Results appear here once teachers enter marks." />
            </Panel>
        );
    }

    const exam = exams.find((e) => `${e.exam_id}` === selected) ?? exams[0];

    return (
        <div className="space-y-6">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <Segmented
                    value={`${exam.exam_id}`}
                    onChange={setSelected}
                    options={exams.map((e) => ({ value: `${e.exam_id}`, label: `${e.exam} · ${e.year}` }))}
                />
                <Button size="sm" asChild>
                    <a href={marksheet}>
                        <ScrollText />
                        Full marksheet
                    </a>
                </Button>
            </div>

            <div className="panel grid divide-y divide-border sm:grid-cols-4 sm:divide-x sm:divide-y-0">
                <FeeFigure label="Average" value={exam.average !== null ? `${exam.average}%` : '—'} />
                <FeeFigure label="Class average" value={exam.class_average !== null ? `${exam.class_average}%` : '—'} />
                <FeeFigure label="Position" value={ordinal(exam.position)} />
                <FeeFigure label="Total score" value={exam.total ?? '—'} />
            </div>

            <Panel title={`${exam.exam} — subject scores`} description={exam.class ? `Taken in ${exam.class}` : undefined} flush>
                <div className="scrollbar-thin overflow-x-auto">
                    <table className="w-full text-left">
                        <thead>
                            <tr className="border-b border-border bg-canvas text-2xs font-semibold uppercase text-fg-muted">
                                <th className="h-9 px-4">Subject</th>
                                <th className="h-9 px-3 text-right">1st CA</th>
                                <th className="h-9 px-3 text-right">2nd CA</th>
                                <th className="h-9 px-3 text-right">Exam</th>
                                <th className="h-9 px-3 text-right">Total</th>
                                <th className="h-9 min-w-[140px] px-3" />
                                <th className="h-9 px-3">Grade</th>
                                <th className="hidden h-9 px-4 text-right md:table-cell">Subject pos.</th>
                            </tr>
                        </thead>
                        <tbody className="tabular">
                            {exam.subjects.map((s) => (
                                <tr key={s.subject} className="border-b border-border last:border-0 hover:bg-muted">
                                    <td className="h-11 px-4 font-medium">{s.subject}</td>
                                    <td className="px-3 text-right text-fg-muted">{s.ca1 ?? '—'}</td>
                                    <td className="px-3 text-right text-fg-muted">{s.ca2 ?? '—'}</td>
                                    <td className="px-3 text-right text-fg-muted">{s.exam ?? '—'}</td>
                                    <td className="px-3 text-right font-semibold">{s.total ?? '—'}</td>
                                    <td className="px-3">{s.total !== null && s.total !== undefined && <Meter value={Number(s.total)} max={100} />}</td>
                                    <td className="px-3">
                                        {s.grade ? (
                                            <span className="inline-flex items-center gap-1.5">
                                                <Badge tone={gradeTone(s.grade)} shape="tag">
                                                    {s.grade}
                                                </Badge>
                                                <span className="text-xs text-fg-muted">{s.remark}</span>
                                            </span>
                                        ) : (
                                            '—'
                                        )}
                                    </td>
                                    <td className="hidden px-4 text-right text-fg-muted md:table-cell">{ordinal(s.position)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </Panel>
        </div>
    );
}

function gradeTone(grade = '') {
    const g = grade.toUpperCase()[0];
    if (g === 'A' || g === 'B') return 'success';
    if (g === 'C') return 'info';
    if (g === 'D' || g === 'E') return 'warning';
    return 'danger';
}
