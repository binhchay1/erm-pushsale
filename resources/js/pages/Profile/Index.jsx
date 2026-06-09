import { Head, router, useForm } from '@inertiajs/react';
import { Camera, KeyRound, Save, Trash2, Upload } from 'lucide-react';
import { useRef, useState } from 'react';

import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useConfirm } from '@/hooks/use-confirm';
import AppLayout from '@/layouts/AppLayout';

function ReadOnlyField({ label, value }) {
    return (
        <div className="space-y-1">
            <p className="text-xs font-medium text-muted-foreground">{label}</p>
            <p className="text-sm font-medium">{value || '—'}</p>
        </div>
    );
}

export default function ProfileIndex({ profile }) {
    const fileRef = useRef(null);
    const [preview, setPreview] = useState(null);
    const { ask, ConfirmDialogPortal } = useConfirm();

    const { data, setData, put, processing, errors, recentlySuccessful, reset } = useForm({
        password: '',
        password_confirmation: '',
    });

    const avatarSrc = preview ?? profile.avatar_url;

    const onPickAvatar = (e) => {
        const file = e.target.files?.[0];
        if (!file) return;
        setPreview(URL.createObjectURL(file));
        router.post(
            '/profile/avatar',
            { avatar: file },
            {
                forceFormData: true,
                preserveScroll: true,
                onFinish: () => {
                    if (preview) URL.revokeObjectURL(preview);
                    setPreview(null);
                },
            }
        );
    };

    const removeAvatar = async () => {
        const ok = await ask({
            title: 'Xóa ảnh đại diện',
            description: 'Xóa ảnh đại diện hiện tại?',
            confirmLabel: 'Xóa',
            variant: 'destructive',
        });
        if (!ok) return;
        router.delete('/profile/avatar', { preserveScroll: true });
    };

    const submitPassword = (e) => {
        e.preventDefault();
        put('/profile', {
            preserveScroll: true,
            onSuccess: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <AppLayout>
            <Head title="Hồ sơ cá nhân" />

            <div className="mx-auto max-w-3xl space-y-6 animate-in fade-in-0 duration-300">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Hồ sơ cá nhân</h1>
                    <p className="text-sm text-muted-foreground">
                        Ảnh đại diện và đổi mật khẩu — thông tin cá nhân do quản trị viên quản lý
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Ảnh đại diện</CardTitle>
                        <CardDescription>JPG, PNG — tối đa 2MB</CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-wrap items-center gap-4">
                        <Avatar className="size-20 border-2 border-border/80 shadow-sm transition-transform duration-200 hover:scale-[1.02]">
                            {avatarSrc ? <AvatarImage src={avatarSrc} alt={profile.name} /> : null}
                            <AvatarFallback className="text-lg">{profile.initials}</AvatarFallback>
                        </Avatar>
                        <div className="flex flex-wrap gap-2">
                            <input
                                ref={fileRef}
                                type="file"
                                accept="image/*"
                                className="hidden"
                                onChange={onPickAvatar}
                            />
                            <Button type="button" variant="outline" size="sm" onClick={() => fileRef.current?.click()}>
                                <Upload className="size-4" />
                                Tải ảnh lên
                            </Button>
                            {profile.avatar_url && (
                                <Button type="button" variant="ghost" size="sm" onClick={removeAvatar}>
                                    <Trash2 className="size-4" />
                                    Xóa ảnh
                                </Button>
                            )}
                        </div>
                        <p className="flex w-full items-center gap-1 text-xs text-muted-foreground">
                            <Camera className="size-3" />
                            Ảnh hiển thị trên thanh menu và hồ sơ
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Thông tin tài khoản</CardTitle>
                        <CardDescription>
                            {profile.role_label}
                            {profile.team_name ? ` · ${profile.team_name}` : ''}
                            {profile.org_level_label ? ` · ${profile.org_level_label}` : ''}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 rounded-lg border bg-muted/20 p-4 sm:grid-cols-2">
                            <ReadOnlyField label="Họ tên" value={profile.name} />
                            <ReadOnlyField label="Email" value={profile.email} />
                            <ReadOnlyField label="Số điện thoại" value={profile.phone} />
                            <ReadOnlyField label="Chức danh" value={profile.job_title} />
                            {profile.manager_name && (
                                <ReadOnlyField label="Người quản lý" value={profile.manager_name} />
                            )}
                        </div>
                        <p className="mt-3 text-xs text-muted-foreground">
                            Cần thay đổi họ tên, email hoặc phòng ban? Liên hệ quản trị viên hệ thống.
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <KeyRound className="size-4" />
                            Đổi mật khẩu
                        </CardTitle>
                        <CardDescription>Nhập mật khẩu mới và xác nhận lại</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submitPassword} className="space-y-4">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="password">Mật khẩu mới</Label>
                                    <Input
                                        id="password"
                                        type="password"
                                        autoComplete="new-password"
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
                                        autoComplete="new-password"
                                        value={data.password_confirmation}
                                        onChange={(e) => setData('password_confirmation', e.target.value)}
                                    />
                                </div>
                            </div>

                            <div className="flex items-center justify-end gap-2">
                                {recentlySuccessful && (
                                    <span className="text-xs text-emerald-600 animate-in fade-in-0">
                                        Đã đổi mật khẩu
                                    </span>
                                )}
                                <Button type="submit" disabled={processing}>
                                    <Save className="size-4" />
                                    {processing ? 'Đang lưu…' : 'Lưu mật khẩu'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>

            <ConfirmDialogPortal />
        </AppLayout>
    );
}
