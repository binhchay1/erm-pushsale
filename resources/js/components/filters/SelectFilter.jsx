import { Label } from '@/components/ui/label';
import { useT } from '@/providers/I18nProvider';

export function SelectFilter({ label, name, value, options, onChange, placeholder }) {
    const t = useT();

    return (
        <div className="space-y-1.5">
            <Label className="text-xs font-medium text-foreground/80">{label}</Label>
            <select
                name={name}
                value={value ?? ''}
                onChange={(e) => onChange(name, e.target.value || null)}
                className="h-9 w-full rounded-lg border border-border bg-background px-2.5 text-sm text-foreground transition-colors outline-none hover:bg-muted/50 focus:border-ring focus:ring-2 focus:ring-ring/30 dark:border-border dark:bg-card dark:text-foreground"
            >
                <option value="" className="bg-background text-foreground">
                    {placeholder ?? t('common.select_all')}
                </option>
                {(options ?? []).map((opt) => (
                    <option
                        key={opt.value ?? opt.id}
                        value={opt.value ?? opt.id}
                        className="bg-background text-foreground"
                    >
                        {opt.label ?? opt.name}
                    </option>
                ))}
            </select>
        </div>
    );
}
