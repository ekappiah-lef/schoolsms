import { useEffect, useMemo, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import { format } from 'date-fns';
import { toast } from 'sonner';
import { ArrowLeft, ArrowRight, Check, Pencil, UserPlus, Users } from 'lucide-react';
import http, { submitForm } from '@/lib/http';
import { cn, formatDate, formatMoney } from '@/lib/utils';
import { FormErrorSummary, FormField, FormSection } from '@/components/app/form';
import { PhotoUploader } from '@/components/app/file-uploader';
import { Button } from '@/components/ui/button';
import { Input, Textarea } from '@/components/ui/input';
import { Checkbox } from '@/components/ui/checkbox';
import { Combobox, Select } from '@/components/ui/select';
import { Segmented } from '@/components/ui/tabs';
import { DatePicker } from '@/components/ui/date-picker';
import { ServicesPicker, servicesTotal, selectedServiceLines, EMPTY_SERVICES } from './services-picker';
import { SectionPicker } from './section-picker';

const today = () => format(new Date(), 'yyyy-MM-dd');

const PARENT_KEYS = ['name', 'phone', 'ghana_card', 'occupation', 'workplace', 'email', 'residential_address', 'postal_address'];
const PARENT_FIELDS = [
    ...['father', 'mother'].flatMap((p) => PARENT_KEYS.map((k) => `${p}_${k}`)),
    'guardian_name',
    'guardian_phone',
    'guardian_ghana_card',
    'guardian_relationship',
    'guardian_address',
];

const DETAIL_FIELDS = [
    'height', 'weight', 'birth_place', 'religion', 'past_school', 'past_school_address', 'past_qualification',
    'allergies', 'medical_conditions', 'insurance_card_no', 'insurance_provider', 'insurance_category',
    'insurance_coverage_limit', 'insurance_valid_until', 'insurance_start_date', 'doctor_name', 'doctor_hospital', 'doctor_contact',
];

const EMPTY = {
    first_name: '',
    middle_name: '',
    last_name: '',
    address: '',
    email: '',
    gender: '',
    phone: '',
    phone2: '',
    dob: '',
    nal_id: '',
    state_id: '',
    lga_id: '',
    bg_id: '',
    ...Object.fromEntries(DETAIL_FIELDS.map((f) => [f, ''])),
    my_class_id: '',
    section_id: '',
    admission_date: '',
    year_admitted: '',
    dorm_id: '',
    dorm_room_no: '',
    house: '',
    adm_no: '',
    services: EMPTY_SERVICES,
    fee_discount_id: '',
    parent_mode: 'existing',
    my_parent_id: '',
    parent_primary: 'father',
    ...Object.fromEntries(PARENT_FIELDS.map((f) => [f, ''])),
    terms_accepted: false,
    send_notices: true,
};

/** Field labels, used for error summaries and the review step. */
export const LABELS = {
    name: 'Full name',
    first_name: 'First name',
    middle_name: 'Middle name',
    last_name: 'Last name',
    address: 'Home address',
    email: 'Email',
    gender: 'Gender',
    phone: 'Phone',
    phone2: 'Alternative phone',
    dob: 'Date of birth',
    nal_id: 'Nationality',
    state_id: 'State',
    lga_id: 'LGA',
    bg_id: 'Blood group',
    photo: 'Student photo',
    height: 'Height',
    weight: 'Weight',
    birth_place: 'Place of birth',
    religion: 'Religion',
    past_school: 'Past school attended',
    past_school_address: 'School address',
    past_qualification: 'Past qualification',
    allergies: 'Allergies',
    medical_conditions: 'Peculiar medical conditions',
    insurance_card_no: 'Insurance card number',
    insurance_provider: 'Insurance provider',
    insurance_category: 'Insurance category',
    insurance_coverage_limit: 'Coverage limit',
    insurance_valid_until: 'Insurance validity date',
    insurance_start_date: 'Insurance start date',
    doctor_name: 'Family doctor',
    doctor_hospital: 'Hospital',
    doctor_contact: 'Doctor’s contact',
    my_class_id: 'Class',
    section_id: 'Section',
    admission_date: 'Date of admission',
    year_admitted: 'Year admitted',
    my_parent_id: 'Parent',
    parent_mode: 'Parent',
    parent_primary: 'Main contact',
    dorm_id: 'Dormitory',
    dorm_room_no: 'Room number',
    house: 'Sport house',
    adm_no: 'Admission number',
    services: 'Optional services',
    fee_discount_id: 'Fee discount',
    terms_accepted: 'Parent/guardian agreement',
    father_name: 'Father’s name',
    father_phone: 'Father’s contact',
    father_email: 'Father’s email',
    mother_name: 'Mother’s name',
    mother_phone: 'Mother’s contact',
    mother_email: 'Mother’s email',
    guardian_name: 'Guardian’s name',
    guardian_phone: 'Guardian’s contact',
};

const STUDENT_FIELDS = ['first_name', 'middle_name', 'last_name', 'gender', 'dob', 'bg_id', 'photo', 'email', 'phone', 'phone2', 'address', 'nal_id', 'state_id', 'lga_id', ...DETAIL_FIELDS];

const STEPS = [
    { id: 'student', title: 'Student Details', description: 'Personal, contact and health information', fields: STUDENT_FIELDS },
    { id: 'enrolment', title: 'Enrolment & Services', description: 'Class, admission details and optional services', fields: ['my_class_id', 'section_id', 'admission_date', 'adm_no', 'fee_discount_id', 'dorm_id', 'dorm_room_no', 'house', 'services'] },
    { id: 'parent', title: 'Parent / Guardian', description: 'Link an existing or add a new guardian', fields: ['parent_mode', 'my_parent_id', 'parent_primary', ...PARENT_FIELDS] },
    { id: 'review', title: 'Review & Submit', description: 'Confirm details, fees and agreements', fields: ['terms_accepted'] },
];

const isEmail = (v) => /^\S+@\S+\.\S+$/.test(v);

/** Mirrors StudentRecordCreate / StudentRecordUpdate + AdmissionRules. */
function validate(data, fields, mode) {
    const e = {};
    const has = (f) => fields.includes(f);
    const blank = (f) => !String(data[f] ?? '').trim();
    const need = (f, msg) => has(f) && blank(f) && (e[f] = msg);

    need('first_name', 'Enter the first name.');
    need('last_name', 'Enter the last name (surname).');
    const full = [data.first_name, data.middle_name, data.last_name].map((s) => (s ?? '').trim()).filter(Boolean).join(' ');
    if (has('first_name') && !e.first_name && !e.last_name && full.length < 6) e.first_name = 'The full name must be at least 6 characters.';
    need('gender', 'Choose a gender.');
    need('address', 'Enter a home address.');
    if (has('address') && data.address && data.address.trim().length < 6) e.address = 'The address must be at least 6 characters.';
    need('nal_id', 'Choose a nationality.');
    need('state_id', 'Choose a state.');
    need('lga_id', 'Choose an LGA.');
    need('my_class_id', 'Choose a class.');
    need('section_id', 'Choose a section.');
    if (mode === 'create') need('admission_date', 'Choose the date of admission.');
    if (has('admission_date') && data.admission_date && data.admission_date > today()) e.admission_date = 'The date of admission cannot be in the future.';
    if (has('email') && data.email && !isEmail(data.email)) e.email = 'Enter a valid email address.';
    if (has('adm_no') && data.adm_no && !/^[a-zA-Z0-9]{3,}$/.test(data.adm_no)) e.adm_no = 'Use at least 3 letters or numbers, no spaces.';

    if (has('services') && data.services?.bus_direction && !data.services?.bus_route_id) e.services = 'Choose the bus location, or set the bus to “No bus”.';

    if (mode === 'create' && has('parent_mode')) {
        if (data.parent_mode === 'existing') need('my_parent_id', 'Choose the parent’s account, or switch to “New parent”.');
        if (data.parent_mode === 'new') {
            const p = data.parent_primary;
            need(`${p}_name`, 'Enter the main contact’s name.');
            if (!['father_phone', 'mother_phone', 'guardian_phone'].some((f) => !blank(f))) e[`${p}_phone`] = 'Enter at least one contact number.';
            ['father_email', 'mother_email'].forEach((f) => data[f] && !isEmail(data[f]) && (e[f] = 'Enter a valid email address.'));
            ['father_phone', 'mother_phone', 'guardian_phone'].forEach((f) => data[f] && String(data[f]).replace(/\D/g, '').length < 9 && (e[f] = 'Enter a full phone number.'));
        }
    }

    if (mode === 'create' && has('terms_accepted') && !data.terms_accepted) e.terms_accepted = 'Confirm the parent/guardian has accepted the agreement.';
    return e;
}

/** LGA options loaded from the existing /ajax endpoint when the state changes. */
function useDependentOptions(url, parentId, initial) {
    const [options, setOptions] = useState(initial ?? []);
    const [loading, setLoading] = useState(false);
    const first = useRef(true);
    useEffect(() => {
        if (first.current && initial) {
            first.current = false;
            return;
        }
        first.current = false;
        // No list to load for an empty or newly typed parent (e.g. a new state): LGAs are typed in.
        if (!parentId || String(parentId).startsWith('new:')) {
            setOptions([]);
            return;
        }
        let cancelled = false;
        setLoading(true);
        http.get(url.replace(':id', parentId))
            .then(({ data }) => !cancelled && setOptions(data))
            .catch(() => !cancelled && toast.error('Could not load options. Check your connection.'))
            .finally(() => !cancelled && setLoading(false));
        return () => {
            cancelled = true;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [parentId]);
    return [options, loading];
}

export default function StudentForm({ mode, options, initial, initialLgas, currentPhoto, submitUrl, onSaved, onCancel }) {
    const [data, setDataState] = useState(() => ({
        ...EMPTY,
        ...(mode === 'create' ? { admission_date: today() } : {}),
        ...(initial ?? {}),
        services: { ...EMPTY_SERVICES, ...(initial?.services ?? {}) },
    }));
    const [photo, setPhoto] = useState(null);
    const [errors, setErrors] = useState({});
    const [serverErrors, setServerErrors] = useState({});
    const [processing, setProcessing] = useState(false);
    const [step, setStep] = useState(0);
    const [dirty, setDirty] = useState(false);
    const topRef = useRef(null);

    const stepped = mode === 'create';
    const [lgas, lgasLoading] = useDependentOptions(options.urls.lgas, data.state_id, initialLgas);

    const set = (field, value) => {
        setDirty(true);
        setDataState((d) => {
            const next = { ...d, [field]: value };
            if (field === 'my_class_id' && value !== d.my_class_id) next.section_id = '';
            if (field === 'state_id' && value !== d.state_id) next.lga_id = '';
            return next;
        });
        if (errors[field]) setErrors((e) => ({ ...e, [field]: undefined }));
    };

    // Warn before leaving with unsaved changes.
    const dirtyRef = useRef(false);
    dirtyRef.current = dirty;
    useEffect(() => {
        if (!dirty) return undefined;
        const onBeforeUnload = (e) => {
            if (!dirtyRef.current) return;
            e.preventDefault();
            e.returnValue = '';
        };
        window.addEventListener('beforeunload', onBeforeUnload);
        const off = router.on('before', (event) => {
            if (dirtyRef.current && event.detail.visit.method === 'get' && !window.confirm('Leave this page? Changes you made will not be saved.')) {
                return false;
            }
            return undefined;
        });
        return () => {
            window.removeEventListener('beforeunload', onBeforeUnload);
            off();
        };
    }, [dirty]);

    // Enrolled / capacity per class, from its sections.
    const classStats = useMemo(() => {
        const stats = {};
        options.sections.forEach((s) => {
            const st = (stats[s.class_id] ??= { enrolled: 0, capacity: 0, unlimited: false });
            st.enrolled += s.enrolled;
            if (s.capacity) st.capacity += s.capacity;
            else st.unlimited = true;
        });
        return stats;
    }, [options.sections]);

    const opt = useMemo(
        () => ({
            classes: options.classes.map((o) => {
                const st = classStats[o.id];
                const hint = !st ? 'No sections' : st.unlimited ? `${st.enrolled} students` : `${st.enrolled}/${st.capacity}`;
                return { value: o.id, label: o.name, hint };
            }),
            parents: options.parents.map((o) => ({ value: o.id, label: o.name, hint: o.hint })),
            dorms: options.dorms.map((o) => ({ value: o.id, label: o.name })),
            states: options.states.map((o) => ({ value: o.id, label: o.name })),
            nationals: options.nationals.map((o) => ({ value: o.id, label: o.name })),
            bloodGroups: options.blood_groups.map((o) => ({ value: o.id, label: o.name })),
        }),
        [options, classStats],
    );
    const classSections = options.sections.filter((s) => String(s.class_id) === String(data.my_class_id));
    const lgaOpts = lgas.map((o) => ({ value: o.id, label: o.name }));

    const label = (list, value) => (String(value ?? '').startsWith('new:') ? String(value).slice(4) : list.find((o) => String(o.value) === String(value))?.label);
    const className = label(opt.classes, data.my_class_id);
    const section = options.sections.find((s) => String(s.id) === String(data.section_id));
    const placement = [className, section?.name].filter(Boolean).join(' ');

    // School fees this student will be billed (new admissions get "new student" fees).
    const discount = (options.discounts ?? []).find((d) => String(d.id) === String(data.fee_discount_id));
    // Tuition share of a fee: its "Tuition" items, or the whole fee when it is not itemised.
    const tuitionOf = (f) => (f.items.length ? f.items.filter((i) => /tuition/i.test(i.name)).reduce((t, i) => t + i.amount, 0) : f.amount);
    const discountOn = (f) => (discount ? Math.round((tuitionOf(f) * discount.percent) / 100) : 0);

    const schoolFees = useMemo(() => {
        if (!data.my_class_id) return [];
        const category = mode === 'create' ? 'new' : null;
        return (options.fees ?? []).filter(
            (f) => (f.class_id === null || String(f.class_id) === String(data.my_class_id)) && (!category || f.category === 'all' || f.category === category),
        );
    }, [options.fees, data.my_class_id, mode]);

    const scrollTop = () => topRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });

    const goToErrors = (errs) => {
        const firstField = Object.keys(errs)[0];
        if (stepped) {
            const idx = STEPS.findIndex((s) => s.fields.includes(firstField));
            if (idx >= 0) setStep(idx);
        }
        setTimeout(() => document.getElementById(`field-${firstField}`)?.focus?.(), 50);
    };

    const next = () => {
        const errs = validate(data, STEPS[step].fields, mode);
        setErrors(errs);
        if (Object.keys(errs).length) {
            goToErrors(errs);
            return;
        }
        setStep((s) => s + 1);
        scrollTop();
    };

    const submit = async (e) => {
        e?.preventDefault();
        if (stepped && step < STEPS.length - 1) {
            next();
            return;
        }
        const allFields = STEPS.flatMap((s) => s.fields);
        const errs = validate(data, allFields, mode);
        setErrors(errs);
        if (Object.keys(errs).length) {
            toast.error('Some required details are missing.');
            goToErrors(errs);
            return;
        }

        setProcessing(true);
        setServerErrors({});
        const name = [data.first_name, data.middle_name, data.last_name].map((s) => (s ?? '').trim()).filter(Boolean).join(' ');
        const payload = { ...data, name, form_version: '2', services: JSON.stringify(data.services) };
        if (mode === 'edit') {
            delete payload.adm_no; // not editable after admission
            ['parent_mode', 'parent_primary', 'terms_accepted', 'send_notices', ...PARENT_FIELDS].forEach((f) => delete payload[f]);
        } else {
            payload.terms_accepted = data.terms_accepted ? '1' : '';
            payload.send_notices = data.send_notices && data.parent_mode !== 'none' ? '1' : '0';
            if (data.parent_mode !== 'existing') payload.my_parent_id = '';
            if (data.parent_mode !== 'new') PARENT_FIELDS.forEach((f) => delete payload[f]);
        }
        delete payload.adm_no_display;
        if (photo) payload.photo = photo;

        const result = await submitForm(submitUrl, payload, { method: mode === 'edit' ? 'put' : 'post' });
        setProcessing(false);

        if (result.ok) {
            dirtyRef.current = false;
            setDirty(false);
            toast.success(result.message);
            onSaved?.(data, result.data);
        } else if (result.errors) {
            setErrors(result.errors);
            setServerErrors(result.errors);
            toast.error(result.message);
            goToErrors(result.errors);
            scrollTop();
        } else {
            toast.error(result.message);
        }
    };

    const err = (f) => errors[f];
    const show = (id) => !stepped || STEPS[step].id === id;
    const text = (f, props = {}) => <Input id={`field-${f}`} value={data[f] ?? ''} onChange={(e) => set(f, e.target.value)} {...props} />;

    const studentSections = (
        <>
            <FormSection id="personal" title="Personal details" description="As they should appear on official school records." columns={3}>
                <FormField label="First name" required error={err('first_name')}>
                    {text('first_name', { autoComplete: 'off' })}
                </FormField>
                <FormField label="Middle name" error={err('middle_name')}>
                    {text('middle_name', { autoComplete: 'off' })}
                </FormField>
                <FormField label="Last name / surname" required error={err('last_name')}>
                    {text('last_name', { autoComplete: 'off' })}
                </FormField>
                <FormField label="Gender" required error={err('gender')}>
                    <Select id="field-gender" value={data.gender} onChange={(v) => set('gender', v)} placeholder="Choose…" options={[{ value: 'Male', label: 'Male' }, { value: 'Female', label: 'Female' }]} />
                </FormField>
                <FormField label="Date of birth" error={err('dob')}>
                    <DatePicker id="field-dob" value={data.dob} onChange={(v) => set('dob', v)} />
                </FormField>
                <FormField label="Blood group" error={err('bg_id')}>
                    <Select id="field-bg_id" value={data.bg_id} onChange={(v) => set('bg_id', v)} options={opt.bloodGroups} clearable clearLabel="Not known" placeholder="Not known" />
                </FormField>
                <FormField label="Height" error={err('height')} hint="e.g. 132 cm">
                    {text('height')}
                </FormField>
                <FormField label="Weight" error={err('weight')} hint="e.g. 28 kg">
                    {text('weight')}
                </FormField>
                <FormField label="Place of birth" error={err('birth_place')}>
                    {text('birth_place')}
                </FormField>
                <FormField label="Religion" error={err('religion')}>
                    {text('religion')}
                </FormField>
                <FormField label="Student photo" error={err('photo')} className="sm:col-span-2">
                    <PhotoUploader
                        value={photo}
                        onChange={(f) => {
                            setPhoto(f);
                            setDirty(true);
                        }}
                        currentUrl={currentPhoto}
                        name={[data.first_name, data.last_name].filter(Boolean).join(' ')}
                        error={err('photo')}
                        onError={(msg) => setErrors((e) => ({ ...e, photo: msg || undefined }))}
                    />
                </FormField>
            </FormSection>

            <FormSection id="contact" title="Contact & origin" description="Email is optional but must be unique.">
                <FormField label="Email" error={err('email')}>
                    <Input id="field-email" type="email" value={data.email ?? ''} onChange={(e) => set('email', e.target.value)} placeholder="name@example.com" />
                </FormField>
                <FormField label="Phone" error={err('phone')}>
                    <Input id="field-phone" type="tel" value={data.phone ?? ''} onChange={(e) => set('phone', e.target.value)} />
                </FormField>
                <FormField label="Alternative phone" error={err('phone2')}>
                    <Input id="field-phone2" type="tel" value={data.phone2 ?? ''} onChange={(e) => set('phone2', e.target.value)} />
                </FormField>
                <FormField label="Home address" required error={err('address')} className="sm:col-span-2">
                    {text('address', { placeholder: 'House number, street, town' })}
                </FormField>
                <FormField label="Nationality" required error={err('nal_id')}>
                    <Combobox id="field-nal_id" value={data.nal_id} onChange={(v) => set('nal_id', v)} options={opt.nationals} placeholder="Choose nationality" creatable searchPlaceholder="Search countries…" />
                </FormField>
                <FormField label="State" required error={err('state_id')}>
                    <Combobox id="field-state_id" value={data.state_id} onChange={(v) => set('state_id', v)} options={opt.states} placeholder="Choose or type a state" creatable />
                </FormField>
                <FormField label="LGA" required error={err('lga_id')} hint={!data.state_id ? 'Choose a state first' : undefined}>
                    <Combobox id="field-lga_id" value={data.lga_id} onChange={(v) => set('lga_id', v)} options={lgaOpts} loading={lgasLoading} disabled={!data.state_id} placeholder="Choose or type an LGA" creatable />
                </FormField>
            </FormSection>

            <FormSection id="background" title="Previous school" description="Leave blank for a first school placement.">
                <FormField label="Past school attended" error={err('past_school')}>
                    {text('past_school')}
                </FormField>
                <FormField label="Past qualification" error={err('past_qualification')}>
                    {text('past_qualification')}
                </FormField>
                <FormField label="School address" error={err('past_school_address')} className="sm:col-span-2">
                    {text('past_school_address')}
                </FormField>
            </FormSection>

            <FormSection id="health" title="Health" description="For health and Medical Purposes.">
                <FormField label="Allergies" error={err('allergies')}>
                    <Textarea id="field-allergies" value={data.allergies ?? ''} onChange={(e) => set('allergies', e.target.value)} placeholder="None known" />
                </FormField>
                <FormField label="Peculiar medical conditions" error={err('medical_conditions')}>
                    <Textarea id="field-medical_conditions" value={data.medical_conditions ?? ''} onChange={(e) => set('medical_conditions', e.target.value)} placeholder="None known" />
                </FormField>
            </FormSection>

            <FormSection id="insurance" title="Health insurance" description="As shown on the insurance card." columns={3}>
                <FormField label="Card number" error={err('insurance_card_no')}>
                    {text('insurance_card_no')}
                </FormField>
                <FormField label="Service provider" error={err('insurance_provider')}>
                    {text('insurance_provider', { placeholder: 'e.g. NHIS' })}
                </FormField>
                <FormField label="Category" error={err('insurance_category')}>
                    {text('insurance_category')}
                </FormField>
                <FormField label="Coverage limit" error={err('insurance_coverage_limit')}>
                    {text('insurance_coverage_limit')}
                </FormField>
                <FormField label="Start date" error={err('insurance_start_date')}>
                    <DatePicker id="field-insurance_start_date" value={data.insurance_start_date} onChange={(v) => set('insurance_start_date', v)} fromYear={2000} defaultMonth={new Date()} />
                </FormField>
                <FormField label="Validity date" error={err('insurance_valid_until')}>
                    <DatePicker
                        id="field-insurance_valid_until"
                        value={data.insurance_valid_until}
                        onChange={(v) => set('insurance_valid_until', v)}
                        fromYear={2000}
                        toYear={new Date().getFullYear() + 10}
                        defaultMonth={new Date()}
                    />
                </FormField>
            </FormSection>

            <FormSection id="doctor" title="Family doctor / specialist" columns={3}>
                <FormField label="Name of doctor" error={err('doctor_name')}>
                    {text('doctor_name')}
                </FormField>
                <FormField label="Name of hospital" error={err('doctor_hospital')}>
                    {text('doctor_hospital')}
                </FormField>
                <FormField label="Contact of doctor" error={err('doctor_contact')}>
                    {text('doctor_contact', { type: 'tel' })}
                </FormField>
            </FormSection>
        </>
    );

    const enrolmentSections = (
        <>
            <FormSection
                id="enrolment"
                title="Class placement"
                description={mode === 'edit' ? 'Changing the class of an existing student removes their marks for the old class this year.' : 'Numbers show students enrolled / class capacity.'}
            >
                <FormField label="Class" required error={err('my_class_id')}>
                    <Combobox id="field-my_class_id" value={data.my_class_id} onChange={(v) => set('my_class_id', v)} options={opt.classes} placeholder="Choose class" clearable={false} />
                </FormField>
                <FormField label="Date of admission" required={mode === 'create'} error={err('admission_date')}>
                    <DatePicker id="field-admission_date" value={data.admission_date} onChange={(v) => set('admission_date', v)} fromYear={2000} defaultMonth={new Date()} />
                </FormField>
                <FormField label="Section" required error={err('section_id')} className="sm:col-span-2" hint={!data.my_class_id ? 'Choose a class first' : undefined}>
                    <SectionPicker id="field-section_id" className={className} sections={classSections} value={data.section_id} onChange={(v) => set('section_id', v)} disabled={!data.my_class_id} invalid={!!err('section_id')} />
                </FormField>
                {mode === 'create' ? (
                    <FormField label="Admission number" error={err('adm_no')} hint="Optional. Leave blank to generate one automatically.">
                        <Input id="field-adm_no" value={data.adm_no} onChange={(e) => set('adm_no', e.target.value)} placeholder="e.g. 1042" />
                    </FormField>
                ) : (
                    <FormField label="Admission number" hint="Set at admission and used as the login ID.">
                        <Input value={initial?.adm_no_display ?? ''} disabled readOnly />
                    </FormField>
                )}
            </FormSection>

            {mode === 'create' && data.my_class_id && (
                <FormSection id="school-fees" title="School fees" description={`Year  ${options.session}`}>
                    <div className="sm:col-span-2">
                        <SchoolFeesPreview fees={schoolFees} discountOn={discountOn} />
                    </div>
                </FormSection>
            )}

            <FormSection id="discount" title="Fee discount" >
                <FormField label="Discount" error={err('fee_discount_id')} hint={discount ? `${discount.percent}% off tuition.` : 'No discount.'}>
                    <Select
                        id="field-fee_discount_id"
                        value={data.fee_discount_id}
                        onChange={(v) => set('fee_discount_id', v)}
                        options={(options.discounts ?? []).map((d) => ({ value: String(d.id), label: d.name.includes(`${d.percent}%`) ? d.name : `${d.name} · ${d.percent}%` }))}
                        clearable
                        clearLabel="No discount"
                        placeholder="No discount"
                    />
                </FormField>
            </FormSection>

            <FormSection id="services" title="Optional services & shop" >
                <div className="sm:col-span-2">
                    <ServicesPicker catalogue={options.services} value={data.services} onChange={(v) => set('services', v)} mode={mode} />
                    {err('services') && <p className="mt-2 text-xs text-danger-fg">{err('services')}</p>}
                </div>
            </FormSection>

            <FormSection id="boarding" title="Boarding & house" description="Leave blank for day students.">
                <FormField label="Dormitory" error={err('dorm_id')}>
                    <Select id="field-dorm_id" value={data.dorm_id} onChange={(v) => set('dorm_id', v)} options={opt.dorms} clearable clearLabel="Day student" placeholder="Day student" />
                </FormField>
                <FormField label="Room number" error={err('dorm_room_no')}>
                    <Input id="field-dorm_room_no" value={data.dorm_room_no ?? ''} onChange={(e) => set('dorm_room_no', e.target.value)} disabled={!data.dorm_id} />
                </FormField>
                <FormField label="Sport house" error={err('house')}>
                    {text('house')}
                </FormField>
            </FormSection>
        </>
    );

    const parentSection =
        mode === 'edit' ? (
            <FormSection id="guardian" title="Parent / guardian" description="Linked parent account. New parents can be added under Users.">
                <FormField label="Parent account" error={err('my_parent_id')} className="sm:col-span-2">
                    <Combobox id="field-my_parent_id" value={data.my_parent_id} onChange={(v) => set('my_parent_id', v)} options={opt.parents} placeholder="No parent linked" searchPlaceholder="Search parents by name or email…" />
                </FormField>
            </FormSection>
        ) : (
            <ParentStep data={data} set={set} err={err} parents={opt.parents} />
        );

    const services = selectedServiceLines(options.services, data.services);
    const parentLabel =
        data.parent_mode === 'existing'
            ? label(opt.parents, data.my_parent_id) || 'Not chosen'
            : data.parent_mode === 'new'
              ? `New parent account · ${data[`${data.parent_primary}_name`] || '—'} (${data.parent_primary})`
              : 'Not linked yet';

    const reviewRows = [
        ['Student', 0, [
            ['Full name', [data.first_name, data.middle_name, data.last_name].filter(Boolean).join(' ')],
            ['Gender', data.gender],
            ['Date of birth', data.dob ? formatDate(data.dob, 'dd/MM/yyyy') : ''],
            ['Blood group', label(opt.bloodGroups, data.bg_id)],
            ['Height / weight', [data.height, data.weight].filter(Boolean).join(' · ')],
            ['Student photo', photo ? photo.name : 'Not added'],
            ['Address', data.address],
            ['Nationality', label(opt.nationals, data.nal_id)],
            ['Allergies / conditions', [data.allergies, data.medical_conditions].filter(Boolean).join(' · ')],
            ['Insurance', [data.insurance_provider, data.insurance_card_no].filter(Boolean).join(' · ')],
            ['Previous school', data.past_school],
        ]],
        ['Enrolment', 1, [
            ['Class', placement],
            ['Date of admission', data.admission_date ? formatDate(data.admission_date, 'dd/MM/yyyy') : ''],
            ['Admission number', data.adm_no || 'Generated automatically'],
            ['Dormitory', data.dorm_id ? `${label(opt.dorms, data.dorm_id)}${data.dorm_room_no ? ` · Room ${data.dorm_room_no}` : ''}` : 'Day student'],
        ]],
        ['Parent / guardian', 2, [
            ['Parent', parentLabel],
            ...(data.parent_mode === 'new'
                ? [
                      ['Father', [data.father_name, data.father_phone].filter(Boolean).join(' · ')],
                      ['Mother', [data.mother_name, data.mother_phone].filter(Boolean).join(' · ')],
                      ['Guardian', [data.guardian_name, data.guardian_relationship, data.guardian_phone].filter(Boolean).join(' · ')],
                  ]
                : []),
        ]],
    ];

    return (
        <form onSubmit={submit} noValidate ref={topRef} className="scroll-mt-20">
            {stepped && <Stepper step={step} onStep={(i) => i < step && setStep(i)} />}

            <div className={cn('grid gap-6', !stepped && 'xl:grid-cols-[1fr_200px]')}>
                <div className="panel min-w-0">
                    {Object.keys(serverErrors).length > 0 && (
                        <div className="border-b border-border p-4">
                            <FormErrorSummary errors={serverErrors} labels={LABELS} />
                        </div>
                    )}
                    <div className="divide-y divide-border px-4 py-6 md:px-6">
                        {show('student') && studentSections}
                        {show('enrolment') && enrolmentSections}
                        {show('parent') && parentSection}
                        {stepped && STEPS[step].id === 'review' && (
                            <div className="space-y-6">
                                {reviewRows.map(([title, target, rows]) => (
                                    <ReviewBlock key={title} title={title} onEdit={() => setStep(target)}>
                                        {rows.map(([k, v]) => (
                                            <ReviewRow key={k} label={k} value={v} />
                                        ))}
                                    </ReviewBlock>
                                ))}

                                <ReviewBlock title="Fees" onEdit={() => setStep(1)}>
                                    {schoolFees.map((f, i) => (
                                        <ReviewRow key={`s${i}`} label={f.title} value={formatMoney(f.amount)} numeric />
                                    ))}
                                    {discount && schoolFees.length > 0 && (
                                        <ReviewRow label={`Discount · ${discount.name}`} value={`−${formatMoney(schoolFees.reduce((t, f) => t + discountOn(f), 0))}`} numeric />
                                    )}
                                    {schoolFees.length === 0 && <ReviewRow label="School fees" value="" empty="None set up for this class yet" />}
                                    {services.map((s, i) => (
                                        <ReviewRow key={`o${i}`} label={`${s.group} · ${s.label}`} value={formatMoney(s.amount)} numeric />
                                    ))}
                                    <div className="grid grid-cols-[1fr_auto] gap-4 bg-muted/60 px-3 py-2 text-sm font-semibold">
                                        <span>Total this term</span>
                                        <span className="tabular">{formatMoney(schoolFees.reduce((t, f) => t + f.amount - discountOn(f), 0) + servicesTotal(options.services, data.services))}</span>
                                    </div>
                                </ReviewBlock>

                                <Agreement
                                    checked={data.terms_accepted}
                                    onChange={(v) => set('terms_accepted', v)}
                                    error={err('terms_accepted')}
                                />

                                <label className={cn('flex items-start gap-3 rounded-lg bg-muted/60 p-4 text-sm', data.parent_mode === 'none' && 'opacity-60')}>
                                    <Checkbox
                                        id="field-send_notices"
                                        className="mt-0.5"
                                        checked={data.send_notices && data.parent_mode !== 'none'}
                                        disabled={data.parent_mode === 'none'}
                                        onCheckedChange={(v) => set('send_notices', v === true)}
                                    />
                                    <span>
                                        <span className="font-medium text-fg">Send admission notices to the parent</span>
                                        <span className="mt-0.5 block text-fg-muted">
                                            Email and SMS: an admission letter with the school policy attached, and a link to the fees statement (school fees and optional services).
                                        </span>
                                    </span>
                                </label>
                            </div>
                        )}
                    </div>

                    {/* Action bar */}
                    <div className="sticky bottom-0 flex items-center justify-between gap-3 rounded-b-lg border-t border-border bg-surface/95 px-4 py-3 backdrop-blur md:px-6">
                        <div>
                            {stepped && step > 0 ? (
                                <Button variant="ghost" onClick={() => setStep((s) => s - 1)} disabled={processing}>
                                    <ArrowLeft />
                                    Back
                                </Button>
                            ) : (
                                <Button variant="ghost" onClick={onCancel} disabled={processing}>
                                    Cancel
                                </Button>
                            )}
                        </div>
                        <div className="flex items-center gap-3">
                            {stepped && <span className="hidden text-sm text-fg-muted sm:inline">Step {step + 1} of {STEPS.length}</span>}
                            {stepped && step < STEPS.length - 1 ? (
                                // Distinct keys: reusing one <button> would turn this click into a form submit.
                                <Button key="continue" variant="primary" onClick={next}>
                                    Continue
                                    <ArrowRight />
                                </Button>
                            ) : (
                                <Button key="submit" type="submit" variant="primary" loading={processing}>
                                    {!processing && <Check />}
                                    {mode === 'create' ? (processing ? 'Submitting…' : 'Submit admission') : processing ? 'Saving…' : 'Save changes'}
                                </Button>
                            )}
                        </div>
                    </div>
                </div>

                {!stepped && <SectionIndex errors={errors} />}
            </div>
        </form>
    );
}

/* ------------------------------------------------------------------ */

function ParentStep({ data, set, err, parents }) {
    const field = (f, label, props = {}) => (
        <FormField label={label} error={err(f)} required={props.required}>
            <Input id={`field-${f}`} value={data[f] ?? ''} onChange={(e) => set(f, e.target.value)} type={props.type} placeholder={props.placeholder} />
        </FormField>
    );
    const primary = data.parent_primary;
    const person = (p, title) => (
        <FormSection
            id={`parent-${p}`}
            title={title}
            description={primary === p ? 'Main contact.' : 'Optional.'}
        >
            {field(`${p}_name`, 'Full name', { required: primary === p })}
            {field(`${p}_phone`, 'Contact number', { type: 'tel' })}
            {field(`${p}_ghana_card`, 'Ghana card #', { placeholder: 'GHA-000000000-0' })}
            {field(`${p}_occupation`, 'Occupation')}
            {field(`${p}_email`, 'Email address', { type: 'email' })}
            {field(`${p}_workplace`, 'Place of work & address')}
            {field(`${p}_residential_address`, 'Residential address')}
            {field(`${p}_postal_address`, 'Postal address')}
        </FormSection>
    );

    return (
        <>
            <FormSection id="guardian" title="Parent / guardian">
                <div className="sm:col-span-2">
                    <div className="grid gap-2 sm:grid-cols-3" role="radiogroup" aria-label="Parent">
                        {[
                            ['existing', 'Existing parent', 'Already has an account', Users],
                            ['new', 'New parent', 'Fill in father, mother, guardian', UserPlus],
                            ['none', 'Add later', 'No parent linked for now', null],
                        ].map(([value, title, hint, Icon]) => (
                            <button
                                key={value}
                                type="button"
                                role="radio"
                                aria-checked={data.parent_mode === value}
                                onClick={() => set('parent_mode', value)}
                                className={cn(
                                    'flex items-start gap-3 rounded-lg p-3 text-left shadow-field transition-shadow',
                                    data.parent_mode === value ? 'bg-primary-soft/50 shadow-[0_0_0_2px_rgb(var(--primary))]' : 'bg-surface hover:shadow-card-hover',
                                )}
                            >
                                <span className={cn('mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-full border', data.parent_mode === value ? 'border-primary' : 'border-border-strong')}>
                                    {data.parent_mode === value && <span className="size-2 rounded-full bg-primary" />}
                                </span>
                                <span className="min-w-0">
                                    <span className="flex items-center gap-1.5 text-sm font-medium">
                                        {Icon && <Icon className="size-3.5 text-fg-muted" />}
                                        {title}
                                    </span>
                                    <span className="block text-xs text-fg-muted">{hint}</span>
                                </span>
                            </button>
                        ))}
                    </div>
                </div>

                {data.parent_mode === 'existing' && (
                    <FormField label="Parent account" required error={err('my_parent_id')} className="sm:col-span-2" hint="Not listed? Choose “New parent” to register them now.">
                        <Combobox id="field-my_parent_id" value={data.my_parent_id} onChange={(v) => set('my_parent_id', v)} options={parents} placeholder="Search for the parent" searchPlaceholder="Search parents by name or email…" />
                    </FormField>
                )}

                {data.parent_mode === 'new' && (
                    <FormField label="Main contact" className="sm:col-span-2" >
                        <Segmented
                            className="self-start"
                            value={primary}
                            onChange={(v) => set('parent_primary', v)}
                            options={[
                                { value: 'father', label: 'Father' },
                                { value: 'mother', label: 'Mother' },
                                { value: 'guardian', label: 'Guardian / nanny' },
                            ]}
                        />
                    </FormField>
                )}

                {data.parent_mode === 'none' && (
                    <p className="text-sm text-fg-muted sm:col-span-2">No notices can't be sent until a parent is linked. You can link one later from the student’s edit page.</p>
                )}
            </FormSection>

            {data.parent_mode === 'new' && (
                <>
                    {person('father', 'Father')}
                    {person('mother', 'Mother')}
                    <FormSection
                        id="parent-guardian"
                        title="Guardian / nanny"
                        description={primary === 'guardian' ? 'Main contact:' : 'Optional.'}
                    >
                        {field('guardian_name', 'Full name', { required: primary === 'guardian' })}
                        {field('guardian_phone', 'Contact number', { type: 'tel' })}
                        {field('guardian_ghana_card', 'Ghana card #')}
                        {field('guardian_relationship', 'Relationship with ward', { placeholder: 'e.g. Aunt' })}
                        <div className="sm:col-span-2">{field('guardian_address', 'Residential address')}</div>
                    </FormSection>
                </>
            )}
        </>
    );
}

function SchoolFeesPreview({ fees, discountOn }) {
    if (!fees.length) {
        return <p className="rounded-lg bg-muted/60 px-3 py-2.5 text-sm text-fg-muted">No school fees are set up for this class yet. They can be added under Fee setup; the student is billed when they exist.</p>;
    }
    return (
        <div className="overflow-hidden rounded-lg shadow-field">
            {fees.map((f, i) => (
                <div key={i} className="border-b border-border last:border-0">
                    <div className="flex items-center justify-between gap-4 px-3 py-2.5 text-sm">
                        <span className="font-medium">{f.title}</span>
                        <span className="tabular font-semibold">{formatMoney(f.amount)}</span>
                    </div>
                    {discountOn(f) > 0 && (
                        <div className="flex justify-between px-3 pb-2 text-xs text-success-fg">
                            <span>Tuition discount</span>
                            <span className="tabular">−{formatMoney(discountOn(f))}</span>
                        </div>
                    )}
                    {f.items.length > 0 && (
                        <ul className="grid gap-x-6 gap-y-1 px-3 pb-2.5 text-xs text-fg-muted sm:grid-cols-2">
                            {f.items.map((it, j) => (
                                <li key={j} className="flex justify-between gap-2">
                                    <span>{it.name}</span>
                                    <span className="tabular">{formatMoney(it.amount)}</span>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            ))}
        </div>
    );
}

function Agreement({ checked, onChange, error }) {
    return (
        <div id="agreement" className={cn('rounded-lg p-4 shadow-field', error && 'shadow-[0_0_0_1.5px_rgb(var(--danger))]')}>
            <h3 className="text-base font-semibold">Parent / guardian agreement</h3>
            <p className="mt-1 text-sm text-fg-muted">From the admission form the parent or guardian signs:</p>
            <ul className="mt-3 list-disc space-y-1 pl-5 text-sm text-fg-muted">
                <li>They will pay all approved fees for the term or year, on or before the deadlines the school sets.</li>
                <li>Fees paid are non-refundable unless the school agrees otherwise in writing.</li>
                <li>Payment is by bank transfer, mobile money, cheque or another approved school platform.</li>
                <li>If fees are not paid, the school may withhold results, deny access to classes or activities, or withdraw the student, and may take lawful steps to recover the debt.</li>
                <li>They have read the school policy and accept full financial responsibility for the learner.</li>
            </ul>
            <label className="mt-4 flex items-start gap-3 text-sm">
                <Checkbox id="field-terms_accepted" className="mt-0.5" checked={checked} onCheckedChange={(v) => onChange(v === true)} />
                <span className="font-medium text-fg">The parent/guardian has signed and accepted this agreement.</span>
            </label>
            {error && <p className="mt-2 text-xs text-danger-fg">{error}</p>}
        </div>
    );
}

function ReviewBlock({ title, onEdit, children }) {
    return (
        <div>
            <div className="mb-2 flex items-center justify-between">
                <h3 className="text-base font-semibold">{title}</h3>
                <Button size="xs" variant="ghost" onClick={onEdit}>
                    <Pencil />
                    Edit
                </Button>
            </div>
            <dl className="divide-y divide-border overflow-hidden rounded-lg shadow-field">{children}</dl>
        </div>
    );
}

function ReviewRow({ label, value, numeric, empty = '—' }) {
    return (
        <div className="grid grid-cols-[180px_1fr] gap-4 px-3 py-2 text-sm">
            <dt className="text-fg-muted">{label}</dt>
            <dd className={cn(!value && 'text-fg-subtle', numeric && 'tabular text-right')}>{value || empty}</dd>
        </div>
    );
}

function Stepper({ step, onStep }) {
    return (
        <ol className="mb-6 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
            {STEPS.map((s, i) => {
                const done = i < step;
                const current = i === step;
                return (
                    <li key={s.id}>
                        <button
                            type="button"
                            onClick={() => onStep(i)}
                            disabled={!done}
                            className={cn(
                                'flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left transition-shadow',
                                current ? 'bg-surface shadow-[0_0_0_2px_rgb(var(--primary))]' : done ? 'bg-surface shadow-card hover:shadow-card-hover' : 'bg-muted/60',
                            )}
                        >
                            <span
                                className={cn(
                                    'flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
                                    done ? 'bg-primary text-white' : current ? 'border-2 border-primary text-primary' : 'border border-border-strong text-fg-subtle',
                                )}
                            >
                                {done ? <Check className="size-3.5" strokeWidth={3} /> : i + 1}
                            </span>
                            <span className="min-w-0">
                                <span className={cn('block truncate text-sm font-medium', !current && !done && 'text-fg-muted')}>{s.title}</span>
                                <span className="block truncate text-xs text-fg-muted">{s.description}</span>
                            </span>
                        </button>
                    </li>
                );
            })}
        </ol>
    );
}

const SECTION_INDEX = [
    ['personal', 'Personal details', ['first_name', 'middle_name', 'last_name', 'gender', 'dob', 'bg_id', 'photo', 'height', 'weight', 'birth_place', 'religion']],
    ['contact', 'Contact & origin', ['email', 'phone', 'phone2', 'address', 'nal_id', 'state_id', 'lga_id']],
    ['background', 'Previous school', ['past_school', 'past_school_address', 'past_qualification']],
    ['health', 'Health', ['allergies', 'medical_conditions']],
    ['insurance', 'Health insurance', ['insurance_card_no', 'insurance_provider', 'insurance_category', 'insurance_coverage_limit', 'insurance_valid_until', 'insurance_start_date']],
    ['doctor', 'Family doctor', ['doctor_name', 'doctor_hospital', 'doctor_contact']],
    ['enrolment', 'Class placement', ['my_class_id', 'section_id', 'admission_date']],
    ['discount', 'Fee discount', ['fee_discount_id']],
    ['services', 'Optional services', ['services']],
    ['boarding', 'Boarding & house', ['dorm_id', 'dorm_room_no', 'house']],
    ['guardian', 'Parent / guardian', ['my_parent_id']],
];

function SectionIndex({ errors }) {
    return (
        <nav className="hidden xl:block" aria-label="Form sections">
            <div className="sticky top-20">
                <div className="overline-label mb-2 px-2">On this page</div>
                <ul className="space-y-0.5">
                    {SECTION_INDEX.map(([id, title, fields]) => {
                        const hasError = fields.some((f) => errors[f]);
                        return (
                            <li key={id}>
                                <a
                                    href={`#${id}`}
                                    className={cn(
                                        'flex h-8 items-center justify-between rounded-md px-2 text-sm transition-colors hover:bg-subtle',
                                        hasError ? 'text-danger-fg' : 'text-fg-muted hover:text-fg',
                                    )}
                                >
                                    {title}
                                    {hasError && <span className="size-1.5 rounded-full bg-danger" />}
                                </a>
                            </li>
                        );
                    })}
                </ul>
            </div>
        </nav>
    );
}
