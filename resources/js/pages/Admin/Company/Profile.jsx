import { Head, useForm } from '@inertiajs/react';
import { Building2, Save } from 'lucide-react';

import AppLayout from '@/layouts/AppLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

function Field({ label, error, children }) {
    return (
        <div className="space-y-1.5">
            <Label>{label}</Label>
            {children}
            {error ? <p className="text-xs text-destructive">{error}</p> : null}
        </div>
    );
}

export default function CompanyProfile({ company }) {
    const form = useForm({
        name: company?.name ?? '',
        contact_email: company?.contact_email ?? '',
        contact_phone: company?.contact_phone ?? '',
        tax_code: company?.tax_code ?? '',
        address: company?.address ?? '',
        website: company?.website ?? '',
        representative_name: company?.representative_name ?? '',
        representative_title: company?.representative_title ?? '',
    });

    const submit = (event) => {
        event.preventDefault();
        form.put('/admin/company/profile', { preserveScroll: true });
    };

    return (
        <AppLayout>
            <Head title="Thông tin đơn vị" />
            <div className="pushsale-company-profile">
                <div className="pushsale-page-titlebar">
                    <div>
                        <h1><Building2 className="size-4" /> Thông tin đơn vị</h1>
                        <p>Cập nhật thông tin pháp lý và thông tin liên hệ của đơn vị đang đăng nhập.</p>
                    </div>
                    <div className="pushsale-company-meta">
                        <span>Mã đơn vị: <strong>{company?.slug}</strong></span>
                        <span>Gói: <strong>{company?.plan}</strong></span>
                        <span>Trạng thái: <strong>{company?.status}</strong></span>
                    </div>
                </div>

                <Card className="pushsale-company-card">
                    <CardHeader>
                        <CardTitle className="text-base">Thông tin chung</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                            <Field label="Tên đơn vị" error={form.errors.name}>
                                <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required />
                            </Field>
                            <Field label="Mã số thuế" error={form.errors.tax_code}>
                                <Input value={form.data.tax_code} onChange={(e) => form.setData('tax_code', e.target.value)} />
                            </Field>
                            <Field label="Số điện thoại" error={form.errors.contact_phone}>
                                <Input value={form.data.contact_phone} onChange={(e) => form.setData('contact_phone', e.target.value)} />
                            </Field>
                            <Field label="Email liên hệ" error={form.errors.contact_email}>
                                <Input type="email" value={form.data.contact_email} onChange={(e) => form.setData('contact_email', e.target.value)} />
                            </Field>
                            <Field label="Website" error={form.errors.website}>
                                <Input placeholder="https://..." value={form.data.website} onChange={(e) => form.setData('website', e.target.value)} />
                            </Field>
                            <Field label="Người đại diện" error={form.errors.representative_name}>
                                <Input value={form.data.representative_name} onChange={(e) => form.setData('representative_name', e.target.value)} />
                            </Field>
                            <Field label="Chức vụ người đại diện" error={form.errors.representative_title}>
                                <Input value={form.data.representative_title} onChange={(e) => form.setData('representative_title', e.target.value)} />
                            </Field>
                            <div className="space-y-1.5 md:col-span-2">
                                <Label>Địa chỉ</Label>
                                <textarea
                                    className="flex min-h-20 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    value={form.data.address}
                                    onChange={(e) => form.setData('address', e.target.value)}
                                />
                                {form.errors.address ? <p className="text-xs text-destructive">{form.errors.address}</p> : null}
                            </div>
                            <div className="flex items-end justify-end md:col-span-2 xl:col-span-3">
                                <Button type="submit" disabled={form.processing}>
                                    <Save className="mr-2 size-4" />
                                    {form.processing ? 'Đang lưu...' : 'Lưu thông tin'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
