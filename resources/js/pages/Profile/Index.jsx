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
import { useT } from '@/providers/I18nProvider';

function ReadOnlyField({ label, value }) {
    return (
        <div className="space-y-1">
            <p className="text-xs font-medium text-muted-foreground">{label}</p>
            <p className="text-sm font-medium">{value || '—'}</p>
        </div>
    );
}

export default function ProfileIndex({ profile }) {
    const t = useT();
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
            title: t('profile.remove_confirm_title'),
            description: t('profile.remove_confirm_desc'),
            confirmLabel: t('common.delete'),
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
            <Head title={t('profile.title')} />

            <div className="mx-auto max-w-3xl space-y-6 animate-in fade-in-0 duration-300">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">{t('profile.title')}</h1>
                    <p className="text-sm text-muted-foreground">{t('profile.desc')}</p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('profile.avatar_title')}</CardTitle>
                        <CardDescription>{t('profile.avatar_desc')}</CardDescription>
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
                                {t('profile.upload')}
                            </Button>
                            {profile.avatar_url && (
                                <Button type="button" variant="ghost" size="sm" onClick={removeAvatar}>
                                    <Trash2 className="size-4" />
                                    {t('profile.remove')}
                                </Button>
                            )}
                        </div>
                        <p className="flex w-full items-center gap-1 text-xs text-muted-foreground">
                            <Camera className="size-3" />
                            {t('profile.avatar_hint')}
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('profile.account_title')}</CardTitle>
                        <CardDescription>
                            {profile.role_label}
                            {profile.team_name ? ` · ${profile.team_name}` : ''}
                            {profile.org_level_label ? ` · ${profile.org_level_label}` : ''}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 rounded-lg border bg-muted/20 p-4 sm:grid-cols-2">
                            <ReadOnlyField label={t('profile.name')} value={profile.name} />
                            <ReadOnlyField label={t('profile.email')} value={profile.email} />
                            <ReadOnlyField label={t('profile.phone')} value={profile.phone} />
                            <ReadOnlyField label={t('profile.job_title')} value={profile.job_title} />
                            {profile.manager_name && (
                                <ReadOnlyField label={t('profile.manager')} value={profile.manager_name} />
                            )}
                        </div>
                        <p className="mt-3 text-xs text-muted-foreground">{t('profile.admin_contact')}</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <KeyRound className="size-4" />
                            {t('profile.password_title')}
                        </CardTitle>
                        <CardDescription>{t('profile.password_desc')}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submitPassword} className="space-y-4">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="password">{t('profile.new_password')}</Label>
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
                                    <Label htmlFor="password_confirmation">{t('profile.confirm_password')}</Label>
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
                                        {t('profile.password_saved')}
                                    </span>
                                )}
                                <Button type="submit" disabled={processing}>
                                    <Save className="size-4" />
                                    {processing ? t('common.saving') : t('profile.save_password')}
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
