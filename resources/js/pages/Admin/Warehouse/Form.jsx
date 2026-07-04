import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { FieldError, RequiredMark } from '@/components/ui/field-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { validate } from '@/lib/validate';
import AppLayout from '@/layouts/AppLayout';
import { useT } from '@/providers/I18nProvider';

export default function WarehouseForm({ warehouse, managers }) {
    const t = useT();
    const isEdit = Boolean(warehouse?.id);
    const { data, setData, post, put, processing, errors, setError, clearErrors } = useForm({
        name: warehouse?.name ?? '',
        phone: warehouse?.phone ?? '',
        address: warehouse?.address ?? '',
        manager_user_id: warehouse?.manager_user_id ?? '',
        vtp_code: warehouse?.vtp_code ?? '',
    });

    const submit = (e) => {
        e.preventDefault();

        const clientErrors = validate(data, {
            name: [{ required: true, label: t('pages.warehouse.name') }],
        });

        if (Object.keys(clientErrors).length > 0) {
            clearErrors();
            Object.entries(clientErrors).forEach(([field, message]) => setError(field, message));
            toast.error(t('common.validation.fix_errors'));
            return;
        }

        if (isEdit) {
            put(`/admin/warehouses/${warehouse.id}`);
        } else {
            post('/admin/warehouses');
        }
    };

    return (
        <AppLayout>
            <Head title={isEdit ? t('pages.warehouse.edit') : t('pages.warehouse.form_create')} />

            <div className="mx-auto max-w-2xl space-y-6">
                <div className="flex items-center gap-3">
                    <Button variant="ghost" size="icon-sm" asChild>
                        <Link href="/admin/warehouses">
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <h1 className="text-2xl font-bold tracking-tight">
                        {isEdit ? t('pages.warehouse.edit') : t('pages.warehouse.form_create')}
                    </h1>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('pages.warehouse.form_info')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-4">
                            <div className="space-y-2">
                                <Label>
                                    {t('pages.warehouse.name')}
                                    <RequiredMark />
                                </Label>
                                <Input
                                    value={data.name}
                                    aria-invalid={!!errors.name}
                                    onChange={(e) => {
                                        setData('name', e.target.value);
                                        clearErrors('name');
                                    }}
                                    placeholder={t('pages.warehouse.name_placeholder')}
                                />
                                <FieldError message={errors.name} />
                            </div>
                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label>{t('pages.warehouse.phone')}</Label>
                                    <Input
                                        value={data.phone}
                                        onChange={(e) => setData('phone', e.target.value)}
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label>{t('pages.warehouse.vtp_code')}</Label>
                                    <Input
                                        value={data.vtp_code}
                                        onChange={(e) => setData('vtp_code', e.target.value)}
                                    />
                                </div>
                            </div>
                            <div className="space-y-2">
                                <Label>{t('pages.warehouse.address')}</Label>
                                <Input
                                    value={data.address}
                                    onChange={(e) => setData('address', e.target.value)}
                                    placeholder={t('pages.warehouse.address_placeholder')}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label>{t('pages.warehouse.manager')}</Label>
                                <select
                                    className="input-soft h-10 w-full px-3"
                                    value={data.manager_user_id}
                                    onChange={(e) => setData('manager_user_id', e.target.value)}
                                >
                                    <option value="">{t('pages.warehouse.select_manager')}</option>
                                    {managers.map((m) => (
                                        <option key={m.id} value={m.id}>
                                            {m.name}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="flex justify-end">
                                <Button type="submit" disabled={processing}>
                                    {processing ? t('common.saving') : t('pages.warehouse.save')}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
