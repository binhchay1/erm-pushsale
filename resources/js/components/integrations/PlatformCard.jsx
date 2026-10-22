import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Check, Copy, ExternalLink, FlaskConical, RefreshCw, Sparkles } from 'lucide-react';
import { toast } from 'sonner';

import { ConnectionTestResult } from '@/components/connections/ConnectionTestResult';
import { CredentialField, SecretField } from '@/components/connections/CredentialField';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { apiPost } from '@/lib/api';
import { copyToClipboard } from '@/lib/clipboard';
import { formatDateTime } from '@/lib/format';
import { tOr } from '@/lib/i18n-fallback';
import { cn } from '@/lib/utils';
import { useT } from '@/providers/I18nProvider';

const PLATFORM_META = {
    pancake: {
        tag: 'POS & Chat đa kênh',
        badgeClass: 'bg-orange-50 text-orange-600 border-orange-200',
        initials: 'PC',
    },
    facebook: {
        tag: 'Lead Ads / Fanpage',
        badgeClass: 'bg-blue-50 text-blue-600 border-blue-200',
        initials: 'FB',
    },
    tiktok: {
        tag: 'Lead Gen / Shop',
        badgeClass: 'bg-slate-100 text-slate-700 border-slate-200',
        initials: 'TT',
    },
    zalo: {
        tag: 'Zalo OA',
        badgeClass: 'bg-sky-50 text-sky-600 border-sky-200',
        initials: 'ZA',
    },
    google: {
        tag: 'Google Ads Lead',
        badgeClass: 'bg-rose-50 text-rose-600 border-rose-200',
        initials: 'GG',
    },
    landing: {
        tag: 'Ladipage / Website',
        badgeClass: 'bg-purple-50 text-purple-600 border-purple-200',
        initials: 'LP',
    },
    shopee: {
        tag: 'Sàn Shopee',
        badgeClass: 'bg-amber-50 text-amber-700 border-amber-200',
        initials: 'SP',
    },
    lazada: {
        tag: 'Sàn Lazada',
        badgeClass: 'bg-indigo-50 text-indigo-600 border-indigo-200',
        initials: 'LZ',
    },
};

