import { cn } from '@/lib/utils';

/**
 * Section choice as tiles showing enrolment against capacity, e.g.
 * "Class 6 Gold 18/24". Full sections can still be chosen: the admin decides.
 */
export function SectionPicker({ id, className, sections, value, onChange, disabled, invalid }) {
    if (disabled) {
        return <div id={id} className="rounded-lg bg-muted px-3.5 py-2.5 text-sm text-fg-subtle">Choose a class to see its sections</div>;
    }
    if (!sections.length) {
        return <div id={id} className="rounded-lg bg-muted px-3.5 py-2.5 text-sm text-fg-muted">This class has no sections yet. Add one under Sections.</div>;
    }

    return (
        <div id={id} role="radiogroup" tabIndex={-1} className={cn('grid gap-2 rounded-lg sm:grid-cols-2 lg:grid-cols-3', invalid && 'shadow-[0_0_0_1.5px_rgb(var(--danger))] p-1')}>
            {sections.map((s) => {
                const selected = String(s.id) === String(value);
                const cap = s.capacity;
                const pct = cap ? Math.min(100, Math.round((s.enrolled / cap) * 100)) : null;
                const full = cap && s.enrolled >= cap;
                return (
                    <button
                        key={s.id}
                        type="button"
                        role="radio"
                        aria-checked={selected}
                        onClick={() => onChange(String(s.id))}
                        className={cn(
                            'flex flex-col gap-2 rounded-lg p-3 text-left shadow-field transition-shadow',
                            selected ? 'bg-primary-soft/40 shadow-[0_0_0_2px_rgb(var(--primary))]' : 'bg-surface hover:shadow-card-hover',
                        )}
                    >
                        <span className="flex items-center justify-between gap-2">
                            <span className="truncate text-sm font-medium">
                                {className} {s.name}
                            </span>
                            <span className={cn('tabular text-sm font-semibold', full ? 'text-danger-fg' : 'text-fg')}>
                                {cap ? `${Math.min(s.enrolled, cap)}/${cap}` : s.enrolled}
                                {cap && s.enrolled > cap ? <span className="ml-1 text-xs">+{s.enrolled - cap}</span> : null}
                            </span>
                        </span>
                        {cap ? (
                            <span className="h-1.5 overflow-hidden rounded-full bg-subtle">
                                <span className={cn('block h-full rounded-full', full ? 'bg-danger' : pct >= 85 ? 'bg-warning' : 'bg-primary')} style={{ width: `${pct}%` }} />
                            </span>
                        ) : null}
                        <span className={cn('text-xs', full ? 'text-danger-fg' : 'text-fg-muted')}>
                            {cap ? (full ? `Full${s.enrolled > cap ? ` · +${s.enrolled - cap} over capacity` : ''} · can still admit` : `${cap - s.enrolled} places left`) : `${s.enrolled} students · no capacity set`}
                        </span>
                    </button>
                );
            })}
        </div>
    );
}
