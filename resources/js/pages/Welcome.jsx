import { Link } from '@inertiajs/react';
import {
    ArrowRight,
    BarChart3,
    Boxes,
    CheckCircle2,
    Megaphone,
    PhoneCall,
    Shuffle,
    Sparkles,
    Truck,
    Wallet,
} from 'lucide-react';

import { MarketingLayout } from '@/components/marketing/MarketingLayout';
import { Seo } from '@/components/marketing/Seo';
import { Button } from '@/components/ui/button';
import { useT } from '@/providers/I18nProvider';

const MODULES = [
    { icon: PhoneCall, title: 'marketing.module_sales_title', desc: 'marketing.module_sales_desc' },
    { icon: Megaphone, title: 'marketing.module_marketing_title', desc: 'marketing.module_marketing_desc' },
    { icon: Truck, title: 'marketing.module_warehouse_title', desc: 'marketing.module_warehouse_desc' },
    { icon: Shuffle, title: 'marketing.module_allocation_title', desc: 'marketing.module_allocation_desc' },
    { icon: Wallet, title: 'marketing.module_accounting_title', desc: 'marketing.module_accounting_desc' },
    { icon: BarChart3, title: 'marketing.module_reports_title', desc: 'marketing.module_reports_desc' },
];

const STATS = [
    { value: 'marketing.stat_modules_value', label: 'marketing.stat_modules_label' },
    { value: 'marketing.stat_realtime_value', label: 'marketing.stat_realtime_label' },
    { value: 'marketing.stat_tenant_value', label: 'marketing.stat_tenant_label' },
    { value: 'marketing.stat_roles_value', label: 'marketing.stat_roles_label' },
];

const STEPS = [
    { n: '01', title: 'marketing.step1_title', desc: 'marketing.step1_desc' },
    { n: '02', title: 'marketing.step2_title', desc: 'marketing.step2_desc' },
    { n: '03', title: 'marketing.step3_title', desc: 'marketing.step3_desc' },
];

function Highlight({ icon: Icon, title, desc, points }) {
    const t = useT();
    return (
        <div className="public-highlight-card rounded-2xl border p-6 sm:p-8">
            <div className="flex size-12 items-center justify-center rounded-xl bg-primary/10 text-primary">
                <Icon className="size-6" />
            </div>
            <h3 className="mt-4 text-xl font-semibold tracking-tight">{t(title)}</h3>
            <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{t(desc)}</p>
            <ul className="mt-4 space-y-2">
                {points.map((p) => (
                    <li key={p} className="flex items-start gap-2 text-sm">
                        <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-primary" />
                        <span>{t(p)}</span>
                    </li>
                ))}
            </ul>
        </div>
    );
}

