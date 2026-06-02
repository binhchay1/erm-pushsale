import { router, useForm } from '@inertiajs/react';
import { CheckCircle2, Copy, ExternalLink, FlaskConical, RefreshCw, Unplug } from 'lucide-react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { StatusBadge } from '@/components/ui/status-badge';
import { Switch } from '@/components/ui/switch';
import { copyToClipboard } from '@/lib/clipboard';
import { formatDateTime } from '@/lib/format';

export function PlatformCard({ platform }) {
    const initialCredentials = platform.fields.reduce((acc, field) => {
        acc[field.key] = '';
        return acc;
    }, {});

    const { data, setData, put, processing, recentlySuccessful, reset } = useForm({
        is_enabled: platform.is_enabled,
        verify_token: platform.verify_token ?? '',
        webhook_secret: '',
        credentials: initialCredentials,
    });

    const copyUrl = async (url) => {
        const ok = await copyToClipboard(url);
        ok ? toast.success('Đã copy URL webhook') : toast.error('Không copy được — copy thủ công');
    };

    const save = (e) => {
        e.preventDefault();
        put(`/admin/integrations/${platform.platform}`, { preserveScroll: true });
    };

    const testWebhook = () => {
        router.post(`/admin/integrations/${platform.platform}/test`, {}, { preserveScroll: true });
    };

    return (
        <Card id={platform.platform} className="scroll-mt-24">
            <CardHeader className="pb-3">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="space-y-1">
                        <CardTitle className="text-lg">{platform.label}</CardTitle>
                        <CardDescription>{platform.description}</CardDescription>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <StatusBadge
                            tone={platform.is_configured ? 'success' : 'warning'}
                            icon={platform.is_configured ? CheckCircle2 : Unplug}
                        >
                            {platform.is_configured ? 'Đã cấu hình' : 'Chưa đủ key'}
                        </StatusBadge>
                        <StatusBadge
                            tone={platform.is_enabled ? 'success' : 'warning'}
                            icon={platform.is_enabled ? CheckCircle2 : Unplug}
                        >
                            {platform.is_enabled ? 'Đang bật' : 'Đang tắt'}
                        </StatusBadge>
                    </div>
                </div>
            </CardHeader>
            <CardContent>
                <form onSubmit={save} className="space-y-4">
                    <div className="flex items-center justify-between rounded-lg border bg-muted/30 px-4 py-3">
                        <div>
                            <p className="text-sm font-medium">Bật nhận webhook</p>
                            <p className="text-xs text-muted-foreground">
                                Tắt = từ chối POST (trừ môi trường local)
                            </p>
                        </div>
                        <Switch
                            checked={data.is_enabled}
                            onCheckedChange={(v) => setData('is_enabled', v)}
                        />
                    </div>

                    <div className="space-y-2">
                        <Label>Webhook URL</Label>
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
                                Hoặc POST Bearer token tới:{' '}
                                <code className="rounded bg-muted px-1">{platform.api_leads_url}</code>
                            </p>
                        )}
                    </div>

                    {platform.platform === 'facebook' && (
                        <div className="grid gap-3 sm:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor={`${platform.platform}-verify`}>Verify Token (Meta)</Label>
                                <Input
                                    id={`${platform.platform}-verify`}
                                    value={data.verify_token}
                                    onChange={(e) => setData('verify_token', e.target.value)}
                                    placeholder={
                                        platform.verify_token_set
                                            ? '•••••••• (đã có — nhập mới để đổi)'
                                            : 'Nhập verify token'
                                    }
                                />
                            </div>
                            <div className="space-y-2">
                                <Label>Webhook Secret (tùy chọn)</Label>
                                <Input
                                    type="password"
                                    value={data.webhook_secret}
                                    onChange={(e) => setData('webhook_secret', e.target.value)}
                                    placeholder={platform.webhook_secret_set ? '••••••••' : 'Secret ký HMAC'}
                                />
                            </div>
                        </div>
                    )}

                    {platform.platform !== 'facebook' && (
                        <div className="space-y-2">
                            <Label>Webhook Secret / API Key</Label>
                            <Input
                                type="password"
                                value={data.webhook_secret}
                                onChange={(e) => setData('webhook_secret', e.target.value)}
                                placeholder={
                                    platform.webhook_secret_set
                                        ? '•••••••• (đã có)'
                                        : 'X-SaleOps-Signature hoặc X-Api-Key'
                                }
                            />
                        </div>
                    )}

                    <div className="space-y-3">
                        <p className="text-sm font-medium">Credentials</p>
                        {platform.fields.map((field) => (
                            <div key={field.key} className="space-y-1.5">
                                <div className="flex items-center justify-between gap-2">
                                    <Label htmlFor={`${platform.platform}-${field.key}`}>{field.label}</Label>
                                    {field.is_set && (
                                        <span className="text-xs text-emerald-600">
                                            ✓ {field.source === 'env' ? '.env' : 'DB'}
                                            {field.masked ? ` ${field.masked}` : ''}
                                        </span>
                                    )}
                                </div>
                                <Input
                                    id={`${platform.platform}-${field.key}`}
                                    type={field.is_secret ? 'password' : 'text'}
                                    value={data.credentials[field.key]}
                                    onChange={(e) =>
                                        setData('credentials', {
                                            ...data.credentials,
                                            [field.key]: e.target.value,
                                        })
                                    }
                                    placeholder={
                                        field.is_set
                                            ? field.is_secret
                                                ? '•••••••• (để trống nếu giữ nguyên)'
                                                : field.value ?? '••••••••'
                                            : `Nhập ${field.label}${field.env ? ` hoặc ${field.env}` : ''}`
                                    }
                                />
                            </div>
                        ))}
                    </div>

                    {platform.last_synced_at && (
                        <p className="text-xs text-muted-foreground">
                            Lần nhận gần nhất: {formatDateTime(platform.last_synced_at)}
                        </p>
                    )}

                    <div className="flex flex-wrap items-center justify-end gap-2 pt-2">
                        {recentlySuccessful && <span className="text-sm text-emerald-600">Đã lưu</span>}
                        {platform.docs_url && (
                            <Button type="button" variant="ghost" size="sm" asChild>
                                <a href={platform.docs_url} target="_blank" rel="noreferrer">
                                    <ExternalLink className="size-4" />
                                    Tài liệu
                                </a>
                            </Button>
                        )}
                        <Button type="button" variant="outline" size="sm" onClick={testWebhook}>
                            <FlaskConical className="size-4" />
                            Test webhook
                        </Button>
                        <Button type="button" variant="ghost" size="sm" onClick={() => reset()}>
                            <RefreshCw className="size-4" />
                            Reset form
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Đang lưu...' : 'Lưu cấu hình'}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}
