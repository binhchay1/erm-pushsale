import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

import AppLayout from '@/layouts/AppLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ThemeSettings, AppearanceSettings } from '@/components/settings/ThemeSettings';
import { NotificationSettings } from '@/components/settings/NotificationSettings';
import { useTheme } from '@/providers/ThemeProvider';
import { applyAppearance } from '@/lib/themes';

export default function SettingsIndex({ preferences, settingsBackUrl }) {
    const { themes } = usePage().props;
    const { applyLocal } = useTheme();

    const { data, setData, put, processing, recentlySuccessful } = useForm({
        theme: preferences.theme,
        appearance: preferences.appearance,
        notifications: { ...preferences.notifications },
    });

    const submit = (e) => {
        e.preventDefault();
        put('/settings', {
            preserveScroll: true,
            onSuccess: () => {
                applyLocal(data.theme, data.appearance);
                applyAppearance(data.appearance);
            },
        });
    };

    return (
        <AppLayout>
            <Head title="Cài đặt" />

            <div className="mx-auto max-w-3xl space-y-6">
                <div className="flex items-center gap-3">
                    <Button variant="ghost" size="icon-sm" asChild>
                        <Link href={settingsBackUrl}>
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Cài đặt</h1>
                        <p className="text-sm text-muted-foreground">
                            Giao diện & thông báo — lưu theo tài khoản
                        </p>
                    </div>
                </div>

                <form onSubmit={submit} className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Giao diện</CardTitle>
                            <CardDescription>Chọn bộ màu theme cho dashboard</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ThemeSettings
                                value={data.theme}
                                onChange={(theme) => setData('theme', theme)}
                            />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Chế độ hiển thị</CardTitle>
                            <CardDescription>Sáng, tối hoặc theo hệ điều hành</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <AppearanceSettings
                                value={data.appearance}
                                onChange={(appearance) => setData('appearance', appearance)}
                            />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Thông báo</CardTitle>
                            <CardDescription>
                                Bật/tắt kênh nhận tin — real-time qua WebSocket + toast
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <NotificationSettings
                                value={data.notifications}
                                onChange={(notifications) => setData('notifications', notifications)}
                            />
                        </CardContent>
                    </Card>

                    <div className="flex items-center justify-end gap-3">
                        {recentlySuccessful && (
                            <span className="text-sm text-emerald-600">Đã lưu thành công</span>
                        )}
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Đang lưu...' : 'Lưu cài đặt'}
                        </Button>
                    </div>
                </form>

                <p className="text-center text-xs text-muted-foreground">
                    Theme: {themes?.[data.theme]?.label ?? data.theme}
                </p>
            </div>
        </AppLayout>
    );
}
