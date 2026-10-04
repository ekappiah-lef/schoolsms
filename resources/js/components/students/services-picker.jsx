import { Bus, Music2, NotebookText, Utensils } from 'lucide-react';
import { cn, formatMoney } from '@/lib/utils';
import { Checkbox } from '@/components/ui/checkbox';
import { Select } from '@/components/ui/select';
import { Segmented } from '@/components/ui/tabs';

export const EMPTY_SERVICES = { feeding: [], extracurricular: [], sales: [], bus_route_id: '', bus_direction: '' };

const ICONS = { feeding: Utensils, extracurricular: Music2, sales: NotebookText };

/** The chosen services as invoice lines: [{ group, label, amount }]. */
export function selectedServiceLines(catalogue, value) {
    const lines = [];
    (catalogue?.groups ?? []).forEach((g) => {
        g.options.forEach((o) => {
            if ((value[g.key] ?? []).map(Number).includes(o.id)) lines.push({ group: g.label, label: o.name, amount: o.amount });
        });
    });
    const route = (catalogue?.routes ?? []).find((r) => String(r.id) === String(value.bus_route_id));
    const dir = (catalogue?.directions ?? []).find((d) => d.value === value.bus_direction);
    if (route && dir) {
        lines.splice(lines.filter((l) => l.group === 'Feeding').length, 0, {
            group: 'Bus',
            label: `${route.name} (${dir.label})`,
            amount: value.bus_direction === 'both' ? route.both : route.one_way,
        });
    }
    return lines;
}

export const servicesTotal = (catalogue, value) => selectedServiceLines(catalogue, value).reduce((t, l) => t + l.amount, 0);

/**
 * Optional services on the admission/edit form: feeding, bus, extra-curricular
 * and books. Every choice is optional; prices come from Finance configuration.
 */
export function ServicesPicker({ catalogue, value, onChange, mode = 'create' }) {
    // Shop items are sold at admission; afterwards they are sold and returned under Sales & inventory.
    const groups = (catalogue?.groups ?? []).filter((g) => mode === 'create' || g.key !== 'sales');
    const routes = catalogue?.routes ?? [];
    const toggle = (key, id, on) => {
        const list = (value[key] ?? []).map(Number).filter((v) => v !== id);
        onChange({ ...value, [key]: on ? [...list, id] : list });
    };
    const busOn = !!value.bus_direction;
    const route = routes.find((r) => String(r.id) === String(value.bus_route_id));
    const total = servicesTotal(catalogue, value);

    const group = (g) => {
        const Icon = ICONS[g.key];
        return (
            <ServiceGroup key={g.key} icon={Icon} title={g.label}>
                {g.options.length === 0 ? (
                    <Empty>Nothing configured yet.</Empty>
                ) : (
                    <div className="grid gap-2 sm:grid-cols-3">
                        {g.options.map((o) => {
                            const on = (value[g.key] ?? []).map(Number).includes(o.id);
                            return (
                                <label
                                    key={o.id}
                                    className={cn(
                                        'flex cursor-pointer items-center gap-3 rounded-lg px-3 py-2.5 text-sm shadow-field transition-shadow',
                                        on ? 'bg-primary-soft/40 shadow-[0_0_0_1.5px_rgb(var(--primary))]' : 'bg-surface hover:shadow-card-hover',
                                    )}
                                >
                                    <Checkbox checked={on} onCheckedChange={(v) => toggle(g.key, o.id, v === true)} />
                                    <span className="min-w-0 flex-1 truncate font-medium">
                                        {o.name}
                                        {o.stock !== undefined && <span className="ml-1 text-xs font-normal text-fg-subtle">{o.stock} in stock</span>}
                                    </span>
                                    <span className="tabular text-fg-muted">{formatMoney(o.amount)}</span>
                                </label>
                            );
                        })}
                    </div>
                )}
            </ServiceGroup>
        );
    };

    const feeding = groups.find((g) => g.key === 'feeding');

    return (
        <div id="field-services" className="space-y-5">
            {feeding && group(feeding)}

            <ServiceGroup icon={Bus} title="Bus">
                {routes.length === 0 ? (
                    <Empty>No bus locations configured yet.</Empty>
                ) : (
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <Segmented
                            value={value.bus_direction || ''}
                            onChange={(v) => onChange({ ...value, bus_direction: v, bus_route_id: v ? value.bus_route_id : '' })}
                            options={[{ value: '', label: 'No bus' }, ...(catalogue.directions ?? [])]}
                        />
                        {busOn && (
                            <div className="flex flex-1 items-center gap-3">
                                <Select
                                    value={value.bus_route_id}
                                    onChange={(v) => onChange({ ...value, bus_route_id: v })}
                                    placeholder="Choose location"
                                    options={routes.map((r) => ({ value: r.id, label: `${r.name} · ${formatMoney(value.bus_direction === 'both' ? r.both : r.one_way)}` }))}
                                    className="sm:max-w-xs"
                                />
                                {!route && <span className="text-xs text-warning-fg">Choose a location to bill the bus.</span>}
                            </div>
                        )}
                    </div>
                )}
            </ServiceGroup>

            {groups.filter((g) => g.key !== 'feeding').map(group)}
            {mode !== 'create' && <p className="text-xs text-fg-muted">Uniforms, books and other shop items are sold under Finance → Sales &amp; inventory.</p>}

            <div className="flex items-center justify-between rounded-lg bg-muted/60 px-3 py-2.5 text-sm">
                <span className="text-fg-muted">Optional fees per term</span>
                <span className="tabular font-semibold">{formatMoney(total)}</span>
            </div>
        </div>
    );
}

function ServiceGroup({ icon: Icon, title, children }) {
    return (
        <div>
            <div className="mb-2 flex items-center gap-2 text-sm font-medium">
                {Icon && <Icon className="size-4 text-fg-subtle" />}
                {title}
                <span className="rounded bg-subtle px-1.5 py-px text-2xs font-medium uppercase tracking-wide text-fg-muted">Optional</span>
            </div>
            {children}
        </div>
    );
}

function Empty({ children }) {
    return <p className="text-sm text-fg-subtle">{children} Add prices under Finance configuration.</p>;
}
