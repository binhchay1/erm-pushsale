import { Link } from '@inertiajs/react';
import { BarChart3, CheckCircle2, Megaphone, PhoneCall, Shield, Shuffle, Truck } from 'lucide-react';

import { MarketingLayout } from '@/components/marketing/MarketingLayout';
import { Seo } from '@/components/marketing/Seo';
import { Button } from '@/components/ui/button';
import { useT } from '@/providers/I18nProvider';

const BLOCKS = [
    {
        icon: PhoneCall,
        title: 'marketing.feat_sales_title',
        desc: 'marketing.feat_sales_desc',
        points: ['marketing.feat_sales_1', 'marketing.feat_sales_2', 'marketing.feat_sales_3'],
    },
    {
        icon: Megaphone,
        title: 'marketing.feat_marketing_title',
        desc: 'marketing.feat_marketing_desc',
        points: ['marketing.feat_marketing_1', 'marketing.feat_marketing_2', 'marketing.feat_marketing_3'],
    },
    {
        icon: Truck,
        title: 'marketing.feat_warehouse_title',
        desc: 'marketing.feat_warehouse_desc',
        points: ['marketing.feat_warehouse_1', 'marketing.feat_warehouse_2', 'marketing.feat_warehouse_3'],
    },
    {
        icon: Shuffle,
        title: 'marketing.feat_allocation_title',
        desc: 'marketing.feat_allocation_desc',
        points: ['marketing.feat_allocation_1', 'marketing.feat_allocation_2', 'marketing.feat_allocation_3'],
    },
    {
        icon: BarChart3,
        title: 'marketing.feat_reports_title',
        desc: 'marketing.feat_reports_desc',
        points: ['marketing.feat_reports_1', 'marketing.feat_reports_2', 'marketing.feat_reports_3'],
    },
    {
        icon: Shield,
        title: 'marketing.feat_platform_title',
        desc: 'marketing.feat_platform_desc',
        points: ['marketing.feat_platform_1', 'marketing.feat_platform_2', 'marketing.feat_platform_3'],
    },
];

export default function Features({ seo }) {
    const t = useT();

    return (
        <MarketingLayout>
            <Seo seo={seo} />

            <section className="border-b border-border/60 bg-muted/30">
                <div className="mx-auto max-w-4xl px-4 py-20 text-center">
                    <h1 className="text-4xl font-bold tracking-tight sm:text-5xl">{t('marketing.features_hero_title')}</h1>
                    <p className="mx-auto mt-5 max-w-2xl text-lg text-muted-foreground">
                        {t('marketing.features_hero_desc')}
                    </p>
                </div>
            </section>

            <section className="mx-auto max-w-6xl px-4 py-16">
                <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                    {BLOCKS.map(({ icon: Icon, title, desc, points }) => (
                        <article key={title} className="rounded-2xl border border-border/70 bg-card p-6">
                            <div className="flex size-11 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                <Icon className="size-5" />
                            </div>
                            <h2 className="mt-4 text-lg font-semibold">{t(title)}</h2>
                            <p className="mt-1.5 text-sm leading-relaxed text-muted-foreground">{t(desc)}</p>
                            <ul className="mt-4 space-y-2 border-t border-border/60 pt-4">
                                {points.map((p) => (
                                    <li key={p} className="flex items-start gap-2 text-sm">
                                        <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-primary" />
                                        <span>{t(p)}</span>
                                    </li>
                                ))}
                            </ul>
                        </article>
                    ))}
                </div>

                <div className="mt-12 flex flex-wrap items-center justify-center gap-3">
                    <Button size="lg" asChild>
                        <Link href="/contact">{t('marketing.cta_button')}</Link>
                    </Button>
                    <Button size="lg" variant="outline" asChild>
                        <Link href="/solutions">{t('marketing.nav_solutions')}</Link>
                    </Button>
                </div>
            </section>
        </MarketingLayout>
    );
}
