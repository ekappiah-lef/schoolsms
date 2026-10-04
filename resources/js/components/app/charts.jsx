import { Bar, BarChart, CartesianGrid, Cell, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { formatNumber } from '@/lib/utils';

/*
 * Chart colours (validated for CVD separation and 3:1 contrast on white):
 * series-1 indigo, series-2 sky-600. Slate is only used for a labelled
 * neutral remainder ("not recorded", "outstanding").
 */
export const SERIES = { one: '#4f46e5', two: '#0284c7', rest: '#cbd5e1' };

const axis = { fontSize: 11, fill: '#94a3b8' };

function TooltipBox({ active, payload, label, suffix = '' }) {
    if (!active || !payload?.length) return null;
    return (
        <div className="min-w-[140px] rounded-lg border border-border bg-surface px-3 py-2 text-xs shadow-lg">
            <div className="mb-1 font-semibold text-fg">{label}</div>
            {payload.map((p) => (
                <div key={p.dataKey} className="flex items-center justify-between gap-4 py-0.5">
                    <span className="flex items-center gap-1.5 text-fg-muted">
                        <span className="size-2 rounded-[2px]" style={{ background: p.color || p.fill }} />
                        {p.name}
                    </span>
                    <span className="tabular font-medium text-fg">
                        {formatNumber(p.value)}
                        {suffix}
                    </span>
                </div>
            ))}
        </div>
    );
}

export function Legend({ items }) {
    return (
        <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-fg-muted">
            {items.map((i) => (
                <span key={i.label} className="inline-flex items-center gap-1.5">
                    <span className="size-2 rounded-[2px]" style={{ background: i.color }} />
                    {i.label}
                </span>
            ))}
        </div>
    );
}

/** Vertical stacked bars, e.g. enrolment per class split by gender. */
export function StackedBars({ data, xKey, series, height = 240, onBarClick }) {
    // Angle category labels when there are too many to sit side by side.
    const crowded = data.length > 7;
    return (
        <ResponsiveContainer width="100%" height={height + (crowded ? 24 : 0)}>
            <BarChart data={data} margin={{ top: 8, right: 4, bottom: 0, left: -18 }} barCategoryGap="28%">
                <CartesianGrid vertical={false} stroke="#e2e8f0" />
                <XAxis
                    dataKey={xKey}
                    tick={crowded ? { ...axis, angle: -35, textAnchor: 'end' } : axis}
                    height={crowded ? 48 : 30}
                    tickLine={false}
                    axisLine={{ stroke: '#e2e8f0' }}
                    interval={0}
                />
                <YAxis tick={axis} tickLine={false} axisLine={false} allowDecimals={false} />
                <Tooltip cursor={{ fill: 'rgb(241 245 249 / 0.7)' }} content={<TooltipBox />} />
                {series.map((s, i) => (
                    <Bar
                        key={s.key}
                        isAnimationActive={false}
                        dataKey={s.key}
                        name={s.label}
                        stackId="a"
                        fill={s.color}
                        stroke="#fff"
                        strokeWidth={1}
                        radius={i === series.length - 1 ? [4, 4, 0, 0] : 0}
                        cursor={onBarClick ? 'pointer' : undefined}
                        onClick={onBarClick ? (d) => onBarClick(d.payload ?? d) : undefined}
                        maxBarSize={40}
                    />
                ))}
            </BarChart>
        </ResponsiveContainer>
    );
}

/** Horizontal bars for a single measure (class averages). */
export function HorizontalBars({ data, labelKey, valueKey, name, domain = [0, 100], height, suffix = '', color = SERIES.one, highlight, format, labelWidth = 84 }) {
    const h = height ?? Math.max(120, data.length * 34 + 24);
    return (
        <ResponsiveContainer width="100%" height={h}>
            <BarChart data={data} layout="vertical" margin={{ top: 0, right: 40, bottom: 0, left: 0 }} barCategoryGap="30%">
                <CartesianGrid horizontal={false} stroke="#e2e8f0" />
                <XAxis type="number" domain={domain} tick={axis} tickLine={false} axisLine={false} tickFormatter={format} />
                <YAxis type="category" dataKey={labelKey} tick={{ ...axis, fill: '#475569' }} tickLine={false} axisLine={false} width={labelWidth} />
                <Tooltip cursor={{ fill: 'rgb(241 245 249 / 0.7)' }} content={<TooltipBox suffix={suffix} />} />
                <Bar
                    dataKey={valueKey}
                    isAnimationActive={false}
                    name={name}
                    radius={[0, 4, 4, 0]}
                    maxBarSize={18}
                    label={{ position: 'right', fontSize: 11, fill: '#475569', formatter: (v) => (format ? format(v) : `${v}${suffix}`) }}
                >
                    {data.map((d) => (
                        <Cell key={d[labelKey]} fill={highlight && !highlight(d) ? SERIES.rest : color} />
                    ))}
                </Bar>
            </BarChart>
        </ResponsiveContainer>
    );
}

/** Part-to-whole meter (collected vs expected). */
export function Meter({ value, max, color = SERIES.one }) {
    const pct = max ? Math.min(100, (value / max) * 100) : 0;
    return (
        <div className="h-2 w-full overflow-hidden rounded-full bg-subtle" role="meter" aria-valuenow={Math.round(pct)} aria-valuemin={0} aria-valuemax={100}>
            <div className="h-full rounded-full" style={{ width: `${pct}%`, background: color }} />
        </div>
    );
}
