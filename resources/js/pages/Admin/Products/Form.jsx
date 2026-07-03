import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { CurrencyInput } from '@/components/ui/currency-input';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/AppLayout';
import { useT } from '@/providers/I18nProvider';

export default function ProductForm({ product, parents }) {
    const t = useT();
    const isEdit = Boolean(product?.id);
    const { data, setData, post, put, processing, errors } = useForm({
        name: product?.name ?? '',
        type: product?.type ?? 'product',
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
            <Head title={isEdit ? t('pages.products.edit') : t('pages.products.form_create')} />

            <div className="mx-auto max-w-2xl space-y-6">
                <div className="flex items-center gap-3">
                    <Button variant="ghost" size="icon-sm" asChild>
                        <Link href="/admin/products">
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <h1 className="text-2xl font-bold tracking-tight">
                        {isEdit ? t('pages.products.edit') : t('pages.products.form_create')}
                    </h1>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('pages.products.form_info')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="name">{t('pages.products.name')}</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                />
                                {errors.name && <p className="text-xs text-destructive">{errors.name}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="type">{t('pages.products.type')}</Label>
                                <select
                                    id="type"
                                    className="input-soft flex h-9 w-full px-3"
                                    value={data.type}
                                    onChange={(e) => setData('type', e.target.value)}
                                >
                                    <option value="product">{t('pages.products.type_product')}</option>
                                    <option value="combo">{t('pages.products.type_combo')}</option>
                                </select>
                                {errors.type && <p className="text-xs text-destructive">{errors.type}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="sku">{t('pages.products.sku')}</Label>
                                <Input
                                    id="sku"
                                    value={data.sku}
                                    onChange={(e) => setData('sku', e.target.value)}
                                />
                                {errors.sku && <p className="text-xs text-destructive">{errors.sku}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="unit_price">{t('pages.products.unit_price')}</Label>
                                <CurrencyInput
                                    id="unit_price"
                                    value={data.unit_price}
                                    onChange={(amount) => setData('unit_price', amount)}
                                />
                                {errors.unit_price && (
                                    <p className="text-xs text-destructive">{errors.unit_price}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="parent_id">{t('pages.products.parent_product')}</Label>
                                <select
                                    id="parent_id"
                                    className="input-soft flex h-9 w-full px-3"
                                    value={data.parent_id}
                                    onChange={(e) => setData('parent_id', e.target.value || '')}
                                >
                                    <option value="">{t('pages.no_parent')}</option>
                                    {parents.map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {p.name}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="flex items-center justify-between rounded-lg border px-4 py-3">
                                <Label htmlFor="is_active">{t('pages.products.active')}</Label>
                                <Switch
                                    id="is_active"
                                    checked={data.is_active}
                                    onCheckedChange={(v) => setData('is_active', v)}
                                />
                            </div>

                            <div className="flex justify-end">
                                <Button type="submit" disabled={processing}>
                                    <Save className="size-4" />
                                    {processing ? t('common.saving') : t('pages.save')}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
