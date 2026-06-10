import { Link } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    Construction,
    FileQuestion,
    Home,
    LayoutDashboard,
    Lock,
    RefreshCw,
    ServerCrash,
    ShieldX,
    Timer,
} from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { getErrorMeta } from '@/lib/error-pages';
import { cn } from '@/lib/utils';

const TONE_STYLES = {
    muted: {
        iconWrap: 'bg-muted text-muted-foreground',
        code: 'text-muted-foreground/25',
        accent: 'from-muted/40 to-transparent',
    },
    warning: {
        iconWrap: 'bg-amber-500/15 text-amber-600 dark:text-amber-400',
        code: 'text-amber-500/20',
        accent: 'from-amber-500/10 to-transparent',
    },
    danger: {
        iconWrap: 'bg-destructive/10 text-destructive',
        code: 'text-destructive/15',
        accent: 'from-destructive/10 to-transparent',
    },
};

function StatusIcon({ status }) {
    const className = 'size-7';

    switch (status) {
        case 401:
            return <Lock className={className} />;
        case 403:
            return <ShieldX className={className} />;
        case 404:
            return <FileQuestion className={className} />;
        case 419:
            return <RefreshCw className={className} />;
        case 429:
            return <Timer className={className} />;
        case 503:
            return <Construction className={className} />;
        case 'client':
            return <AlertTriangle className={className} />;
        default:
            return <ServerCrash className={className} />;
    }
}

/**
 * Khung hiển thị lỗi — dùng chung cho trang HTTP và ErrorBoundary.
 */
export function ErrorShell({
    status = 500,
    title,
    description,
    message,
    brandName = 'ERM SaleOps',
    brandTagline,
    homeUrl = '/login',
    showLogin = false,
    children,
    detail,
}) {
    const meta = getErrorMeta(status === 'client' ? 'client' : status);
    const tone = TONE_STYLES[meta.tone] ?? TONE_STYLES.danger;
    const displayTitle = title ?? meta.title;
    const displayDescription = description ?? meta.description;
    const codeLabel = status === 'client' ? '!' : String(status);

    return (
        <div className="relative flex min-h-svh flex-col items-center justify-center overflow-hidden bg-gradient-to-br from-background via-muted/30 to-background px-4 py-10">
            <div
                className={cn(
                    'pointer-events-none absolute inset-x-0 top-0 h-64 bg-gradient-to-b',
                    tone.accent,
                )}
                aria-hidden
            />
            <div
                className="pointer-events-none absolute -top-24 left-1/2 h-72 w-72 -translate-x-1/2 rounded-full bg-primary/10 blur-3xl"
                aria-hidden
            />
            <div
                className="pointer-events-none absolute bottom-0 right-0 h-64 w-64 rounded-full bg-primary/5 blur-3xl"
                aria-hidden
            />

            <div className="relative z-10 w-full max-w-lg">
                <div className="mb-6 flex flex-col items-center gap-2 text-center">
                    <div className="flex size-11 items-center justify-center rounded-xl bg-primary text-primary-foreground shadow-md">
                        <LayoutDashboard className="size-5" />
                    </div>
                    <p className="text-sm font-semibold text-foreground">{brandName}</p>
                    {brandTagline && (
                        <p className="max-w-sm text-xs text-muted-foreground">{brandTagline}</p>
                    )}
                </div>

                <Card className="overflow-hidden border-border/80 shadow-lg shadow-primary/5">
                    <CardContent className="relative p-0">
                        <span
                            className={cn(
                                'pointer-events-none absolute -right-4 -top-6 select-none text-[7rem] font-black leading-none',
                                tone.code,
                            )}
                            aria-hidden
                        >
                            {codeLabel}
                        </span>

                        <div className="relative space-y-5 p-6 sm:p-8">
                            <div className="flex items-start gap-4">
                                <div
                                    className={cn(
                                        'flex size-12 shrink-0 items-center justify-center rounded-xl',
                                        tone.iconWrap,
                                    )}
                                >
                                    <StatusIcon status={status} />
                                </div>
                                <div className="min-w-0 pt-0.5">
                                    <p className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                        {status === 'client' ? 'Lỗi ứng dụng' : `Mã lỗi ${status}`}
                                    </p>
                                    <h1 className="mt-1 text-xl font-bold tracking-tight text-foreground">
                                        {displayTitle}
                                    </h1>
                                </div>
                            </div>

                            <p className="text-sm leading-relaxed text-muted-foreground">
                                {displayDescription}
                            </p>

                            {message && (
                                <p className="rounded-lg border border-border/60 bg-muted/40 px-3 py-2 text-sm text-foreground">
                                    {message}
                                </p>
                            )}

                            {detail && (
                                <pre className="max-h-48 overflow-auto rounded-lg border border-destructive/20 bg-destructive/5 p-3 text-xs whitespace-pre-wrap break-words text-destructive">
                                    {detail}
                                </pre>
                            )}

                            <div className="flex flex-wrap gap-2 pt-1">
                                {children ?? (
                                    <>
                                        <Button asChild>
                                            <Link href={homeUrl}>
                                                <Home className="size-4" />
                                                {showLogin ? 'Đăng nhập' : 'Về trang chủ'}
                                            </Link>
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => window.history.back()}
                                        >
                                            <ArrowLeft className="size-4" />
                                            Quay lại
                                        </Button>
                                    </>
                                )}
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}
