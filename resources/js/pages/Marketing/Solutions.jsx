import { Link } from '@inertiajs/react';
import { ArrowRight, BarChart3, CheckCircle2, Megaphone, PhoneCall, Warehouse } from 'lucide-react';

import { MarketingLayout } from '@/components/marketing/MarketingLayout';
import { Seo } from '@/components/marketing/Seo';
import { Button } from '@/components/ui/button';
import { useT } from '@/providers/I18nProvider';

const SOLUTIONS = [
    {
        icon: PhoneCall,
        title: 'marketing.sol_sales_title',
        desc: 'marketing.sol_sales_desc',
        points: ['marketing.sol_sales_1', 'marketing.sol_sales_2', 'marketing.sol_sales_3'],
    },
    {
        icon: Megaphone,
        title: 'marketing.sol_marketing_title',
        desc: 'marketing.sol_marketing_desc',
        points: ['marketing.sol_marketing_1', 'marketing.sol_marketing_2', 'marketing.sol_marketing_3'],
    },
    {
        icon: Warehouse,
        title: 'marketing.sol_ops_title',
        desc: 'marketing.sol_ops_desc',
        points: ['marketing.sol_ops_1', 'marketing.sol_ops_2', 'marketing.sol_ops_3'],
    },
    {
        icon: BarChart3,
        title: 'marketing.sol_leader_title',
        desc: 'marketing.sol_leader_desc',
        points: ['marketing.sol_leader_1', 'marketing.sol_leader_2', 'marketing.sol_leader_3'],
    },
];

export default function Solutions({ seo }) {
    const t = useT();

    return (
        <MarketingLayout>
            <Seo seo={seo} />

            <section className="border-b border-border/60 bg-muted/30">
                <div className="mx-auto max-w-4xl px-4 py-20 text-center">
                    <h1 className="text-4xl font-bold tracking-tight sm:text-5xl">{t('marketing.solutions_hero_title')}</h1>
                    <p className="mx-auto mt-5 max-w-2xl text-lg text-muted-foreground">
                        {t('marketing.solutions_hero_desc')}
                    </p>
                </div>
            </section>

            <section className="mx-auto max-w-6xl px-4 py-16">
                <div className="grid gap-6 md:grid-cols-2">
                    {SOLUTIONS.map(({ icon: Icon, title, desc, points }) => (
                        <article
                            key={title}
                            className="flex flex-col rounded-2xl border border-border/70 bg-card p-7 sm:p-8"
                        >
                            <div className="flex items-center gap-3">
                                <div className="flex size-12 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                    <Icon className="size-6" />
                                </div>
                                <h2 className="text-xl font-semibold">{t(title)}</h2>
                            </div>
                            <p className="mt-4 text-sm leading-relaxed text-muted-foreground">{t(desc)}</p>
                            <ul className="mt-5 space-y-2.5">
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
                        <Link href="/contact">
                            {t('marketing.cta_button')}
                            <ArrowRight className="size-4" />
                        </Link>
                    </Button>
                    <Button size="lg" variant="outline" asChild>
                        <Link href="/features">{t('marketing.nav_features')}</Link>
                    </Button>
                </div>
            </section>
        </MarketingLayout>
    );
}
