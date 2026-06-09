import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/AppLayout';

export default function UserForm({ user, roles, teams, managers, orgLevels }) {
    const isEdit = Boolean(user?.id);
    const { data, setData, post, put, processing, errors } = useForm({
        name: user?.name ?? '',
        email: user?.email ?? '',
        role: user?.role ?? 'sales',
        team_id: user?.team_id ?? '',
        manager_user_id: user?.manager_user_id ?? '',
        is_team_leader: user?.is_team_leader ?? false,
        org_level: user?.org_level ?? '',
        phone: user?.phone ?? '',
        job_title: user?.job_title ?? '',
        password: '',
        password_confirmation: '',
    });

    const submit = (e) => {
        e.preventDefault();
        if (isEdit) {
            put(`/admin/users/${user.id}`);
        } else {
            post('/admin/users');
        }
    };

    return (
        <AppLayout>
            <Head title={isEdit ? 'Sửa nhân viên' : 'Thêm nhân viên'} />

            <div className="mx-auto max-w-2xl space-y-6">
                <div className="flex items-center gap-3">
                    <Button variant="ghost" size="icon-sm" asChild>
                        <Link href="/admin/users">
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <h1 className="text-2xl font-bold tracking-tight">
                        {isEdit ? 'Sửa nhân viên' : 'Thêm nhân viên'}
                    </h1>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Thông tin tài khoản</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="name">Họ tên</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                />
                                {errors.name && <p className="text-xs text-destructive">{errors.name}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="email">Email</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                />
                                {errors.email && <p className="text-xs text-destructive">{errors.email}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="role">Vai trò</Label>
                                <select
                                    id="role"
                                    className="input-soft flex h-9 w-full px-3"
                                    value={data.role}
                                    onChange={(e) => setData('role', e.target.value)}
                                >
                                    {roles.map((r) => (
                                        <option key={r.value} value={r.value}>
                                            {r.label}
                                        </option>
                                    ))}
                                </select>
                                {errors.role && <p className="text-xs text-destructive">{errors.role}</p>}
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="phone">Số điện thoại</Label>
                                    <Input
                                        id="phone"
                                        value={data.phone}
                                        onChange={(e) => setData('phone', e.target.value)}
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="job_title">Chức danh</Label>
                                    <Input
                                        id="job_title"
                                        value={data.job_title}
                                        onChange={(e) => setData('job_title', e.target.value)}
                                    />
                                </div>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="team_id">Phòng ban</Label>
                                    <select
                                        id="team_id"
                                        className="input-soft flex h-9 w-full px-3"
                                        value={data.team_id}
                                        onChange={(e) => setData('team_id', e.target.value || '')}
                                    >
                                        <option value="">— Không chọn —</option>
                                        {teams.map((t) => (
                                            <option key={t.id} value={t.id}>
                                                {t.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="manager_user_id">Quản lý trực tiếp</Label>
                                    <select
                                        id="manager_user_id"
                                        className="input-soft flex h-9 w-full px-3"
                                        value={data.manager_user_id}
                                        onChange={(e) => setData('manager_user_id', e.target.value || '')}
                                    >
                                        <option value="">— Không chọn —</option>
                                        {managers.map((m) => (
                                            <option key={m.id} value={m.id}>
                                                {m.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="org_level">Cấp trong phòng ban</Label>
                                <select
                                    id="org_level"
                                    className="input-soft flex h-9 w-full px-3"
                                    value={data.org_level}
                                    onChange={(e) => setData('org_level', e.target.value || '')}
                                >
                                    <option value="">— Không chọn —</option>
                                    {(orgLevels ?? []).map((l) => (
                                        <option key={l.value} value={l.value}>
                                            {l.label}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="flex items-center justify-between rounded-lg border px-4 py-3">
                                <Label htmlFor="is_team_leader">Trưởng nhóm (legacy)</Label>
                                <Switch
                                    id="is_team_leader"
                                    checked={data.is_team_leader}
                                    onCheckedChange={(v) => setData('is_team_leader', v)}
                                />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="password">
                                    Mật khẩu {isEdit && '(để trống nếu giữ nguyên)'}
                                </Label>
                                <Input
                                    id="password"
                                    type="password"
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                />
                                {errors.password && (
                                    <p className="text-xs text-destructive">{errors.password}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="password_confirmation">Xác nhận mật khẩu</Label>
                                <Input
                                    id="password_confirmation"
                                    type="password"
                                    value={data.password_confirmation}
                                    onChange={(e) => setData('password_confirmation', e.target.value)}
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
