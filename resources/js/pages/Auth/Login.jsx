import { Head, usePage } from '@inertiajs/react';
import { LayoutDashboard } from 'lucide-react';

import GuestLayout from '@/layouts/GuestLayout';
import { LoginForm } from '@/components/auth/LoginForm';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

export default function Login() {
    const { brand } = usePage().props;
    const name = brand?.name ?? 'ERM SaleOps';
    const tagline = brand?.tagline ?? 'Hệ thống điều hành bán hàng & vận hành';

    return (
        <GuestLayout>
            <Head title="Đăng nhập" />

            <div className="relative z-10 w-full max-w-md">
                <div className="mb-6 flex flex-col items-center gap-2 text-center">
                    <div className="flex size-12 items-center justify-center rounded-xl bg-primary text-primary-foreground shadow-md">
                        <LayoutDashboard className="size-6" />
                    </div>
                    <h1 className="text-2xl font-bold tracking-tight text-foreground">{name}</h1>
                    <p className="text-sm text-muted-foreground">{tagline}</p>
                </div>

                <Card className="border-border/80 shadow-lg shadow-blue-500/5">
                    <CardHeader>
                        <CardTitle>Đăng nhập</CardTitle>
                        <CardDescription>Quản trị viên, Sale, Marketing hoặc Kho</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <LoginForm />
                    </CardContent>
                </Card>

                <p className="mt-4 text-center text-xs text-muted-foreground">
                    Demo: admin@saleops.local · sales@saleops.local · marketing@saleops.local · warehouse@saleops.local
                    <br />
                    Mật khẩu <code className="rounded bg-muted px-1 py-0.5">password</code>
                </p>
            </div>
        </GuestLayout>
    );
}
