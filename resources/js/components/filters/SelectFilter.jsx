import { Label } from '@/components/ui/label';
import { useT } from '@/providers/I18nProvider';

export function SelectFilter({ label, name, value, options, onChange, placeholder }) {
    const t = useT();

    return (
        <div className="space-y-1.5">
            <Label className="text-xs font-medium text-muted-foreground">{label}</Label>
            <select
                name={name}
                value={value ?? ''}
                onChange={(e) => onChange(name, e.target.value || null)}
                className="h-9 w-full rounded-lg border border-transparent bg-muted/70 px-2.5 text-sm transition-colors outline-none hover:bg-muted focus:border-ring focus:bg-card focus:ring-2 focus:ring-ring/30 dark:bg-input/30 dark:focus:bg-input/50"
            >
                <option value="">{placeholder ?? t('common.select_all')}</option>
                {(options ?? []).map((opt) => (
                    <option key={opt.value ?? opt.id} value={opt.value ?? opt.id}>
                        {opt.label ?? opt.name}
                    </option>
                ))}
            </select>
        </div>
    );
}
