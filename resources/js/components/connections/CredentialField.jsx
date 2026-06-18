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
            <Label htmlFor={id}>{fieldLabel}</Label>

            <div
                className={cn(
                    'rounded-md border px-3 py-2 text-xs',
                    configured
                        ? 'border-emerald-200 bg-emerald-50/80 text-emerald-900 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200'
                        : 'border-amber-200 bg-amber-50/80 text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200',
                )}
            >
                {configured ? (
                    <>
                        <span className="font-medium">{t('integrations.configured')}</span>
                        {source && <span className="text-muted-foreground"> — {source}</span>}
                        <p className="mt-0.5 font-mono text-[11px] break-all">
                            {field.is_secret
                                ? field.masked ?? '••••••••'
                                : field.value ?? '—'}
                        </p>
                    </>
                ) : (
                    <span className="font-medium">{t('integrations.not_configured_env')}</span>
                )}
            </div>

            <Input
                id={id}
                type={field.is_secret ? 'password' : 'text'}
                value={value}
                onChange={onChange}
                placeholder={
                    configured
                        ? t('integrations.placeholder_keep')
                        : t('integrations.placeholder_enter', { label: fieldLabel.toLowerCase() })
                }
                autoComplete="off"
            />
        </div>
    );
}

export function SecretField({ id, label, isSet, masked, value, onChange, placeholderEmpty }) {
    const t = useT();

    return (
        <div className="space-y-1.5">
            <Label htmlFor={id}>{label}</Label>
            <div
                className={cn(
                    'rounded-md border px-3 py-2 text-xs',
                    isSet
                        ? 'border-emerald-200 bg-emerald-50/80 text-emerald-900 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200'
                        : 'border-amber-200 bg-amber-50/80 text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200',
                )}
            >
                {isSet ? (
                    <>
                        <span className="font-medium">{t('integrations.configured')}</span>
                        {masked && (
                            <p className="mt-0.5 font-mono text-[11px]">{masked}</p>
                        )}
                    </>
                ) : (
                    <span className="font-medium">{t('integrations.not_configured')}</span>
                )}
            </div>
            <Input
                id={id}
                type="password"
                value={value}
                onChange={onChange}
                placeholder={isSet ? t('integrations.placeholder_keep') : placeholderEmpty}
                autoComplete="off"
            />
        </div>
    );
}
