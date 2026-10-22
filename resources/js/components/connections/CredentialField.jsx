import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useT } from '@/providers/I18nProvider';
import { cn } from '@/lib/utils';
import { tOr } from '@/lib/i18n-fallback';

function useSourceLabel(source) {
    const t = useT();
    if (source === 'env' || source === 'environment') return t('integrations.from_env');
    if (source === 'db' || source === 'database') return t('integrations.from_saved');
    return null;
}

export function CredentialField({ id, field, value, onChange, className, fieldLabelKey = 'integrations.fields' }) {
    const t = useT();
    const configured = Boolean(field.is_set);
    const source = useSourceLabel(field.source);
    const fieldLabel = tOr(t, `${fieldLabelKey}.${field.key}`, field.label);

    return (
        <div className={cn('space-y-1.5', className)}>
            <div className="flex items-center justify-between gap-2">
                <Label htmlFor={id} className="text-xs font-semibold text-slate-700">
                    {fieldLabel}
                    {field.required && <span className="ml-0.5 text-rose-500">*</span>}
                </Label>
                <div className="flex items-center gap-1.5 text-[11px]">
                    {configured ? (
                        <span className="inline-flex items-center gap-1 rounded bg-emerald-50 px-1.5 py-0.5 font-medium text-emerald-700 border border-emerald-200">
                            <span className="inline-block rounded-full bg-emerald-500" style={{ width: 6, height: 6 }} />
                            <span>{source || t('integrations.configured')}</span>
                            {field.is_secret ? (
                                <span className="font-mono text-slate-400">({field.masked ?? '••••••••'})</span>
                            ) : field.value ? (
                                <span className="max-w-[130px] truncate font-mono text-slate-600" title={String(field.value)}>
                                    ({field.value})
                                </span>
                            ) : null}
                        </span>
                    ) : (
                        <span className="inline-flex items-center gap-1 rounded bg-slate-100 px-1.5 py-0.5 font-medium text-slate-500 border border-slate-200">
                            <span className="inline-block rounded-full bg-slate-400" style={{ width: 6, height: 6 }} />
                            <span>{t('integrations.not_configured')}</span>
                        </span>
                    )}
                </div>
            </div>

            <Input
                id={id}
                type={field.is_secret ? 'password' : 'text'}
                value={value}
                onChange={onChange}
                placeholder={
                    configured
                        ? (field.is_secret ? '•••••••• (' + t('integrations.placeholder_keep') + ')' : String(field.value ?? t('integrations.placeholder_keep')))
                        : t('integrations.placeholder_enter', { label: fieldLabel.toLowerCase() })
                }
                autoComplete="off"
                className="h-8.5 font-mono text-xs bg-white text-slate-900 border-slate-300 shadow-none focus-visible:border-blue-500 focus-visible:ring-1 focus-visible:ring-blue-500"
            />
        </div>
    );
}

export function SecretField({ id, label, isSet, masked, value, onChange, placeholderEmpty }) {
    const t = useT();

    return (
        <div className="space-y-1.5">
            <div className="flex items-center justify-between gap-2">
                <Label htmlFor={id} className="text-xs font-semibold text-slate-700">{label}</Label>
                <div className="flex items-center gap-1.5 text-[11px]">
                    {isSet ? (
                        <span className="inline-flex items-center gap-1 rounded bg-emerald-50 px-1.5 py-0.5 font-medium text-emerald-700 border border-emerald-200">
                            <span className="inline-block rounded-full bg-emerald-500" style={{ width: 6, height: 6 }} />
                            <span>{t('integrations.configured')}</span>
                            {masked && <span className="font-mono text-slate-400">({masked})</span>}
                        </span>
                    ) : (
                        <span className="inline-flex items-center gap-1 rounded bg-slate-100 px-1.5 py-0.5 font-medium text-slate-500 border border-slate-200">
                            <span className="inline-block rounded-full bg-slate-400" style={{ width: 6, height: 6 }} />
                            <span>{t('integrations.not_configured')}</span>
                        </span>
                    )}
                </div>
            </div>
            <Input
                id={id}
                type="password"
                value={value}
                onChange={onChange}
                placeholder={isSet ? '•••••••• (' + t('integrations.placeholder_keep') + ')' : placeholderEmpty}
                autoComplete="off"
                className="h-8.5 font-mono text-xs bg-white text-slate-900 border-slate-300 shadow-none focus-visible:border-blue-500 focus-visible:ring-1 focus-visible:ring-blue-500"
            />
        </div>
    );
}
