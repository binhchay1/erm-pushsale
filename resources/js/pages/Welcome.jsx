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
        <div className="rounded-2xl border border-border/70 bg-card p-6 sm:p-8">
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
            <section className="relative overflow-hidden">
                <div
                    className="pointer-events-none absolute inset-0 -z-10 bg-gradient-to-b from-primary/5 via-background to-background"
                    aria-hidden
                />
                <div
                    className="pointer-events-none absolute -top-32 left-1/2 -z-10 h-96 w-[42rem] -translate-x-1/2 rounded-full bg-primary/10 blur-3xl"
                    aria-hidden
                />
                <div className="mx-auto max-w-4xl px-4 pb-16 pt-20 text-center sm:pt-28">
                    <span className="inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/5 px-3 py-1 text-xs font-medium text-primary">
                        <Sparkles className="size-3.5" />
                        {t('marketing.badge')}
                    </span>
                    <h1 className="mt-6 text-4xl font-bold tracking-tight sm:text-5xl lg:text-6xl">
                        {t('welcome.hero_title')}
                    </h1>
                    <p className="mx-auto mt-5 max-w-2xl text-lg text-muted-foreground">{t('welcome.hero_desc')}</p>
                    <div className="mt-8 flex flex-wrap items-center justify-center gap-3">
                        <Button size="lg" asChild>
                            <Link href="/login">
                                {t('welcome.cta_login')}
                                <ArrowRight className="size-4" />
                            </Link>
                        </Button>
                        <Button size="lg" variant="outline" asChild>
                            <Link href="/features">{t('marketing.explore_features')}</Link>
                        </Button>
                    </div>
                    <p className="mt-6 text-sm text-muted-foreground">{t('marketing.trust')}</p>
                </div>
            </section>

            {/* Stats */}
            <section className="border-y border-border/60 bg-muted/30">
                <div className="mx-auto grid max-w-6xl grid-cols-2 gap-6 px-4 py-10 lg:grid-cols-4">
                    {STATS.map((s) => (
                        <div key={s.label} className="text-center">
                            <div className="text-2xl font-bold tracking-tight text-primary sm:text-3xl">{t(s.value)}</div>
                            <div className="mt-1 text-sm text-muted-foreground">{t(s.label)}</div>
                        </div>
                    ))}
                </div>
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
                            className="group rounded-2xl border border-border/70 bg-card p-6 transition-colors hover:border-primary/40"
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
            <section className="border-t border-border/60 bg-muted/30">
                <div className="mx-auto max-w-6xl px-4 py-20">
                    <h2 className="text-center text-3xl font-bold tracking-tight">{t('marketing.steps_title')}</h2>
                    <div className="mt-12 grid gap-8 md:grid-cols-3">
                        {STEPS.map((s) => (
                            <div key={s.n} className="relative">
                                <span className="text-4xl font-bold text-primary/25">{s.n}</span>
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
                <div className="mx-auto max-w-5xl overflow-hidden rounded-3xl bg-primary px-6 py-14 text-center text-primary-foreground sm:px-12">
                    <h2 className="text-3xl font-bold tracking-tight">{t('marketing.cta_title')}</h2>
                    <p className="mx-auto mt-3 max-w-xl text-primary-foreground/85">{t('marketing.cta_desc')}</p>
                    <div className="mt-8 flex flex-wrap items-center justify-center gap-3">
                        <Button size="lg" variant="secondary" asChild>
                            <Link href="/contact">{t('marketing.cta_button')}</Link>
                        </Button>
                        <Button
                            size="lg"
                            variant="outline"
                            className="border-primary-foreground/30 bg-transparent text-primary-foreground hover:bg-primary-foreground/10 hover:text-primary-foreground"
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
