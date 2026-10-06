import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowRight, GraduationCap, Search } from 'lucide-react';
import { withAppLayout } from '@/layouts/AppLayout';
import { Field, ModuleHeader, NativeSelect } from '@/components/app/module';
import { EmptyState, Panel } from '@/components/app/page';
import { Avatar } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Segmented } from '@/components/ui/tabs';
import { cn } from '@/lib/utils';

const CHOICES = [
    { value: 'P', label: 'Promote' },
    { value: 'D', label: 'Repeat' },
    { value: 'G', label: 'Graduate' },
];

/** Promote a class/section from this year to next year: each student is promoted, repeats or graduates. */
export default function Promotions({ oldYear, newYear, classes, sections, selected, students, urls }) {
    const [sel, setSel] = useState(() => ({
        fc: String(selected?.fc ?? ''),
        fs: String(selected?.fs ?? ''),
        tc: String(selected?.tc ?? ''),
        ts: String(selected?.ts ?? ''),
    }));
    // Students in the final class graduate by default; everyone else is promoted.
    const isFinal = selected && String(selected.fc) === String(selected.tc) && classes[classes.length - 1]?.id === selected.fc;
    const [choice, setChoice] = useState(() => Object.fromEntries(students.map((s) => [s.id, isFinal ? 'G' : 'P'])));
    const [processing, setProcessing] = useState(false);
    const secs = (cls) => sections.filter((x) => String(x.class_id) === String(cls));
    const name = (id) => classes.find((c) => String(c.id) === String(id))?.name;
    const secName = (id) => sections.find((x) => String(x.id) === String(id))?.name;
    const ready = sel.fc && sel.fs && sel.tc && sel.ts;

    const pick = (k, v) =>
        setSel((s) => {
            const n = { ...s, [k]: v };
            if (k === 'fc') {
                n.fs = secs(v)[0] ? String(secs(v)[0].id) : '';
                // Suggest the next class up (the final class stays where it is, to graduate).
                const i = classes.findIndex((c) => String(c.id) === String(v));
                const next = classes[i + 1] ?? classes[i];
                n.tc = next ? String(next.id) : '';
                const ns = secs(n.tc).find((x) => x.name === secs(v).find((y) => String(y.id) === n.fs)?.name) ?? secs(n.tc)[0];
                n.ts = ns ? String(ns.id) : '';
            }
            if (k === 'tc') n.ts = secs(v)[0] ? String(secs(v)[0].id) : '';
            return n;
        });
    const load = () => router.get(`${urls.base}/${sel.fc}/${sel.fs}/${sel.tc}/${sel.ts}`);
    const setAll = (v) => setChoice(Object.fromEntries(students.map((s) => [s.id, v])));
    const counts = CHOICES.map((c) => ({ ...c, n: Object.values(choice).filter((v) => v === c.value).length }));

    const promote = () =>
        router.post(
            urls.promote,
            Object.fromEntries(students.map((s) => [`p-${s.id}`, choice[s.id]])),
            { onStart: () => setProcessing(true), onFinish: () => setProcessing(false) },
        );

    return (
        <>
            <Head title="Promotion" />
            <div className="mx-auto flex w-full max-w-5xl flex-col gap-6">
                <ModuleHeader
                    crumbs={['Students', 'Promotion']}
                    title="Student Promotion"
                    aside={
                        <Button asChild>
                            <Link href={urls.manage}>Manage promotions</Link>
                        </Button>
                    }
                />

                <Panel title="Classes">
                    <div className="grid items-end gap-4 md:grid-cols-[1fr_1fr_auto_1fr_1fr]">
                        <Field label={`From class (${oldYear})`}>
                            <NativeSelect value={sel.fc} onChange={(v) => pick('fc', v)}>
                                <option value="" disabled>
                                    Select class
                                </option>
                                {classes.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.name}
                                    </option>
                                ))}
                            </NativeSelect>
                        </Field>
                        <Field label="Section">
                            <NativeSelect value={sel.fs} onChange={(v) => pick('fs', v)} disabled={!sel.fc}>
                                {secs(sel.fc).map((x) => (
                                    <option key={x.id} value={x.id}>
                                        {x.name}
                                    </option>
                                ))}
                            </NativeSelect>
                        </Field>
                        <ArrowRight className="mb-2.5 hidden size-5 text-fg-subtle md:block" />
                        <Field label={`To class (${newYear})`}>
                            <NativeSelect value={sel.tc} onChange={(v) => pick('tc', v)}>
                                <option value="" disabled>
                                    Select class
                                </option>
                                {classes.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.name}
                                    </option>
                                ))}
                            </NativeSelect>
                        </Field>
                        <Field label="Section">
                            <NativeSelect value={sel.ts} onChange={(v) => pick('ts', v)} disabled={!sel.tc}>
                                {secs(sel.tc).map((x) => (
                                    <option key={x.id} value={x.id}>
                                        {x.name}
                                    </option>
                                ))}
                            </NativeSelect>
                        </Field>
                    </div>
                    <div className="mt-4 flex justify-end">
                        <Button variant="primary" onClick={load} disabled={!ready}>
                            <Search />
                            Show students
                        </Button>
                    </div>
                </Panel>

                {selected && (
                    <Panel
                        title={`${name(selected.fc)} ${secName(selected.fs) ?? ''} → ${name(selected.tc)} ${secName(selected.ts) ?? ''}`}
                        description={counts.map((c) => `${c.n} ${c.label.toLowerCase()}`).join(' · ')}
                        actions={
                            <div className="flex items-center gap-2 text-sm text-fg-muted">
                                Set all
                                <Segmented size="sm" value="" onChange={setAll} options={CHOICES} />
                            </div>
                        }
                        flush
                        footer={
                            <div className="flex justify-end">
                                <Button variant="primary" onClick={promote} disabled={processing || !students.length}>
                                    <GraduationCap />
                                    {processing ? 'Saving…' : `Save promotion for ${students.length} students`}
                                </Button>
                            </div>
                        }
                    >
                        {students.length ? (
                            <ul className="divide-y divide-border">
                                {students.map((s, i) => (
                                    <li key={s.id} className="flex flex-wrap items-center gap-3 px-5 py-2.5">
                                        <span className="tabular w-6 text-sm text-fg-subtle">{i + 1}</span>
                                        <Avatar src={s.photo} name={s.name} size="md" />
                                        <div className="min-w-0 flex-1">
                                            <div className="truncate font-medium">{s.name}</div>
                                            <div className="text-xs text-fg-muted">{s.adm_no}</div>
                                        </div>
                                        <div className={cn('rounded-lg', choice[s.id] === 'G' && 'ring-1 ring-primary/40', choice[s.id] === 'D' && 'ring-1 ring-warning/50')}>
                                            <Segmented size="sm" value={choice[s.id]} onChange={(v) => setChoice((c) => ({ ...c, [s.id]: v }))} options={CHOICES} />
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <EmptyState compact title="No students in this class for the year" />
                        )}
                    </Panel>
                )}
            </div>
        </>
    );
}

Promotions.layout = withAppLayout;
