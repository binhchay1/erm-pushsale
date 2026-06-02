import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import { CheckCircle2, Copy, ExternalLink, Save, Unplug } from 'lucide-react';

import { CarrierApiTestPanel } from '@/components/shipping/CarrierApiTestPanel';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { StatusBadge } from '@/components/ui/status-badge';
import { Switch } from '@/components/ui/switch';
import { copyToClipboard } from '@/lib/clipboard';

export function ShippingPartnerCard({ provider }) {
    const initialCredentials = provider.fields.reduce((acc, field) => {
        acc[field.key] = '';
        return acc;
    }, {});

    const { data, setData, put, processing, recentlySuccessful } = useForm({
        is_enabled: provider.is_enabled,
        webhook_secret: '',
        credentials: initialCredentials,
    });

    // Sync is_enabled with fresh server-side props after each Inertia visit
    useEffect(() => {
        setData('is_enabled', provider.is_enabled);
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [provider.is_enabled]);

    const submit = (e) => {
        e.preventDefault();
        put(`/admin/shipping-partners/${provider.provider}`, { preserveScroll: true });
    };

    return (
        <Card>
            <CardHeader>
                <div className="flex items-start justify-between gap-3">
                    <div>
                        <CardTitle>{provider.label}</CardTitle>
                        <CardDescription>{provider.description}</CardDescription>
                    </div>
                    <StatusBadge
                        tone={provider.is_configured ? 'success' : 'warning'}
                        icon={provider.is_configured ? CheckCircle2 : Unplug}
                    >
                        {provider.is_configured ? 'Đã cấu hình' : 'Thiếu thông tin'}
                    </StatusBadge>
                </div>
            </CardHeader>
            <CardContent>
                <form onSubmit={submit} className="space-y-4">
                    <div className="flex items-center justify-between rounded-lg border bg-muted/30 px-4 py-3">
                        <div>
                            <p className="text-sm font-medium">Kích hoạt đối tác</p>
                            <p className="text-xs text-muted-foreground">
                                Bật để sẵn sàng gọi API tạo vận đơn
                            </p>
                        </div>
                        <Switch
                            checked={data.is_enabled}
                            onCheckedChange={(v) => setData('is_enabled', v)}
                        />
                    </div>

                    <div className="space-y-2">
                        <Label>Webhook Secret (nhận trạng thái giao hàng)</Label>
                        <Input
                            type="password"
                            value={data.webhook_secret}
                            onChange={(e) => setData('webhook_secret', e.target.value)}
                            placeholder={
                                provider.webhook_secret_set
                                    ? '•••••••• (đã có, nhập mới để đổi)'
                                    : 'Nhập secret webhook từ đối tác'
                            }
                        />
                    </div>

                    {provider.api_base_url && (
                        <div className="rounded-lg border bg-muted/20 px-4 py-3 text-xs">
                            <p className="font-mono text-muted-foreground">API: {provider.api_base_url}</p>
                            {provider.services?.length > 0 && (
                                <p className="mt-1 text-muted-foreground">
                                    Dịch vụ:{' '}
                                    {provider.services.map((s) => `${s.label} (${s.code})`).join(', ')}
                                </p>
                            )}
                        </div>
                    )}

                    <div className="space-y-2">
                        <Label>Webhook URL nhận callback</Label>
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
                            <div key={field.key} className="space-y-2">
                                <div className="flex items-center justify-between gap-2">
                                    <Label htmlFor={`${provider.provider}-${field.key}`}>
                                        {field.label}
                                    </Label>
                                    {field.is_set && (
                                        <span className="text-xs text-emerald-600">
                                            ✓ {field.source === 'env' ? '.env' : 'DB'}
                                            {field.masked ? ` ${field.masked}` : ''}
                                        </span>
                                    )}
                                </div>
                                <Input
                                    id={`${provider.provider}-${field.key}`}
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
                                                : field.value ?? '•••••••• (để trống nếu giữ nguyên)'
                                            : `Nhập ${field.label.toLowerCase()}`
                                    }
                                />
                            </div>
                        ))}
                    </div>

                    {provider.provider && (
                        <CarrierApiTestPanel provider={provider.provider} label={provider.label} />
                    )}

                    <div className="flex items-center justify-end gap-2">
                        {provider.docs_url && (
                            <Button type="button" variant="ghost" asChild>
                                <a href={provider.docs_url} target="_blank" rel="noreferrer">
                                    <ExternalLink className="size-4" />
                                    Docs
                                </a>
                            </Button>
                        )}
                        {recentlySuccessful && (
                            <span className="text-sm text-emerald-600">Đã lưu thành công</span>
                        )}
                        <Button type="submit" disabled={processing}>
                            <Save className="size-4" />
                            {processing ? 'Đang lưu...' : 'Lưu cấu hình'}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}
