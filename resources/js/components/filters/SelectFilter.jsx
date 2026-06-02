import { Label } from '@/components/ui/label';

export function SelectFilter({ label, name, value, options, onChange, placeholder }) {
    return (
        <div className="space-y-1">
            <Label className="text-xs text-muted-foreground">{label}</Label>
            <select
                name={name}
                value={value ?? ''}
                onChange={(e) => onChange(name, e.target.value || null)}
                className="h-8 w-full rounded-lg border border-input bg-background px-2 text-sm"
            >
                <option value="">{placeholder ?? '— Tất cả —'}</option>
                {(options ?? []).map((opt) => (
                    <option key={opt.value ?? opt.id} value={opt.value ?? opt.id}>
                        {opt.label ?? opt.name}
                    </option>
                ))}
            </select>
        </div>
    );
}
