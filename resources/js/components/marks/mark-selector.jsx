import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import { toast } from 'sonner';
import { ArrowRight } from 'lucide-react';
import http from '@/lib/http';
import { Button } from '@/components/ui/button';
import { Combobox, Select } from '@/components/ui/select';

/**
 * Exam / class / section / subject picker. Sections and subjects come from the
 * existing /ajax/get_class_subjects endpoint, which already limits teachers to
 * the subjects they teach. Submitting runs the existing marks.selector action.
 */
export function MarkSelector({ exams, classes, current, urls, compact = false }) {
    const [values, setValues] = useState({
        exam_id: current?.exam_id ?? exams[exams.length - 1]?.id ?? '',
        my_class_id: current?.my_class_id ?? '',
        section_id: current?.section_id ?? '',
        subject_id: current?.subject_id ?? '',
    });
    const [lists, setLists] = useState({ sections: [], subjects: [], bySection: null });
    const [loading, setLoading] = useState(false);
    const [submitting, setSubmitting] = useState(false);

    useEffect(() => {
        if (!values.my_class_id) {
            setLists({ sections: [], subjects: [], bySection: null });
            return;
        }
        let cancelled = false;
        setLoading(true);
        http.get(urls.classSubjects.replace(':id', values.my_class_id))
            .then(({ data }) => {
                if (cancelled) return;
                setLists({ sections: data.sections ?? [], subjects: data.subjects ?? [], bySection: data.bySection ?? null });
                // Keep the current section/subject if still valid, otherwise preselect the only option.
                setValues((v) => ({
                    ...v,
                    section_id: data.sections?.some((s) => String(s.id) === String(v.section_id)) ? v.section_id : data.sections?.length === 1 ? data.sections[0].id : '',
                    subject_id: data.subjects?.some((s) => String(s.id) === String(v.subject_id)) ? v.subject_id : '',
                    // (subjects are narrowed per section below for teachers)
                }));
            })
            .catch(() => !cancelled && toast.error('Could not load sections and subjects.'))
            .finally(() => !cancelled && setLoading(false));
        return () => {
            cancelled = true;
        };
    }, [values.my_class_id, urls.classSubjects]);

    // Teachers: the subjects allowed depend on the section (all subjects in their own class,
    // otherwise only the subjects they teach).
    const subjects = lists.bySection && values.section_id ? lists.subjects.filter((s) => (lists.bySection[values.section_id] ?? []).includes(Number(s.id))) : lists.subjects;
    useEffect(() => {
        if (values.subject_id && !subjects.some((s) => String(s.id) === String(values.subject_id))) setValues((v) => ({ ...v, subject_id: '' }));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [values.section_id, lists]);

    const ready = values.exam_id && values.my_class_id && values.section_id && values.subject_id;
    const unchanged =
        current &&
        ['exam_id', 'my_class_id', 'section_id', 'subject_id'].every((k) => String(current[k]) === String(values[k]));

    const submit = (e) => {
        e.preventDefault();
        if (!ready) return;
        router.post(urls.select, values, {
            onStart: () => setSubmitting(true),
            onFinish: () => setSubmitting(false),
        });
    };

    const set = (k, v) => setValues((s) => ({ ...s, [k]: v }));

    return (
        <form onSubmit={submit} className={compact ? 'flex flex-wrap items-end gap-3' : 'grid gap-4 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_1fr_1.3fr_auto] lg:items-end'}>
            <Field label="Exam" compact={compact}>
                <Select value={values.exam_id} onChange={(v) => set('exam_id', v)} options={exams.map((e) => ({ value: e.id, label: e.name }))} placeholder="Choose exam" size={compact ? 'sm' : 'md'} />
            </Field>
            <Field label="Class" compact={compact}>
                <Combobox
                    value={values.my_class_id}
                    onChange={(v) => set('my_class_id', v)}
                    options={classes.map((c) => ({ value: c.id, label: c.name }))}
                    placeholder="Choose class"
                    clearable={false}
                    className={compact ? 'h-8 text-sm' : undefined}
                />
            </Field>
            <Field label="Section" compact={compact}>
                <Select
                    value={values.section_id}
                    onChange={(v) => set('section_id', v)}
                    options={lists.sections.map((s) => ({ value: s.id, label: s.name }))}
                    placeholder={loading ? 'Loading…' : values.my_class_id ? 'Choose section' : 'Class first'}
                    disabled={!values.my_class_id || loading}
                    size={compact ? 'sm' : 'md'}
                />
            </Field>
            <Field label="Subject" compact={compact}>
                <Combobox
                    value={values.subject_id}
                    onChange={(v) => set('subject_id', v)}
                    options={subjects.map((s) => ({ value: s.id, label: s.name }))}
                    placeholder={loading ? 'Loading…' : values.my_class_id ? (subjects.length ? 'Choose subject' : 'No subjects for you in this class') : 'Class first'}
                    disabled={!values.my_class_id || loading || !subjects.length}
                    clearable={false}
                    className={compact ? 'h-8 text-sm' : undefined}
                />
            </Field>
            <Button type="submit" variant={unchanged ? 'secondary' : 'primary'} size={compact ? 'sm' : 'md'} disabled={!ready || unchanged} loading={submitting}>
                {compact ? 'Switch' : 'Open score sheet'}
                {!compact && <ArrowRight />}
            </Button>
        </form>
    );
}

function Field({ label, children, compact }) {
    return (
        <div className={compact ? 'w-44' : 'min-w-0'}>
            <label className={compact ? 'mb-1 block text-xs font-medium text-fg-muted' : 'mb-1.5 block text-sm font-medium'}>{label}</label>
            {children}
        </div>
    );
}
