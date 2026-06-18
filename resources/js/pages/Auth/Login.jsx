import { Head, usePage } from '@inertiajs/react';
import { LayoutDashboard } from 'lucide-react';

import GuestLayout from '@/layouts/GuestLayout';
import { LoginForm } from '@/components/auth/LoginForm';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useT } from '@/providers/I18nProvider';

export default function Login() {
    const { brand } = usePage().props;
    const t = useT();
    const name = brand?.name ?? 'ERM SaleOps';
    const tagline = brand?.tagline ?? '';

    return (
        <GuestLayout>
            <Head title={t('auth.login_title')} />

            <div className="relative z-10 w-full max-w-md">
                <div className="mb-6 flex flex-col items-center gap-2 text-center">
                    <div className="flex size-12 items-center justify-center rounded-xl bg-primary text-primary-foreground shadow-md">
                        <LayoutDashboard className="size-6" />
                    </div>
                    <h1 className="text-2xl font-bold tracking-tight text-foreground">{name}</h1>
                    {tagline && <p className="text-sm text-muted-foreground">{tagline}</p>}
                </div>

                <Card className="border-border/80 shadow-lg shadow-blue-500/5">
                    <CardHeader>
                        <CardTitle>{t('auth.login_title')}</CardTitle>
                        <CardDescription>{t('auth.login_desc')}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <LoginForm />
                    </CardContent>
                </Card>

                <p className="mt-4 text-center text-xs text-muted-foreground">
                    {t('auth.demo_hint')}
                    <br />
                    {t('auth.demo_password')}{' '}
                    <code className="rounded bg-muted px-1 py-0.5">password</code>
                </p>
            </div>
        </GuestLayout>
    );
}
