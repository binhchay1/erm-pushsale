import { Head, router, useForm } from '@inertiajs/react';
import { Camera, Save, Trash2, Upload } from 'lucide-react';
import { useRef, useState } from 'react';

import { OrgStructureCard } from '@/components/org/OrgStructureCard';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout';

export default function ProfileIndex({ profile, org }) {
    const fileRef = useRef(null);
    const [preview, setPreview] = useState(null);

    const { data, setData, put, processing, errors, recentlySuccessful } = useForm({
        name: profile.name ?? '',
        email: profile.email ?? '',
        phone: profile.phone ?? '',
        job_title: profile.job_title ?? '',
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

    const removeAvatar = () => {
        if (!window.confirm('Xóa ảnh đại diện?')) return;
        router.delete('/profile/avatar', { preserveScroll: true });
    };

    const submit = (e) => {
        e.preventDefault();
        put('/profile', { preserveScroll: true });
    };

    return (
        <AppLayout>
            <Head title="Hồ sơ cá nhân" />

            <div className="mx-auto max-w-3xl space-y-6 animate-in fade-in-0 duration-300">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Hồ sơ cá nhân</h1>
                    <p className="text-sm text-muted-foreground">
                        Ảnh đại diện, thông tin liên hệ và mật khẩu
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
                        <CardTitle>Thông tin</CardTitle>
                        <CardDescription>
                            {profile.role_label}
                            {profile.team_name ? ` · ${profile.team_name}` : ''}
                            {profile.org_level_label ? ` · ${profile.org_level_label}` : ''}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-4">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2 sm:col-span-2">
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
                                    <Label htmlFor="phone">Số điện thoại</Label>
                                    <Input
                                        id="phone"
                                        value={data.phone}
                                        onChange={(e) => setData('phone', e.target.value)}
                                    />
                                    {errors.phone && <p className="text-xs text-destructive">{errors.phone}</p>}
                                </div>
                                <div className="space-y-2 sm:col-span-2">
                                    <Label htmlFor="job_title">Chức danh</Label>
                                    <Input
                                        id="job_title"
                                        value={data.job_title}
                                        onChange={(e) => setData('job_title', e.target.value)}
                                        placeholder="VD: Giám sát Marketing"
                                    />
                                    {errors.job_title && (
                                        <p className="text-xs text-destructive">{errors.job_title}</p>
                                    )}
                                </div>
                            </div>

                            <div className="rounded-lg border border-dashed p-4">
                                <p className="mb-3 text-sm font-medium">Đổi mật khẩu (tùy chọn)</p>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="password">Mật khẩu mới</Label>
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
                                        <Label htmlFor="password_confirmation">Xác nhận</Label>
                                        <Input
                                            id="password_confirmation"
                                            type="password"
                                            value={data.password_confirmation}
                                            onChange={(e) => setData('password_confirmation', e.target.value)}
                                        />
                                    </div>
                                </div>
                            </div>

                            <div className="flex items-center justify-end gap-2">
                                {recentlySuccessful && (
                                    <span className="text-xs text-muted-foreground animate-in fade-in-0">
                                        Đã lưu
                                    </span>
                                )}
                                <Button type="submit" disabled={processing}>
                                    <Save className="size-4" />
                                    {processing ? 'Đang lưu…' : 'Lưu thay đổi'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <OrgStructureCard org={org} />
            </div>
        </AppLayout>
    );
}
