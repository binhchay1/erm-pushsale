import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { CurrencyInput } from '@/components/ui/currency-input';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout';
import { useT } from '@/providers/I18nProvider';

const channels = ['facebook', 'tiktok', 'google', 'zalo', 'landing', 'shopee', 'lazada'];

export default function CampaignForm({ baseUrl, campaign, products, marketers }) {
    const t = useT();
    const isEdit = Boolean(campaign?.id);
    const { data, setData, post, put, processing, errors } = useForm({
        name: campaign?.name ?? '',
        product_id: campaign?.product_id ?? '',
        marketer_user_id: campaign?.marketer_user_id ?? '',
        ad_channel: campaign?.ad_channel ?? 'landing',
        utm_source: campaign?.utm_source ?? '',
        utm_campaign: campaign?.utm_campaign ?? '',
        budget: campaign?.budget ?? 0,
        is_active: campaign?.is_active ?? true,
    });

    const submit = (e) => {
        e.preventDefault();
        if (isEdit) {
            put(`${baseUrl}/${campaign.id}`);
        } else {
            post(baseUrl);
        }
    };

    return (
        <AppLayout>
            <Head title={isEdit ? t('pages.campaigns.admin_edit') : t('pages.campaigns.admin_form_create')} />

            <div className="mx-auto max-w-2xl space-y-6">
                <div className="flex items-center gap-3">
                    <Button variant="ghost" size="icon-sm" asChild>
                        <Link href={baseUrl}>
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <h1 className="text-2xl font-bold tracking-tight">
                        {isEdit ? t('pages.campaigns.admin_edit') : t('pages.campaigns.admin_form_create')}
                    </h1>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('pages.campaigns.form_info')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-4">
                            <div className="space-y-2">
                                <Label>{t('pages.campaigns.name')}</Label>
                                <Input
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder={t('pages.campaigns.name_placeholder')}
                                />
                                {errors.name && <p className="text-xs text-destructive">{errors.name}</p>}
                            </div>

                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label>{t('pages.campaigns.product_in_stock')}</Label>
                                    <select
                                        className="input-soft h-10 w-full px-3"
                                        value={data.product_id}
                                        onChange={(e) => setData('product_id', e.target.value)}
                                    >
                                        <option value="">{t('pages.campaigns.select_product')}</option>
                                        {products.map((p) => (
                                            <option key={p.id} value={p.id}>
                                                {p.name}
                                            </option>
                                        ))}
                                    </select>
                                    {errors.product_id && (
                                        <p className="text-xs text-destructive">{errors.product_id}</p>
                                    )}
                                </div>
                                <div className="space-y-2">
                                    <Label>{t('pages.campaigns.marketer_label')}</Label>
                                    <select
                                        className="input-soft h-10 w-full px-3"
                                        value={data.marketer_user_id}
                                        onChange={(e) => setData('marketer_user_id', e.target.value)}
                                    >
                                        <option value="">{t('pages.campaigns.select_marketer')}</option>
                                        {marketers.map((m) => (
                                            <option key={m.id} value={m.id}>
                                                {m.name}
                                            </option>
                                        ))}
                                    </select>
                                    {errors.marketer_user_id && (
                                        <p className="text-xs text-destructive">{errors.marketer_user_id}</p>
                                    )}
                                </div>
                            </div>

                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label>{t('pages.campaigns.channel')}</Label>
                                    <select
                                        className="input-soft h-10 w-full px-3"
                                        value={data.ad_channel}
                                        onChange={(e) => setData('ad_channel', e.target.value)}
                                    >
                                        {channels.map((c) => (
                                            <option key={c} value={c}>
                                                {c}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div className="space-y-2">
                                    <Label>{t('pages.campaigns.budget_vnd')}</Label>
                                    <CurrencyInput
                                        value={data.budget}
                                        onChange={(amount) => setData('budget', amount)}
                                    />
                                </div>
                            </div>

                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label>utm_source</Label>
                                    <Input
                                        value={data.utm_source}
                                        onChange={(e) => setData('utm_source', e.target.value)}
                                        placeholder="tiktok"
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label>{t('pages.campaigns.utm_campaign_label')}</Label>
                                    <Input
                                        value={data.utm_campaign}
                                        onChange={(e) => setData('utm_campaign', e.target.value)}
                                        placeholder="serum-vitc-t5"
                                    />
                                    {errors.utm_campaign ? (
                                        <p className="text-xs text-destructive">{errors.utm_campaign}</p>
                                    ) : (
                                        <p className="text-xs text-muted-foreground">
                                            {t('pages.campaigns.utm_campaign_hint')}
                                        </p>
                                    )}
                                </div>
                            </div>

                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={data.is_active}
                                    onChange={(e) => setData('is_active', e.target.checked)}
                                    className="size-4 rounded border"
                                />
                                {t('pages.campaigns.active_receive')}
                            </label>

                            <div className="flex justify-end">
                                <Button type="submit" disabled={processing}>
                                    {processing ? t('common.saving') : t('pages.campaigns.save')}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
