export function ChartTooltip({ active, payload, label, formatter }) {
    if (!active || !payload?.length) return null;

    return (
        <div className="rounded-lg border border-border bg-popover px-3 py-2 text-xs shadow-md">
            {label && <p className="mb-1 font-medium text-foreground">{label}</p>}
            {payload.map((entry) => (
                <p key={entry.dataKey} className="text-muted-foreground">
                    <span
                        className="mr-2 inline-block size-2 rounded-full"
                        style={{ background: entry.color }}
                    />
                    {formatter ? formatter(entry.value, entry.name) : entry.value}
                </p>
            ))}
        </div>
    );
}
