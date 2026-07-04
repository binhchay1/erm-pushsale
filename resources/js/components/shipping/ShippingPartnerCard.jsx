import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import { CheckCircle2, Copy, ExternalLink, Save, Unplug } from 'lucide-react';
import { toast } from 'sonner';

import { CredentialField, SecretField } from '@/components/connections/CredentialField';
import { CarrierApiTestPanel } from '@/components/shipping/CarrierApiTestPanel';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { StatusBadge } from '@/components/ui/status-badge';
import { Switch } from '@/components/ui/switch';
import { copyToClipboard } from '@/lib/clipboard';
import { tOr } from '@/lib/i18n-fallback';
import { useT } from '@/providers/I18nProvider';

export function ShippingPartnerCard({ provider }) {
    const t = useT();
    const providerKey = provider.provider;
    const providerLabel = tOr(t, `shipping.providers.${providerKey}.label`, provider.label);
    const providerDescription = tOr(
        t,
        `shipping.providers.${providerKey}.description`,
        provider.description,
    );
    const localizedServices = provider.services?.map((service) => ({
        ...service,
        label: tOr(
            t,
            `shipping.providers.${providerKey}.services.${service.code}`,
            service.label,
        ),
    }));
    const initialCredentials = provider.fields.reduce((acc, field) => {
        acc[field.key] = '';
        return acc;
    }, {});

    const { data, setData, put, processing, recentlySuccessful } = useForm({
        is_enabled: provider.is_enabled,
        webhook_secret: '',
        credentials: initialCredentials,
    });

    useEffect(() => {
        setData('is_enabled', provider.is_enabled);
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [provider.is_enabled]);

    const submit = (e) => {
        e.preventDefault();
        put(`/admin/shipping-partners/${provider.provider}`, {
            preserveScroll: true,
            onSuccess: () => toast.success(t('integrations.saved')),
            onError: (errs) => toast.error(Object.values(errs)[0] ?? t('common.request_failed')),
        });
    };

    return (
        <Card>
            <CardHeader>
                <div className="flex items-start justify-between gap-3">
                    <div>
                        <CardTitle>{providerLabel}</CardTitle>
                        <CardDescription>{providerDescription}</CardDescription>
                    </div>
                    <StatusBadge
                        tone={provider.is_configured ? 'success' : 'warning'}
                        icon={provider.is_configured ? CheckCircle2 : Unplug}
                    >
                        {provider.is_configured ? t('integrations.configured') : t('shipping.missing_info')}
                    </StatusBadge>
                </div>
            </CardHeader>
            <CardContent>
                <form onSubmit={submit} className="space-y-4">
                    <div className="flex items-center justify-between rounded-lg border bg-muted/30 px-4 py-3">
                        <div>
                            <p className="text-sm font-medium">{t('shipping.activate_partner')}</p>
                            <p className="text-xs text-muted-foreground">
                                {t('shipping.activate_partner_desc')}
                            </p>
                        </div>
                        <Switch
                            checked={data.is_enabled}
                            onCheckedChange={(v) => setData('is_enabled', v)}
                        />
                    </div>

                    <SecretField
                        id={`${provider.provider}-webhook-secret`}
                        label={t('shipping.webhook_secret_label')}
                        isSet={provider.webhook_secret_set}
                        masked={provider.webhook_secret_masked}
                        value={data.webhook_secret}
                        onChange={(e) => setData('webhook_secret', e.target.value)}
                        placeholderEmpty={t('shipping.webhook_secret_placeholder')}
                    />

                    {provider.api_base_url && (
                        <div className="rounded-lg border bg-muted/20 px-4 py-3 text-xs">
                            <p className="font-mono text-muted-foreground">
                                {t('shipping.api_base_url')} {provider.api_base_url}
                            </p>
                            {localizedServices?.length > 0 && (
                                <p className="mt-1 text-muted-foreground">
                                    {t('shipping.services_label')}{' '}
                                    {localizedServices.map((s) => `${s.label} (${s.code})`).join(', ')}
                                </p>
                            )}
                        </div>
                    )}

                    <div className="space-y-2">
                        <Label>{t('shipping.webhook_url')}</Label>
                        <div className="flex gap-2">
                            <Input readOnly value={provider.webhook_url} className="font-mono text-xs" />
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                onClick={() => copyToClipboard(provider.webhook_url)}
                            >
                                <Copy className="size-4" />
                            </Button>
                        </div>
                    </div>

                    <div className="grid gap-3 md:grid-cols-2">
                        {provider.fields.map((field) => (
                            <CredentialField
                                key={field.key}
                                id={`${provider.provider}-${field.key}`}
                                field={field}
                                fieldLabelKey={`shipping.providers.${providerKey}.fields`}
                                value={data.credentials[field.key]}
                                onChange={(e) =>
                                    setData('credentials', {
                                        ...data.credentials,
                                        [field.key]: e.target.value,
                                    })
                                }
                            />
                        ))}
                    </div>

                    {provider.provider && (
                        <CarrierApiTestPanel
                            provider={provider.provider}
                            label={providerLabel}
                            testActions={provider.test_actions ?? []}
                        />
                    )}

                    <div className="flex items-center justify-end gap-2">
                        {provider.docs_url && (
                            <Button type="button" variant="ghost" asChild>
                                <a href={provider.docs_url} target="_blank" rel="noreferrer">
                                    <ExternalLink className="size-4" />
                                    {t('shipping.docs')}
                                </a>
                            </Button>
                        )}
                        {recentlySuccessful && (
                            <span className="text-sm text-emerald-600">{t('shipping.saved_success')}</span>
                        )}
                        <Button type="submit" disabled={processing}>
                            <Save className="size-4" />
                            {processing ? t('common.saving') : t('shipping.save_config')}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}
