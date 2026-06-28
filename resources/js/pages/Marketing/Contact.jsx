import { Clock, Mail, Phone } from 'lucide-react';
import { useState } from 'react';

import { MarketingLayout } from '@/components/marketing/MarketingLayout';
import { Seo } from '@/components/marketing/Seo';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useT } from '@/providers/I18nProvider';

export default function Contact({ seo }) {
    const t = useT();
    const [sent, setSent] = useState(false);

    const channels = [
        { icon: Mail, label: t('marketing.contact_email_label'), value: t('marketing.contact_email_value') },
        { icon: Phone, label: t('marketing.contact_phone_label'), value: t('marketing.contact_phone_value') },
        { icon: Clock, label: t('marketing.contact_hours_label'), value: t('marketing.contact_hours_value') },
    ];

    const submit = (e) => {
        e.preventDefault();
        setSent(true);
    };

    return (
        <MarketingLayout>
            <Seo seo={seo} />

            <section className="border-b border-border/60 bg-muted/30">
                <div className="mx-auto max-w-4xl px-4 py-20 text-center">
                    <h1 className="text-4xl font-bold tracking-tight sm:text-5xl">{t('marketing.contact_hero_title')}</h1>
                    <p className="mx-auto mt-5 max-w-2xl text-lg text-muted-foreground">
                        {t('marketing.contact_hero_desc')}
                    </p>
                </div>
            </section>

            <section className="mx-auto max-w-6xl px-4 py-16">
                <div className="grid gap-10 lg:grid-cols-2">
                    <div className="space-y-4">
                        {channels.map(({ icon: Icon, label, value }) => (
                            <div
                                key={label}
                                className="flex items-center gap-4 rounded-2xl border border-border/70 bg-card p-5"
                            >
                                <div className="flex size-11 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                    <Icon className="size-5" />
                                </div>
                                <div>
                                    <div className="text-sm text-muted-foreground">{label}</div>
                                    <div className="font-medium">{value}</div>
                                </div>
                            </div>
                        ))}
                        <p className="rounded-2xl border border-dashed border-border/70 bg-muted/30 p-5 text-sm text-muted-foreground">
                            {t('marketing.contact_form_note')}
                        </p>
                    </div>

                    <div className="rounded-2xl border border-border/70 bg-card p-6 sm:p-8">
                        {sent ? (
                            <div className="flex h-full min-h-56 flex-col items-center justify-center text-center">
                                <div className="flex size-14 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                                    <Mail className="size-7" />
                                </div>
                                <p className="mt-4 text-base font-medium">{t('marketing.contact_form_success')}</p>
                            </div>
                        ) : (
                            <form onSubmit={submit} className="space-y-4">
                                <div className="space-y-1.5">
                                    <Label htmlFor="c_name">{t('marketing.contact_form_name')}</Label>
                                    <Input id="c_name" name="name" required autoComplete="name" />
                                </div>
                                <div className="space-y-1.5">
                                    <Label htmlFor="c_email">{t('marketing.contact_form_email')}</Label>
                                    <Input id="c_email" name="email" type="email" required autoComplete="email" />
                                </div>
                                <div className="space-y-1.5">
                                    <Label htmlFor="c_company">{t('marketing.contact_form_company')}</Label>
                                    <Input id="c_company" name="company" autoComplete="organization" />
                                </div>
                                <div className="space-y-1.5">
                                    <Label htmlFor="c_message">{t('marketing.contact_form_message')}</Label>
                                    <textarea
                                        id="c_message"
                                        name="message"
                                        rows={4}
                                        required
                                        className="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                    />
                                </div>
                                <Button type="submit" size="lg" className="w-full">
                                    {t('marketing.contact_form_submit')}
                                </Button>
                            </form>
                        )}
                    </div>
                </div>
            </section>
        </MarketingLayout>
    );
}
