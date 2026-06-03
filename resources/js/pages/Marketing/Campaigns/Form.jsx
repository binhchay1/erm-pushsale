import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Copy } from 'lucide-react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { copyToClipboard } from '@/lib/clipboard';
import AppLayout from '@/layouts/AppLayout';

function slugPreview(name) {
    return name
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

export default function CampaignForm({ campaign, products, marketers, fieldMapping }) {
    const isEdit = Boolean(campaign?.id);
    const { data, setData, post, put, processing, errors } = useForm({
        name: campaign?.name ?? '',
        product_id: campaign?.product_id ?? '',
        marketer_user_id: campaign?.marketer_user_id ?? '',
        ad_channel: campaign?.ad_channel ?? 'landing',
        budget: campaign?.budget ?? 0,
        is_active: campaign?.is_active ?? true,
    });

    const utmPreview = slugPreview(data.name) || '…';

    const submit = (e) => {
        e.preventDefault();
        if (isEdit) {
            put(`/marketing/campaigns/${campaign.id}`);
        } else {
            post('/marketing/campaigns');
        }
    };

    const copyUrl = async () => {
        if (!campaign?.webhook_url) return;
        const ok = await copyToClipboard(campaign.webhook_url);
        ok ? toast.success('Đã copy URL API') : toast.error('Không copy được');
    };

    return (
        <AppLayout>
            <Head title={isEdit ? 'Sửa kết nối Landing' : 'Thêm kết nối Landing'} />

            <div className="mx-auto max-w-2xl space-y-6">
                <div className="flex items-center gap-3">
                    <Button variant="ghost" size="icon-sm" asChild>
                        <Link href="/marketing/campaigns">
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <h1 className="text-2xl font-bold tracking-tight">
                        {isEdit ? 'Sửa kết nối Landing' : 'Thêm kết nối Landing'}
                    </h1>
                </div>

                {isEdit && campaign?.webhook_url && (
                    <Card className="border-primary/30 bg-primary/5">
                        <CardHeader>
                            <CardTitle className="text-base">URL API cho Ladipage</CardTitle>
                            <CardDescription>
                                Dán vào Form → Lưu Data → API URL. Content-Type: application/json
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex gap-2">
                            <Input readOnly value={campaign.webhook_url} className="font-mono text-xs" />
                            <Button type="button" variant="outline" onClick={copyUrl}>
                                <Copy className="size-4" />
                                Copy
                            </Button>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Thông tin nguồn</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-4">
                            <div className="space-y-2">
                                <Label>Tên landing / chiến dịch</Label>
                                <Input
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="VD: Serum Vitamin C - Ladipage T6"
                                />
                                {errors.name && <p className="text-xs text-destructive">{errors.name}</p>}
                                <p className="text-xs text-muted-foreground">
                                    Tên không trùng với chiến dịch bạn đã tạo trước đó.
                                </p>
                            </div>

                            <div className="rounded-lg border bg-muted/30 px-3 py-2 text-xs">
                                <span className="text-muted-foreground">utm_campaign (tự sinh): </span>
                                <span className="font-mono font-medium">{utmPreview}</span>
                            </div>

                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label>Sản phẩm</Label>
                                    <select
                                        className="h-10 w-full rounded-md border bg-background px-3 text-sm"
                                        value={data.product_id}
                                        onChange={(e) => setData('product_id', e.target.value)}
                                    >
                                        <option value="">-- Chọn --</option>
                                        {products.map((p) => (
                                            <option key={p.id} value={p.id}>
                                                {p.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div className="space-y-2">
                                    <Label>Marketer phụ trách</Label>
                                    <select
                                        className="h-10 w-full rounded-md border bg-background px-3 text-sm"
                                        value={data.marketer_user_id}
                                        onChange={(e) => setData('marketer_user_id', e.target.value)}
                                    >
                                        {marketers.map((m) => (
                                            <option key={m.id} value={m.id}>
                                                {m.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label>Ngân sách (đ)</Label>
                                <Input
                                    type="number"
                                    min={0}
                                    value={data.budget}
                                    onChange={(e) => setData('budget', e.target.value)}
                                />
                            </div>

                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={data.is_active}
                                    onChange={(e) => setData('is_active', e.target.checked)}
                                    className="size-4 rounded border"
                                />
                                Đang nhận lead
                            </label>

                            <div className="flex justify-end">
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Đang lưu...' : isEdit ? 'Lưu' : 'Tạo & lấy URL API'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Map trường Ladipage</CardTitle>
                        <CardDescription>Bắt buộc khớp tên biến khi cấu hình API trên Ladipage</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <table className="w-full text-xs">
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    <th className="pb-2">Ladipage</th>
                                    <th className="pb-2">Hệ thống</th>
                                </tr>
                            </thead>
                            <tbody>
                                {fieldMapping?.map((row) => (
                                    <tr key={row.ladipage} className="border-b border-border/50">
                                        <td className="py-2 font-mono">{row.ladipage}</td>
                                        <td className="py-2">{row.system}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
