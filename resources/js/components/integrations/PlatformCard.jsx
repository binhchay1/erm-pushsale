import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { CheckCircle2, Copy, ExternalLink, FlaskConical, RefreshCw, Unplug } from 'lucide-react';
import { toast } from 'sonner';

import { ConnectionTestResult } from '@/components/connections/ConnectionTestResult';
import { CredentialField, SecretField } from '@/components/connections/CredentialField';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { StatusBadge } from '@/components/ui/status-badge';
import { Switch } from '@/components/ui/switch';
import { apiPost } from '@/lib/api';
import { copyToClipboard } from '@/lib/clipboard';
import { formatDateTime } from '@/lib/format';
import { tOr } from '@/lib/i18n-fallback';
import { useT } from '@/providers/I18nProvider';

export function PlatformCard({ platform }) {
    const t = useT();
    const platformKey = platform.platform;
    const platformLabel = tOr(t, `integrations.platforms.${platformKey}.label`, platform.label);
    const platformDescription = tOr(
        t,
        `integrations.platforms.${platformKey}.description`,
        platform.description,
    );
    const [testing, setTesting] = useState(false);
    const [testResult, setTestResult] = useState(null);

    const initialCredentials = platform.fields.reduce((acc, field) => {
        acc[field.key] = '';
        return acc;
    }, {});

    const { data, setData, put, processing, recentlySuccessful, reset } = useForm({
        is_enabled: platform.is_enabled,
        verify_token: '',
        webhook_secret: '',
        credentials: initialCredentials,
    });

    const copyUrl = async (url) => {
        const ok = await copyToClipboard(url);
        ok
            ? toast.success(t('integrations.copy_webhook_success'))
            : toast.error(t('integrations.copy_webhook_failed'));
    };

    const save = (e) => {
        e.preventDefault();
        put(`/admin/integrations/${platform.platform}`, { preserveScroll: true });
    };

    const testWebhook = async () => {
        setTesting(true);
        const actionLabel = t('integrations.test_webhook');
        try {
            const res = await apiPost(`/admin/integrations/${platform.platform}/test`);
            setTestResult({ data: res, actionLabel });
            toast.success(res.message ?? t('integrations.test_webhook_sent'));
        } catch (e) {
            setTestResult({ error: e.message, actionLabel });
            toast.error(e.message);
        } finally {
            setTesting(false);
        }
    };

    return (
        <Card id={platform.platform} className="scroll-mt-24">
            <CardHeader className="pb-3">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="space-y-1">
                        <CardTitle className="text-lg">{platformLabel}</CardTitle>
                        <CardDescription>{platformDescription}</CardDescription>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <StatusBadge
                            tone={platform.is_configured ? 'success' : 'warning'}
                            icon={platform.is_configured ? CheckCircle2 : Unplug}
                        >
                            {platform.is_configured
                                ? t('integrations.configured')
                                : t('integrations.not_enough_info')}
                        </StatusBadge>
                        <StatusBadge
                            tone={platform.is_enabled ? 'success' : 'warning'}
                            icon={platform.is_enabled ? CheckCircle2 : Unplug}
                        >
                            {platform.is_enabled ? t('integrations.enabled_on') : t('integrations.enabled_off')}
                        </StatusBadge>
                    </div>
                </div>
            </CardHeader>
            <CardContent>
                <form onSubmit={save} className="space-y-4">
                    <div className="flex items-center justify-between rounded-lg border bg-muted/30 px-4 py-3">
                        <div>
                            <p className="text-sm font-medium">{t('integrations.enable_webhook')}</p>
                            <p className="text-xs text-muted-foreground">{t('integrations.enable_webhook_hint')}</p>
                        </div>
                        <Switch
                            checked={data.is_enabled}
                            onCheckedChange={(v) => setData('is_enabled', v)}
                        />
                    </div>

                    <div className="space-y-2">
                        <Label>{t('integrations.webhook_url')}</Label>
                        <div className="flex gap-2">
                            <Input readOnly value={platform.webhook_url} className="font-mono text-xs" />
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                onClick={() => copyUrl(platform.webhook_url)}
                            >
                                <Copy className="size-4" />
                            </Button>
                        </div>
                        {platform.platform === 'landing' && (
                            <p className="text-xs text-muted-foreground">
                                {t('integrations.bearer_post')}{' '}
                                <code className="rounded bg-muted px-1">{platform.api_leads_url}</code>
                            </p>
                        )}
                    </div>

                    {platform.platform === 'facebook' && (
                        <div className="grid gap-3 sm:grid-cols-2">
                            <SecretField
                                id={`${platform.platform}-verify`}
                                label={t('integrations.meta_verify')}
                                isSet={platform.verify_token_set}
                                masked={platform.verify_token_masked}
                                value={data.verify_token}
                                onChange={(e) => setData('verify_token', e.target.value)}
                                placeholderEmpty={t('integrations.meta_verify_ph')}
                            />
                            <SecretField
                                id={`${platform.platform}-secret`}
                                label={t('integrations.meta_secret')}
                                isSet={platform.webhook_secret_set}
                                masked={platform.webhook_secret_masked}
                                value={data.webhook_secret}
                                onChange={(e) => setData('webhook_secret', e.target.value)}
                                placeholderEmpty={t('integrations.meta_secret_ph')}
                            />
                        </div>
                    )}

                    {platform.platform !== 'facebook' && (
                        <SecretField
                            id={`${platform.platform}-webhook-secret`}
                            label={t('integrations.webhook_secret_api')}
                            isSet={platform.webhook_secret_set}
                            masked={platform.webhook_secret_masked}
                            value={data.webhook_secret}
                            onChange={(e) => setData('webhook_secret', e.target.value)}
                            placeholderEmpty={t('integrations.webhook_secret_ph')}
                        />
                    )}

                    <div className="space-y-3">
                        <p className="text-sm font-medium">{t('integrations.api_credentials')}</p>
                        {platform.fields.map((field) => (
                            <CredentialField
                                key={field.key}
                                id={`${platform.platform}-${field.key}`}
                                field={field}
                                fieldLabelKey="integrations.fields"
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

                    {platform.last_synced_at && (
                        <p className="text-xs text-muted-foreground">
                            {t('integrations.last_synced')} {formatDateTime(platform.last_synced_at)}
                        </p>
                    )}

                    <div className="rounded-lg border border-dashed border-primary/30 bg-primary/5 p-4">
                        <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                            <div className="flex items-center gap-2">
                                <FlaskConical className="size-4 text-primary" />
                                <p className="text-sm font-semibold">{t('integrations.test_webhook_title')}</p>
                            </div>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                disabled={testing}
                                onClick={testWebhook}
                            >
                                {testing ? t('integrations.testing') : t('integrations.test_webhook')}
                            </Button>
                        </div>
                        <p className="text-xs text-muted-foreground">{t('integrations.test_webhook_desc')}</p>
                        <ConnectionTestResult result={testResult} actionLabel={testResult?.actionLabel} />
                    </div>

                    <div className="flex flex-wrap items-center justify-end gap-2 pt-2">
                        {recentlySuccessful && (
                            <span className="text-sm text-emerald-600">{t('integrations.saved')}</span>
                        )}
                        {platform.docs_url && (
                            <Button type="button" variant="ghost" size="sm" asChild>
                                <a href={platform.docs_url} target="_blank" rel="noreferrer">
                                    <ExternalLink className="size-4" />
                                    {t('integrations.docs')}
                                </a>
                            </Button>
                        )}
                        <Button type="button" variant="ghost" size="sm" onClick={() => reset()}>
                            <RefreshCw className="size-4" />
                            {t('integrations.reset_form')}
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing ? t('common.saving') : t('integrations.save_config')}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}