export function PlatformCard({ platform }) {
    const t = useT();
    const platformKey = platform.platform;
    const meta = PLATFORM_META[platformKey] ?? {
        tag: 'Tích hợp',
        badgeClass: 'bg-slate-100 text-slate-700 border-slate-200',
        initials: platformKey.substring(0, 2).toUpperCase(),
    };

    const platformLabel = tOr(t, `integrations.platforms.${platformKey}.label`, platform.label);
    const platformDescription = tOr(
        t,
        `integrations.platforms.${platformKey}.description`,
        platform.description,
    );

    const [testing, setTesting] = useState(false);
    const [syncing, setSyncing] = useState(false);
    const [copied, setCopied] = useState(false);
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
        if (ok) {
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
            toast.success(t('integrations.copy_webhook_success'));
        } else {
            toast.error(t('integrations.copy_webhook_failed'));
        }
    };

    const save = (e) => {
        e.preventDefault();
        put(`/admin/integrations/${platform.platform}`, {
            preserveScroll: true,
            onSuccess: () => toast.success(t('integrations.saved')),
            onError: (errs) => toast.error(Object.values(errs)[0] ?? t('common.request_failed')),
        });
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

    const syncNow = async () => {
        setSyncing(true);
        const actionLabel = 'Đồng bộ đơn Pancake POS';
        try {
            const res = await apiPost(`/admin/integrations/${platform.platform}/sync-now`);
            setTestResult({
                data: {
                    success: res.success,
                    message: res.message,
                    display: {
                        success: res.success,
                        message: res.message,
                        lines: [
                            { label: 'Số đơn đã đồng bộ', value: String(res.count ?? 0), highlight: true },
                            ...(res.orders?.map((o) => ({
                                label: o.order_code,
                                value: `${o.customer_name} (${o.customer_phone})`,
                            })) ?? []),
                        ],
                    },
                },
                actionLabel,
            });
            toast.success(res.message);
        } catch (e) {
            setTestResult({ error: e.message, actionLabel });
            toast.error(e.message);
        } finally {
            setSyncing(false);
        }
    };

    return (
        <Card id={platform.platform} className="scroll-mt-24 border border-slate-200 bg-white shadow-2xs">
            <CardHeader className="border-b border-slate-100 pb-3 pt-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center gap-3">
                        <div className={cn('flex size-9 shrink-0 items-center justify-center rounded-md border font-mono text-xs font-bold', meta.badgeClass)}>
                            {meta.initials}
                        </div>
                        <div>
                            <div className="flex items-center gap-2">
                                <CardTitle className="text-base font-bold text-slate-900">{platformLabel}</CardTitle>
                                <span className={cn('rounded px-1.5 py-0.5 text-[11px] font-medium border', meta.badgeClass)}>
                                    {meta.tag}
                                </span>
                            </div>
                            <p className="text-xs text-slate-500 mt-0.5 line-clamp-1">{platformDescription}</p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2.5">
                        <span className={cn('text-xs font-semibold', data.is_enabled ? 'text-emerald-700' : 'text-slate-400')}>
                            {data.is_enabled ? 'Đang bật' : 'Tạm tắt'}
                        </span>
                        <Switch
                            checked={data.is_enabled}
                            onCheckedChange={(v) => setData('is_enabled', v)}
                            aria-label={`Bật/tắt ${platformLabel}`}
                        />
                    </div>
                </div>
            </CardHeader>

            <CardContent className="space-y-4 pt-4">
                <form onSubmit={save} className="space-y-4">
                    {/* KHU VỰC WEBHOOK ENDPOINT */}
                    <div className="rounded-lg border border-slate-200 bg-slate-50/70 p-3 space-y-2">
                        <div className="flex items-center justify-between">
                            <Label className="text-xs font-semibold text-slate-700">
                                {t('integrations.webhook_url')}
                            </Label>
                            {platform.last_synced_at && (
                                <span className="text-[11px] text-slate-500">
                                    Đồng bộ gần nhất: {formatDateTime(platform.last_synced_at)}
                                </span>
                            )}
                        </div>

                        <div className="flex gap-2">
                            <Input
                                readOnly
                                value={platform.webhook_url}
                                className="h-8.5 font-mono text-xs bg-white text-slate-800 border-slate-300 shadow-none select-all"
                            />
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => copyUrl(platform.webhook_url)}
                                className="h-8.5 px-3 gap-1.5 text-xs shrink-0 bg-white border-slate-300 text-slate-700 hover:bg-slate-100"
                            >
                                {copied ? <Check className="size-3.5 text-emerald-600" /> : <Copy className="size-3.5 text-slate-500" />}
                                <span>{copied ? 'Đã copy' : 'Copy'}</span>
                            </Button>
                        </div>

                        {platform.platform === 'landing' && (
                            <p className="text-[11px] text-slate-600 pt-0.5">
                                {t('integrations.bearer_post')}{' '}
                                <code className="rounded bg-white border border-slate-200 px-1.5 py-0.5 font-mono text-slate-800">{platform.api_leads_url}</code>
                            </p>
                        )}

                        {platform.platform === 'pancake' && (
                            <div className="rounded-md border border-blue-200 bg-blue-50/80 p-3 text-xs text-slate-800 space-y-1.5 mt-2">
                                <div className="flex items-center gap-1.5 font-semibold text-blue-900">
                                    <Sparkles className="size-3.5 text-blue-600" />
                                    <span>Hướng dẫn cấu hình Webhook trên Pancake POS:</span>
                                </div>
                                <div className="space-y-1 text-slate-700 text-xs leading-relaxed pl-1">
                                    <p>1. Vào <strong>Pancake POS</strong> (<code className="rounded bg-white border border-blue-200 px-1 py-0.5 font-mono text-blue-900">pos.pages.fm</code>) &rarr; <strong>Cài đặt</strong> &rarr; <strong>Nâng cao</strong> &rarr; <strong>Kết nối bên thứ 3 (Webhook/API)</strong>.</p>
                                    <p>2. Dán <strong>Webhook URL</strong> ở trên và tích chọn: <em>Đơn hàng (Tạo mới, Cập nhật)</em>.</p>
                                    {platform.pancake_extension_url && (
                                        <p className="pt-0.5 border-t border-blue-200/60 text-slate-600">
                                            3. Nếu dùng Chrome Extension, cấu hình endpoint: <code className="rounded bg-white border border-blue-200 px-1 py-0.5 font-mono text-slate-800">{platform.pancake_extension_url}</code>
                                        </p>
                                    )}
                                </div>
                            </div>
                        )}
                    </div>

                    {/* XÁC THỰC WEBHOOK SECRET / VERIFY TOKEN NẾU CÓ */}
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

                    {platform.platform !== 'facebook' && platform.webhook_secret_set !== undefined && (
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

                    {/* KHU VỰC CREDENTIALS */}
                    {platform.fields.length > 0 && (
                        <div className="space-y-3 pt-1">
                            <div className="flex items-center justify-between border-b border-slate-200 pb-1.5">
                                <p className="text-xs font-bold uppercase tracking-wider text-slate-600">
                                    {t('integrations.api_credentials')}
                                </p>
                                <span className="text-[11px] font-medium text-slate-500">
                                    {platform.fields.filter(f => f.is_set).length}/{platform.fields.length} đã cấu hình
                                </span>
                            </div>

                            <div className={cn('grid gap-3', platform.fields.length > 1 ? 'sm:grid-cols-2' : 'grid-cols-1')}>
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
                        </div>
                    )}

                    {/* KHU VỰC THAO TÁC / KIỂM TRA / ĐỒNG BỘ */}
                    <div className="rounded-lg border border-slate-200 bg-slate-50/70 p-3 space-y-2">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <div className="flex items-center gap-1.5 text-xs font-semibold text-slate-800">
                                <FlaskConical className="size-3.5 text-blue-600" />
                                <span>{t('integrations.test_webhook_title')}</span>
                            </div>
                            <div className="flex flex-wrap items-center gap-2">
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    disabled={testing || syncing}
                                    onClick={testWebhook}
                                    className="h-8 text-xs bg-white border-slate-300 text-slate-700 hover:bg-slate-100 font-medium"
                                >
                                    {testing ? t('integrations.testing') : t('integrations.test_webhook')}
                                </Button>
                                {platform.platform === 'pancake' && (
                                    <Button
                                        type="button"
                                        size="sm"
                                        disabled={testing || syncing}
                                        onClick={syncNow}
                                        className="h-8 text-xs gap-1.5 bg-blue-600 hover:bg-blue-700 text-white font-medium shadow-xs"
                                    >
                                        <RefreshCw className={cn('size-3.5', syncing && 'animate-spin')} />
                                        <span>{syncing ? 'Đang kéo đơn...' : 'Đồng bộ đơn ngay'}</span>
                                    </Button>
                                )}
                            </div>
                        </div>
                        <p className="text-[11px] text-slate-500">{t('integrations.test_webhook_desc')}</p>
                        <ConnectionTestResult result={testResult} actionLabel={testResult?.actionLabel} />
                    </div>

                    {/* FOOTER ACTIONS */}
                    <div className="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-3">
                        <div>
                            {platform.docs_url && (
                                <Button type="button" variant="ghost" size="sm" className="h-8 px-2 text-xs text-slate-500 hover:text-slate-900" asChild>
                                    <a href={platform.docs_url} target="_blank" rel="noreferrer">
                                        <ExternalLink className="size-3.5 mr-1 text-slate-400" />
                                        {t('integrations.docs')}
                                    </a>
                                </Button>
                            )}
                        </div>

                        <div className="flex items-center gap-2">
                            {recentlySuccessful && (
                                <span className="text-xs text-emerald-600 font-semibold">{t('integrations.saved')}</span>
                            )}
                            <Button type="button" variant="outline" size="sm" className="h-8 text-xs bg-white border-slate-200 text-slate-600 hover:bg-slate-50" onClick={() => reset()}>
                                <RefreshCw className="size-3.5 mr-1 text-slate-400" />
                                {t('integrations.reset_form')}
                            </Button>
                            <Button type="submit" size="sm" className="h-8 text-xs px-4 bg-blue-600 hover:bg-blue-700 text-white font-medium shadow-xs" disabled={processing}>
                                {processing ? t('common.saving') : t('integrations.save_config')}
                            </Button>
                        </div>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}
