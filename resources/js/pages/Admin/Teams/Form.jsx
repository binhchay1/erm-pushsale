import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout';

export default function TeamForm({ team, types, parents, leaders }) {
    const { url } = usePage();
    const isEdit = Boolean(team?.id);
    const parentFromQuery = new URLSearchParams(url.split('?')[1] ?? '').get('parent_id');

    const { data, setData, post, put, processing, errors } = useForm({
        name: team?.name ?? '',
        type: team?.type ?? 'marketing',
        parent_id: team?.parent_id ?? parentFromQuery ?? '',
        leader_user_id: team?.leader_user_id ?? '',
    });

    const submit = (e) => {
        e.preventDefault();
        if (isEdit) {
            put(`/admin/teams/${team.id}`);
        } else {
            post('/admin/teams');
        }
    };

    return (
        <AppLayout>
            <Head title={isEdit ? 'Sửa phòng ban' : 'Thêm phòng ban'} />

            <div className="mx-auto max-w-2xl space-y-6">
                <div className="flex items-center gap-3">
                    <Button variant="ghost" size="icon-sm" asChild>
                        <Link href="/admin/teams">
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <h1 className="text-2xl font-bold tracking-tight">
                        {isEdit ? 'Sửa phòng ban' : 'Thêm phòng ban'}
                    </h1>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Thông tin phòng ban</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="name">Tên phòng ban</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="VD: Ban Marketing — Giám sát A"
                                />
                                {errors.name && <p className="text-xs text-destructive">{errors.name}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="type">Loại bộ phận</Label>
                                <select
                                    id="type"
                                    className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                                    value={data.type}
                                    onChange={(e) => setData('type', e.target.value)}
                                >
                                    {types.map((t) => (
                                        <option key={t.value} value={t.value}>
                                            {t.label}
                                        </option>
                                    ))}
                                </select>
                                {errors.type && <p className="text-xs text-destructive">{errors.type}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="parent_id">Thuộc phòng ban (cha)</Label>
                                <select
                                    id="parent_id"
                                    className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                                    value={data.parent_id}
                                    onChange={(e) => setData('parent_id', e.target.value || '')}
                                >
                                    <option value="">— Gốc (không có cha) —</option>
                                    {parents.map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {p.name}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="leader_user_id">Trưởng ban / nhóm</Label>
                                <select
                                    id="leader_user_id"
                                    className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                                    value={data.leader_user_id}
                                    onChange={(e) => setData('leader_user_id', e.target.value || '')}
                                >
                                    <option value="">— Chưa gán —</option>
                                    {leaders.map((l) => (
                                        <option key={l.id} value={l.id}>
                                            {l.name}
                                        </option>
                                    ))}
                                </select>
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
