import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Home, LogIn, RefreshCw } from 'lucide-react';

import { ErrorShell } from '@/components/errors/ErrorShell';
import { Button } from '@/components/ui/button';

export default function ErrorPage({ status = 500, message = null, homeUrl = '/login' }) {
    const { brand } = usePage().props;
    const needsLogin = status === 401 || status === 419;
    const canReload = status === 419 || status === 429 || status >= 500;

    return (
        <>
            <Head title={`Lỗi ${status}`} />

            <ErrorShell
                status={status}
                message={message}
                brandName={brand?.name}
                brandTagline={brand?.tagline}
                homeUrl={needsLogin ? '/login' : homeUrl}
                showLogin={needsLogin}
            >
                {needsLogin ? (
                    <Button asChild>
                        <Link href="/login">
                            <LogIn className="size-4" />
                            Đăng nhập lại
                        </Link>
                    </Button>
                ) : (
                    <Button asChild>
                        <Link href={homeUrl}>
                            <Home className="size-4" />
                            Về trang chủ
                        </Link>
                    </Button>
                )}

                {canReload && (
                    <Button type="button" variant="outline" onClick={() => window.location.reload()}>
                        <RefreshCw className="size-4" />
                        Tải lại trang
                    </Button>
                )}

                <Button type="button" variant="ghost" onClick={() => window.history.back()}>
                    <ArrowLeft className="size-4" />
                    Quay lại
                </Button>
            </ErrorShell>
        </>
    );
}
