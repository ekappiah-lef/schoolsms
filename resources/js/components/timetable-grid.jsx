import { useState } from 'react';
import { cn, formatDate } from '@/lib/utils';

// Soft colours, one per subject, in the order subjects first appear.
export const COLOURS = [
    'bg-sky-100 text-sky-900',
    'bg-rose-100 text-rose-900',
    'bg-lime-100 text-lime-900',
    'bg-amber-100 text-amber-900',
    'bg-violet-100 text-violet-900',
    'bg-teal-100 text-teal-900',
    'bg-orange-100 text-orange-900',
    'bg-indigo-100 text-indigo-900',
    'bg-emerald-100 text-emerald-900',
    'bg-fuchsia-100 text-fuchsia-900',
];

/**
 * A timetable the way schools print it: days down the side, periods across the top (start and end
 * times, period numbers), breaks as one tall column across every day, and a subject that runs over
 * periods next to each other shown as one wide cell. `slots` and `rows` come from TimeTableController::grid().
 */
export function TimetableGrid({ isExam, slots, rows }) {
    let n = 0;
    const periodNo = slots.map((s) => (s.label ? null : ++n));

    return (
        <div className="scrollbar-thin overflow-x-auto">
            <table className="w-full min-w-[760px] border-collapse text-sm">
                <thead>
                    <tr className="bg-canvas text-xs text-fg-muted">
                        <th rowSpan={2} className="w-32 border border-border px-3 py-2 text-left font-semibold uppercase">
                            {isExam ? 'Date' : 'Day'}
                        </th>
                        {slots.map((s) => (
                            <th key={s.id} className={cn('tabular border border-border px-2 py-1.5 font-medium', s.label && 'w-12')}>
                                <div className="whitespace-nowrap">{s.from}</div>
                                <div className="whitespace-nowrap text-fg-subtle">{s.to}</div>
                            </th>
                        ))}
                    </tr>
                    <tr className="bg-canvas text-xs font-semibold text-fg-muted">
                        {slots.map((s, i) => (
                            <th key={s.id} className="border border-border px-2 py-1">
                                {periodNo[i] ?? ''}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {rows.map((r) => (
                        <tr key={r.day}>
                            <th className="border border-border bg-canvas px-3 py-2 text-left font-semibold uppercase">
                                {isExam ? (
                                    <>
                                        {formatDate(r.day, 'EEEE')}
                                        <div className="text-xs font-normal normal-case text-fg-muted">{formatDate(r.day, 'dd/MM/yyyy')}</div>
                                    </>
                                ) : (
                                    r.day
                                )}
                            </th>
                            {r.cells.map((c, i) =>
                                c.kind === 'break' ? (
                                    <td key={i} rowSpan={c.rowspan} className="border border-border bg-muted/50 p-0 text-center align-middle">
                                        <span className="inline-block py-2 text-xs font-semibold tracking-[0.15em] text-fg-muted [writing-mode:vertical-rl]">{c.text}</span>
                                    </td>
                                ) : c.kind === 'activity' ? (
                                    <td key={i} className="h-16 border border-border bg-muted/50 px-2 text-center text-xs font-semibold uppercase tracking-[0.1em] text-fg-muted">
                                        {c.text}
                                    </td>
                                ) : (
                                    <td key={i} colSpan={c.colspan} className={cn('h-16 border border-border px-2 text-center text-xs font-medium leading-tight', c.kind === 'subject' ? COLOURS[c.colour % COLOURS.length] : '')}>
                                        {c.text ?? ''}
                                    </td>
                                ),
                            )}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

/** Phones: one day at a time, each period on its own line, breaks and activities as their own rows. */
export function TimetableDay({ isExam, byDay }) {
    const today = new Date().toLocaleDateString('en-GB', { weekday: 'long' });
    const [at, setAt] = useState(() => Math.max(0, byDay.findIndex((d) => d.day === today)));
    const day = byDay[at];
    const label = (d) => (isExam ? formatDate(d, 'EEE dd/MM') : d.slice(0, 3));
    if (!day) return null;
    return (
        <div className="flex flex-col">
            <div className="scrollbar-thin flex gap-1.5 overflow-x-auto border-b border-border p-3">
                {byDay.map((d, i) => (
                    <button
                        key={d.day}
                        type="button"
                        onClick={() => setAt(i)}
                        className={cn('h-10 min-w-14 shrink-0 rounded-md px-3 text-sm font-semibold', i === at ? 'bg-primary text-white' : 'bg-muted text-fg-muted')}
                    >
                        {label(d.day)}
                    </button>
                ))}
            </div>
            <ol className="flex flex-col divide-y divide-border">
                {day.periods.map((p, i) => (
                    <li key={i} className="flex items-stretch gap-3 px-3 py-2">
                        <div className="tabular w-20 shrink-0 py-1 text-xs leading-tight text-fg-muted">
                            {p.from}
                            <br />
                            {p.to}
                        </div>
                        <div
                            className={cn(
                                'flex min-h-11 flex-1 items-center rounded-md px-3 text-sm',
                                p.kind === 'subject' && cn('font-medium', COLOURS[p.colour % COLOURS.length]),
                                (p.kind === 'break' || p.kind === 'activity') && 'bg-muted/70 text-xs font-semibold uppercase tracking-[0.1em] text-fg-muted',
                                p.kind === 'empty' && 'text-fg-subtle',
                            )}
                        >
                            {p.text || '—'}
                        </div>
                    </li>
                ))}
            </ol>
        </div>
    );
}