export default function Welcome({ seo }) {
    const t = useT();

    return (
        <MarketingLayout>
            <Seo seo={seo} />
            <div className="public-home-page">

            {/* Hero */}
            <section className="public-home-hero-bg relative w-full overflow-hidden">
                <div className="mx-auto grid w-full max-w-6xl items-center gap-10 px-4 py-12 lg:grid-cols-2 lg:gap-12 lg:py-16">
                    <div className="text-center lg:text-left">
                        <span className="inline-flex items-center gap-2 rounded-full border border-primary/25 bg-primary/10 px-3 py-1 text-xs font-semibold text-primary">
                            <Sparkles className="size-3.5" aria-hidden />
                            {t('marketing.badge')}
                        </span>
                        <h1 className="mt-5 text-balance text-4xl font-bold tracking-tight text-foreground sm:text-5xl lg:text-[2.75rem] lg:leading-[1.08]">
                            {t('welcome.hero_title')}
                        </h1>
                        <p className="mx-auto mt-4 max-w-xl text-pretty text-base leading-relaxed text-muted-foreground sm:text-lg lg:mx-0">
                            {t('welcome.hero_desc')}
                        </p>
                        <div className="mt-8 flex flex-wrap items-center justify-center gap-3 lg:justify-start">
                            <Button size="lg" asChild>
                                <Link href="/login">
                                    {t('welcome.cta_login')}
                                    <ArrowRight className="size-4" aria-hidden />
                                </Link>
                            </Button>
                            <Button size="lg" variant="outline" className="public-hero-secondary-btn" asChild>
                                <Link href="/features">{t('marketing.explore_features')}</Link>
                            </Button>
                        </div>
                    </div>

                    <div className="public-hero-panel relative mx-auto w-full max-w-md rounded-2xl p-6 sm:p-8 lg:max-w-none">
                        <p className="text-sm font-semibold text-foreground">{t('marketing.modules_title')}</p>
                        <ul className="mt-4 space-y-3">
                            {MODULES.slice(0, 4).map(({ icon: Icon, title, desc }) => (
                                <li
                                    key={title}
                                    className="public-hero-panel-item flex gap-3 rounded-xl border p-3"
                                >
                                    <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/15 text-primary">
                                        <Icon className="size-4" aria-hidden />
                                    </div>
                                    <div className="min-w-0">
                                        <p className="text-sm font-medium leading-snug text-foreground">{t(title)}</p>
                                        <p className="mt-0.5 line-clamp-2 text-xs leading-relaxed text-muted-foreground">
                                            {t(desc)}
                                        </p>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </div>
                </div>
            </section>

            {/* Stats */}
            <section className="public-home-stats border-y">
                <div className="mx-auto grid max-w-6xl grid-cols-2 gap-6 px-4 py-10 lg:grid-cols-4">
                    {STATS.map((s) => (
                        <div key={s.label} className="text-center">
                            <div className="public-stat-value text-2xl font-bold tracking-tight sm:text-3xl">{t(s.value)}</div>
                            <div className="mt-1 text-sm text-muted-foreground">{t(s.label)}</div>
                        </div>
                    ))}
                </div>
                <p className="mx-auto max-w-2xl px-4 pb-8 text-center text-sm text-muted-foreground">{t('marketing.trust')}</p>
            </section>

            {/* Modules */}
            <section className="mx-auto max-w-6xl px-4 py-20">
                <div className="mx-auto max-w-2xl text-center">
                    <h2 className="text-3xl font-bold tracking-tight">{t('marketing.modules_title')}</h2>
                    <p className="mt-3 text-muted-foreground">{t('marketing.modules_subtitle')}</p>
                </div>
                <div className="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    {MODULES.map(({ icon: Icon, title, desc }) => (
                        <div
                            key={title}
                            className="public-feature-card group rounded-2xl border p-6 transition-colors"
                        >
                            <div className="flex size-11 items-center justify-center rounded-xl bg-primary/10 text-primary transition-colors group-hover:bg-primary group-hover:text-primary-foreground">
                                <Icon className="size-5" />
                            </div>
                            <h3 className="mt-4 font-semibold">{t(title)}</h3>
                            <p className="mt-1.5 text-sm leading-relaxed text-muted-foreground">{t(desc)}</p>
                        </div>
                    ))}
                </div>
            </section>

            {/* Steps */}
            <section className="public-home-steps border-t border-border/60">
                <div className="mx-auto max-w-6xl px-4 py-20">
                    <h2 className="text-center text-3xl font-bold tracking-tight">{t('marketing.steps_title')}</h2>
                    <div className="mt-12 grid gap-8 md:grid-cols-3">
                        {STEPS.map((s) => (
                            <div key={s.n} className="relative">
                                <span className="public-step-index text-4xl font-bold">{s.n}</span>
                                <h3 className="mt-2 text-lg font-semibold">{t(s.title)}</h3>
                                <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{t(s.desc)}</p>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* Highlights */}
            <section className="mx-auto max-w-6xl px-4 py-20">
                <div className="grid gap-6 lg:grid-cols-2">
                    <Highlight
                        icon={Boxes}
                        title="marketing.tenant_title"
                        desc="marketing.tenant_desc"
                        points={['marketing.tenant_point_1', 'marketing.tenant_point_2', 'marketing.tenant_point_3']}
                    />
                    <Highlight
                        icon={BarChart3}
                        title="marketing.analytics_title"
                        desc="marketing.analytics_desc"
                        points={[
                            'marketing.analytics_point_1',
                            'marketing.analytics_point_2',
                            'marketing.analytics_point_3',
                        ]}
                    />
                </div>
            </section>

            {/* CTA */}
            <section className="px-4 pb-20">
                <div className="public-marketing-cta mx-auto max-w-5xl overflow-hidden rounded-3xl px-6 py-14 text-center sm:px-12">
                    <h2 className="text-3xl font-bold tracking-tight">{t('marketing.cta_title')}</h2>
                    <p className="mx-auto mt-3 max-w-xl">{t('marketing.cta_desc')}</p>
                    <div className="mt-8 flex flex-wrap items-center justify-center gap-3">
                        <Button size="lg" variant="secondary" className="text-foreground" asChild>
                            <Link href="/contact">{t('marketing.cta_button')}</Link>
                        </Button>
                        <Button
                            size="lg"
                            variant="outline"
                            className="border-white/35 bg-white/10 text-white hover:bg-white/20 hover:text-white"
                            asChild
                        >
                            <Link href="/features">{t('marketing.cta_secondary')}</Link>
                        </Button>
                    </div>
                </div>
            </section>
            </div>
        </MarketingLayout>
    );
}
