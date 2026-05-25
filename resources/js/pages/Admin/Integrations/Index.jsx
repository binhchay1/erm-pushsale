import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowRight,
    CheckCircle2,
    Copy,
    ExternalLink,
    FlaskConical,
    Plug,
    RefreshCw,
    Unplug,
} from 'lucide-react';
import { toast } from 'sonner';

import AppLayout from '@/layouts/AppLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';

function StatusBadge({ ok, label }) {
    return (
        <span
            className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ${
                ok
                    ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300'
                    : 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300'
            }`}
        >
            {ok ? <CheckCircle2 className="size-3" /> : <Unplug className="size-3" />}
            {label}
        </span>
    );
}

function PlatformCard({ platform }) {
    const initialCredentials = platform.fields.reduce((acc, f) => {
        acc[f.key] = '';
        return acc;
    }, {});

    const { data, setData, put, processing, recentlySuccessful, reset } = useForm({
        is_enabled: platform.is_enabled,
        verify_token: platform.verify_token ?? '',
        webhook_secret: '',
        credentials: initialCredentials,
    });

    const copyUrl = async (url) => {
        try {
            await navigator.clipboard.writeText(url);
            toast.success('Đã copy URL webhook');
        } catch {
            toast.error('Không copy được — copy thủ công');
        }
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
                            ok={platform.is_configured}
                            label={platform.is_configured ? 'Đã cấu hình' : 'Chưa đủ key'}
                        />
                        <StatusBadge
                            ok={platform.is_enabled}
                            label={platform.is_enabled ? 'Đang bật' : 'Đang tắt'}
                        />
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
                            Lần nhận gần nhất:{' '}
                            {new Date(platform.last_synced_at).toLocaleString('vi-VN')}
                        </p>
                    )}

                    <div className="flex flex-wrap items-center justify-end gap-2 pt-2">
                        {recentlySuccessful && (
                            <span className="text-sm text-emerald-600">Đã lưu</span>
                        )}
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

export default function IntegrationsIndex({ hub, categories, platforms, leadRouting, stats }) {
    const grouped = Object.keys(categories).map((key) => ({
        key,
        label: categories[key],
        items: platforms.filter((p) => p.category === key),
    }));

    return (
        <AppLayout>
            <Head title="Tích hợp nền tảng" />

            <div className="space-y-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">{hub.title}</h1>
                        <p className="mt-1 max-w-3xl text-sm text-muted-foreground">{hub.summary}</p>
                    </div>
                    <Button variant="outline" asChild>
                        <Link href="/admin/leads">
                            Nhật ký lead
                            <ArrowRight className="size-4" />
                        </Link>
                    </Button>
                </div>

                <div className="grid gap-4 sm:grid-cols-3">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Lead hôm nay</CardDescription>
                            <CardTitle className="text-3xl">{stats.leads_today}</CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Đang chờ xử lý</CardDescription>
                            <CardTitle className="text-3xl">{stats.leads_pending}</CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Nền tảng đang bật</CardDescription>
                            <CardTitle className="text-3xl">{stats.platforms_enabled}</CardTitle>
                        </CardHeader>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-lg">
                            <Plug className="size-5" />
                            Mục đích & giải pháp
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-6 lg:grid-cols-2">
                        <div>
                            <p className="mb-2 text-sm font-medium text-destructive">Nỗi đau thường gặp</p>
                            <ul className="list-inside list-disc space-y-1 text-sm text-muted-foreground">
                                {hub.problems?.map((p) => (
                                    <li key={p}>{p}</li>
                                ))}
                            </ul>
                        </div>
                        <div>
                            <p className="mb-2 text-sm font-medium text-primary">Giải pháp phễu SaleOps</p>
                            <ul className="space-y-1 text-sm text-muted-foreground">
                                {Object.entries(hub.solutions ?? {}).map(([k, v]) => (
                                    <li key={k}>
                                        <span className="font-medium text-foreground">{k}:</span> {v}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Luồng vận hành lõi</CardTitle>
                        <CardDescription>
                            Chia số: <strong>{leadRouting.strategy}</strong> · Chống trùng{' '}
                            {leadRouting.duplicate_window_days} ngày
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ol className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            {hub.workflow?.map((step, i) => (
                                <li
                                    key={step}
                                    className="rounded-lg border bg-muted/20 px-3 py-2 text-sm"
                                >
                                    <span className="mr-2 font-bold text-primary">{i + 1}.</span>
                                    {step}
                                </li>
                            ))}
                        </ol>
                    </CardContent>
                </Card>

                {grouped.map(
                    (group) =>
                        group.items.length > 0 && (
                            <section key={group.key} className="space-y-4">
                                <h2 className="text-lg font-semibold">{group.label}</h2>
                                <div className="grid gap-6 xl:grid-cols-2">
                                    {group.items.map((platform) => (
                                        <PlatformCard key={platform.platform} platform={platform} />
                                    ))}
                                </div>
                            </section>
                        )
                )}
            </div>
        </AppLayout>
    );
}
