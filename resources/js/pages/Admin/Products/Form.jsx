import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/AppLayout';

export default function ProductForm({ product, parents }) {
    const isEdit = Boolean(product?.id);
    const { data, setData, post, put, processing, errors } = useForm({
        name: product?.name ?? '',
        sku: product?.sku ?? '',
        unit_price: product?.unit_price ?? 0,
        parent_id: product?.parent_id ?? '',
        is_active: product?.is_active ?? true,
    });

    const submit = (e) => {
        e.preventDefault();
        if (isEdit) {
            put(`/admin/products/${product.id}`);
        } else {
            post('/admin/products');
        }
    };

    return (
        <AppLayout>
            <Head title={isEdit ? 'Sửa sản phẩm' : 'Thêm sản phẩm'} />

            <div className="mx-auto max-w-2xl space-y-6">
                <div className="flex items-center gap-3">
                    <Button variant="ghost" size="icon-sm" asChild>
                        <Link href="/admin/products">
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <h1 className="text-2xl font-bold tracking-tight">
                        {isEdit ? 'Sửa sản phẩm' : 'Thêm sản phẩm'}
                    </h1>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Thông tin sản phẩm</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="name">Tên sản phẩm</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                />
                                {errors.name && <p className="text-xs text-destructive">{errors.name}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="sku">SKU</Label>
                                <Input
                                    id="sku"
                                    value={data.sku}
                                    onChange={(e) => setData('sku', e.target.value)}
                                />
                                {errors.sku && <p className="text-xs text-destructive">{errors.sku}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="unit_price">Đơn giá (VNĐ)</Label>
                                <Input
                                    id="unit_price"
                                    type="number"
                                    min={0}
                                    value={data.unit_price}
                                    onChange={(e) => setData('unit_price', Number(e.target.value))}
                                />
                                {errors.unit_price && (
                                    <p className="text-xs text-destructive">{errors.unit_price}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="parent_id">Sản phẩm cha (nhóm)</Label>
                                <select
                                    id="parent_id"
                                    className="input-soft flex h-9 w-full px-3"
                                    value={data.parent_id}
                                    onChange={(e) => setData('parent_id', e.target.value || '')}
                                >
                                    <option value="">— Không có —</option>
                                    {parents.map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {p.name}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="flex items-center justify-between rounded-lg border px-4 py-3">
                                <Label htmlFor="is_active">Đang kinh doanh</Label>
                                <Switch
                                    id="is_active"
                                    checked={data.is_active}
                                    onCheckedChange={(v) => setData('is_active', v)}
                                />
                            </div>

                            <div className="flex justify-end">
                                <Button type="submit" disabled={processing}>
                                    <Save className="size-4" />
                                    {processing ? 'Đang lưu…' : 'Lưu'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
