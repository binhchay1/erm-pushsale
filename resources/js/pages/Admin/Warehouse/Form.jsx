import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout';

export default function WarehouseForm({ warehouse, managers }) {
    const isEdit = Boolean(warehouse?.id);
    const { data, setData, post, put, processing, errors } = useForm({
        name: warehouse?.name ?? '',
        phone: warehouse?.phone ?? '',
        address: warehouse?.address ?? '',
        manager_user_id: warehouse?.manager_user_id ?? '',
        vtp_code: warehouse?.vtp_code ?? '',
    });

    const submit = (e) => {
        e.preventDefault();
        if (isEdit) {
            put(`/admin/warehouses/${warehouse.id}`);
        } else {
            post('/admin/warehouses');
        }
    };

    return (
        <AppLayout>
            <Head title={isEdit ? 'Sửa kho' : 'Tạo kho'} />

            <div className="mx-auto max-w-2xl space-y-6">
                <div className="flex items-center gap-3">
                    <Button variant="ghost" size="icon-sm" asChild>
                        <Link href="/admin/warehouses">
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <h1 className="text-2xl font-bold tracking-tight">
                        {isEdit ? 'Sửa kho' : 'Tạo kho'}
                    </h1>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Thông tin kho</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-4">
                            <div className="space-y-2">
                                <Label>Tên kho</Label>
                                <Input
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="Ví dụ: Kho Hà Nội"
                                />
                                {errors.name && <p className="text-xs text-destructive">{errors.name}</p>}
                            </div>
                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label>Số điện thoại</Label>
                                    <Input
                                        value={data.phone}
                                        onChange={(e) => setData('phone', e.target.value)}
                                        placeholder="0988xxxxxx"
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label>Mã VTP</Label>
                                    <Input
                                        value={data.vtp_code}
                                        onChange={(e) => setData('vtp_code', e.target.value)}
                                        placeholder="VTP-HN-01"
                                    />
                                </div>
                            </div>
                            <div className="space-y-2">
                                <Label>Địa chỉ</Label>
                                <Input
                                    value={data.address}
                                    onChange={(e) => setData('address', e.target.value)}
                                    placeholder="Số nhà, quận/huyện, tỉnh/thành"
                                />
                            </div>
                            <div className="space-y-2">
                                <Label>Quản kho</Label>
                                <select
                                    className="h-10 w-full rounded-md border bg-background px-3 text-sm"
                                    value={data.manager_user_id}
                                    onChange={(e) => setData('manager_user_id', e.target.value)}
                                >
                                    <option value="">-- Chọn quản kho --</option>
                                    {managers.map((m) => (
                                        <option key={m.id} value={m.id}>
                                            {m.name}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="flex justify-end">
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Đang lưu...' : 'Lưu kho'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
