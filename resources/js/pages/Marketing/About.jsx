import { Link } from '@inertiajs/react';
import { Gauge, Layers, ShieldCheck } from 'lucide-react';

import { MarketingLayout } from '@/components/marketing/MarketingLayout';
import { Seo } from '@/components/marketing/Seo';
import { Button } from '@/components/ui/button';
import { useT } from '@/providers/I18nProvider';

const VALUES = [
    { icon: Layers, title: 'marketing.about_value1_title', desc: 'marketing.about_value1_desc' },
    { icon: Gauge, title: 'marketing.about_value2_title', desc: 'marketing.about_value2_desc' },
    { icon: ShieldCheck, title: 'marketing.about_value3_title', desc: 'marketing.about_value3_desc' },
];

export default function About({ seo }) {
    const t = useT();

    return (
        <MarketingLayout>
            <Seo seo={seo} />

            <section className="border-b border-border/60 bg-muted/30">
                <div className="mx-auto max-w-4xl px-4 py-20 text-center">
                    <h1 className="text-4xl font-bold tracking-tight sm:text-5xl">{t('marketing.about_hero_title')}</h1>
                    <p className="mx-auto mt-5 max-w-2xl text-lg text-muted-foreground">
                        {t('marketing.about_hero_desc')}
                    </p>
                </div>
            </section>

            <section className="mx-auto max-w-3xl px-4 py-16 text-center">
                <h2 className="text-2xl font-bold tracking-tight">{t('marketing.about_mission_title')}</h2>
                <p className="mt-4 text-lg leading-relaxed text-muted-foreground">{t('marketing.about_mission_desc')}</p>
            </section>

            <section className="mx-auto max-w-6xl px-4 pb-16">
                <div className="grid gap-6 sm:grid-cols-3">
                    {VALUES.map(({ icon: Icon, title, desc }) => (
                        <div key={title} className="rounded-2xl border border-border/70 bg-card p-6 text-center">
                            <div className="mx-auto flex size-12 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                <Icon className="size-6" />
                            </div>
                            <h3 className="mt-4 font-semibold">{t(title)}</h3>
                            <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{t(desc)}</p>
                        </div>
                    ))}
                </div>

                <div className="mt-12 flex justify-center">
                    <Button size="lg" asChild>
                        <Link href="/contact">{t('marketing.cta_button')}</Link>
                    </Button>
                </div>
            </section>
        </MarketingLayout>
    );
}
