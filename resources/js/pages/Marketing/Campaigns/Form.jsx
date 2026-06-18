import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Copy } from 'lucide-react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { copyToClipboard } from '@/lib/clipboard';
import AppLayout from '@/layouts/AppLayout';
import { useT } from '@/providers/I18nProvider';

function slugPreview(name) {
    return name
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

export default function CampaignForm({ campaign, products, marketers, fieldMapping }) {
    const t = useT();
    const isEdit = Boolean(campaign?.id);
    const { data, setData, post, put, processing, errors } = useForm({
        name: campaign?.name ?? '',
        product_id: campaign?.product_id ?? '',
        marketer_user_id: campaign?.marketer_user_id ?? '',
        ad_channel: campaign?.ad_channel ?? 'landing',
        budget: campaign?.budget ?? 0,
        is_active: campaign?.is_active ?? true,
    });

    const utmPreview = slugPreview(data.name) || '…';

    const submit = (e) => {
        e.preventDefault();
        if (isEdit) {
            put(`/marketing/campaigns/${campaign.id}`);
        } else {
            post('/marketing/campaigns');
        }
    };

    const copyUrl = async () => {
        if (!campaign?.webhook_url) return;
        const ok = await copyToClipboard(campaign.webhook_url);
        ok ? toast.success(t('pages.campaigns.marketing_copy_success')) : toast.error(t('common.copy_failed'));
    };

    return (
        <AppLayout>
            <Head title={isEdit ? t('pages.campaigns.marketing_edit') : t('pages.campaigns.marketing_form_create')} />

            <div className="mx-auto max-w-2xl space-y-6">
                <div className="flex items-center gap-3">
                    <Button variant="ghost" size="icon-sm" asChild>
                        <Link href="/marketing/campaigns">
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <h1 className="text-2xl font-bold tracking-tight">
                        {isEdit ? t('pages.campaigns.marketing_edit') : t('pages.campaigns.marketing_form_create')}
                    </h1>
                </div>

                {isEdit && campaign?.webhook_url && (
                    <Card className="border-primary/30 bg-primary/5">
                        <CardHeader>
                            <CardTitle className="text-base">{t('pages.campaigns.webhook_title')}</CardTitle>
                            <CardDescription>{t('pages.campaigns.webhook_desc')}</CardDescription>
                        </CardHeader>
                        <CardContent className="flex gap-2">
                            <Input readOnly value={campaign.webhook_url} className="font-mono text-xs" />
                            <Button type="button" variant="outline" onClick={copyUrl}>
                                <Copy className="size-4" />
                                {t('common.copy')}
                            </Button>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>{t('pages.campaigns.source_info')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-4">
                            <div className="space-y-2">
                                <Label>{t('pages.campaigns.landing_name')}</Label>
                                <Input
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder={t('pages.campaigns.landing_name_placeholder')}
                                />
                                {errors.name && <p className="text-xs text-destructive">{errors.name}</p>}
                                <p className="text-xs text-muted-foreground">{t('pages.campaigns.name_unique_hint')}</p>
                            </div>

                            <div className="rounded-lg border bg-muted/30 px-3 py-2 text-xs">
                                <span className="text-muted-foreground">{t('pages.campaigns.utm_auto')} </span>
                                <span className="font-mono font-medium">{utmPreview}</span>
                            </div>

                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label>{t('pages.campaigns.product')}</Label>
                                    <select
                                        className="input-soft h-10 w-full px-3"
                                        value={data.product_id}
                                        onChange={(e) => setData('product_id', e.target.value)}
                                    >
                                        <option value="">{t('pages.select_placeholder')}</option>
                                        {products.map((p) => (
                                            <option key={p.id} value={p.id}>
                                                {p.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div className="space-y-2">
                                    <Label>{t('pages.campaigns.marketer_label')}</Label>
                                    <select
                                        className="input-soft h-10 w-full px-3"
                                        value={data.marketer_user_id}
                                        onChange={(e) => setData('marketer_user_id', e.target.value)}
                                    >
                                        {marketers.map((m) => (
                                            <option key={m.id} value={m.id}>
                                                {m.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label>{t('pages.campaigns.budget_vnd')}</Label>
                                <Input
                                    type="number"
                                    min={0}
                                    value={data.budget}
                                    onChange={(e) => setData('budget', e.target.value)}
                                />
                            </div>

                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={data.is_active}
                                    onChange={(e) => setData('is_active', e.target.checked)}
                                    className="size-4 rounded border"
                                />
                                {t('pages.campaigns.receiving_leads')}
                            </label>

                            <div className="flex justify-end">
                                <Button type="submit" disabled={processing}>
                                    {processing
                                        ? t('common.saving')
                                        : isEdit
                                          ? t('pages.save')
                                          : t('pages.campaigns.create_and_get_url')}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">{t('pages.campaigns.ladipage_map')}</CardTitle>
                        <CardDescription>{t('pages.campaigns.ladipage_map_desc')}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <table className="w-full text-xs">
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    <th className="pb-2">{t('pages.campaigns.ladipage_col')}</th>
                                    <th className="pb-2">{t('pages.campaigns.system_col')}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {fieldMapping?.map((row) => (
                                    <tr key={row.ladipage} className="border-b border-border/50">
                                        <td className="py-2 font-mono">{row.ladipage}</td>
                                        <td className="py-2">{row.system}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
