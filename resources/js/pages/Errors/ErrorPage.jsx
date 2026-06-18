import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Home, LogIn, RefreshCw } from 'lucide-react';

import { ErrorShell } from '@/components/errors/ErrorShell';
import { Button } from '@/components/ui/button';
import { useT } from '@/providers/I18nProvider';

export default function ErrorPage({ status = 500, message = null, homeUrl = '/login' }) {
    const { brand } = usePage().props;
    const t = useT();
    const needsLogin = status === 401 || status === 419;
    const canReload = status === 419 || status === 429 || status >= 500;

    return (
        <>
            <Head title={`${t('errors.error_code', { code: status })}`} />

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
                            {t('auth.login')}
                        </Link>
                    </Button>
                ) : (
                    <Button asChild>
                        <Link href={homeUrl}>
                            <Home className="size-4" />
                            {t('errors.back_home')}
                        </Link>
                    </Button>
                )}

                {canReload && (
                    <Button type="button" variant="outline" onClick={() => window.location.reload()}>
                        <RefreshCw className="size-4" />
                        {t('common.refresh')}
                    </Button>
                )}

                <Button type="button" variant="ghost" onClick={() => window.history.back()}>
                    <ArrowLeft className="size-4" />
                    {t('common.back')}
                </Button>
            </ErrorShell>
        </>
    );
}
