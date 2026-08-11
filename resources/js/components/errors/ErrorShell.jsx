import { Link } from '@inertiajs/react';
import { ArrowLeft, Home, LayoutDashboard } from 'lucide-react';

import DonutErrorIllustration from '@/components/errors/DonutErrorIllustration';
import { Button } from '@/components/ui/button';
import { useT } from '@/providers/I18nProvider';
import { cn } from '@/lib/utils';

const PANEL_TONE = {
    400: 'from-slate-500/10 via-transparent to-transparent border-slate-200/80',
    401: 'from-amber-500/10 via-transparent to-transparent border-amber-200/80',
    403: 'from-orange-500/10 via-transparent to-transparent border-orange-200/80',
    404: 'from-rose-500/10 via-transparent to-transparent border-rose-200/80',
    405: 'from-cyan-500/10 via-transparent to-transparent border-cyan-200/80',
    410: 'from-fuchsia-500/10 via-transparent to-transparent border-fuchsia-200/80',
    419: 'from-violet-500/10 via-transparent to-transparent border-violet-200/80',
    429: 'from-sky-500/10 via-transparent to-transparent border-sky-200/80',
    500: 'from-amber-500/10 via-transparent to-transparent border-amber-200/80',
    503: 'from-blue-500/10 via-transparent to-transparent border-blue-200/80',
    client: 'from-red-500/10 via-transparent to-transparent border-red-200/80',
};

function errorKey(status) {
    return status === 'client' ? 'client' : Number(status || 500);
}

function copy(t, key, fallbackKey = 500) {
    const suffix = key === 'client' ? 'client' : String(key);
    const fallbackSuffix = String(fallbackKey);

    return {
        title: t(`errors.title_${suffix}`) || t(`errors.title_${fallbackSuffix}`),
        desc: t(`errors.desc_${suffix}`) || t(`errors.desc_${fallbackSuffix}`),
        hint: t(`errors.hint_${suffix}`) || t(`errors.hint_${fallbackSuffix}`),
    };
}

/**
 * Error display shell — shared by HTTP error pages and ErrorBoundary.
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
}) {
    const t = useT();
    const key = errorKey(status);
    const panelTone = PANEL_TONE[key] ?? PANEL_TONE[500];
    const code = key === 'client' ? 'ERR' : String(key);
    const localized = copy(t, key);
    const displayTitle = title ?? localized.title;
    const displayDescription = description ?? localized.desc;
    const showMessage = Boolean(message) && key !== 500 && key !== 503 && key !== 'client';

    return (
        <div className="erm-error-page fixed inset-0 z-[9999] flex h-dvh min-h-dvh w-screen items-center justify-center overflow-hidden bg-[radial-gradient(circle_at_top,_rgba(255,255,255,0.98),_rgba(248,250,252,0.96)_44%,_rgba(236,242,250,0.98)_100%)] px-4 py-6">
            <div className="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden>
                <div className="absolute -left-20 top-10 h-56 w-56 rounded-full bg-sky-300/18 blur-3xl" />
                <div className="absolute right-[-3rem] top-24 h-72 w-72 rounded-full bg-rose-200/24 blur-3xl" />
                <div className="absolute bottom-[-5rem] left-1/2 h-80 w-80 -translate-x-1/2 rounded-full bg-blue-100/70 blur-3xl" />
            </div>

            <main className={cn('relative z-10 grid h-[min(720px,calc(100dvh-32px))] min-h-[560px] w-full max-w-6xl grid-cols-1 overflow-hidden rounded-[30px] border bg-white/95 shadow-[0_28px_90px_rgba(15,23,42,0.14)] backdrop-blur lg:grid-cols-[1fr_1fr]', panelTone)}>
                <div className="absolute inset-x-0 top-0 h-36 bg-gradient-to-b opacity-100" aria-hidden />

                <section className="relative flex min-h-0 flex-col overflow-y-auto px-7 py-7 sm:px-10 sm:py-9 lg:px-12">
                    <div className="flex items-center gap-3">
                        <div className="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-primary text-primary-foreground shadow-md shadow-primary/20">
                            <LayoutDashboard className="size-5" />
                        </div>
                        <div className="min-w-0">
                            <p className="truncate text-sm font-bold text-slate-900">{brandName}</p>
                            <p className="truncate text-xs text-slate-500">{brandTagline || t('errors.brand_tagline')}</p>
                        </div>
                    </div>

                    <div className="mt-8 inline-flex w-fit items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.22em] text-slate-500">
                        {t('errors.error_code', { code })}
                    </div>

                    <div className="mt-7 max-w-xl">
                        <h1 className="text-3xl font-black leading-[1.06] tracking-[-0.045em] text-slate-950 sm:text-4xl lg:text-5xl">
                            {displayTitle}
                        </h1>
                        <p className="mt-5 text-base leading-7 text-slate-600 sm:text-[16px]">
                            {displayDescription}
                        </p>
                    </div>

                    {showMessage && (
                        <div className="mt-5 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm leading-6 text-slate-600">
                            {message}
                        </div>
                    )}

                    <div className="mt-6 rounded-2xl border border-dashed border-slate-200 bg-slate-50/80 px-4 py-3">
                        <p className="text-sm font-bold text-slate-800">{t('errors.suggested_next_step')}</p>
                        <p className="mt-1 text-sm leading-6 text-slate-600">{localized.hint}</p>
                    </div>

                    <div className="mt-auto flex flex-wrap gap-3 pt-8">
                        {children ?? (
                            <>
                                <Button asChild>
                                    <Link href={homeUrl}>
                                        <Home className="size-4" />
                                        {showLogin ? t('errors.login') : t('errors.back_home')}
                                    </Link>
                                </Button>
                                <Button type="button" variant="outline" onClick={() => window.history.back()}>
                                    <ArrowLeft className="size-4" />
                                    {t('common.back')}
                                </Button>
                            </>
                        )}
                    </div>
                </section>

                <section className="relative hidden min-h-0 items-center justify-center border-l border-slate-100 bg-gradient-to-b from-white to-slate-50/90 p-8 lg:flex">
                    <div className="w-full max-w-[460px] text-center">
                        <DonutErrorIllustration status={key} className="max-w-[450px]" />
                        <p className="mt-4 text-base font-bold text-slate-800">{t('errors.art_title')}</p>
                        <p className="mt-2 text-sm leading-6 text-slate-500">{t('errors.art_desc')}</p>
                    </div>
                </section>
            </main>
        </div>
    );
}
