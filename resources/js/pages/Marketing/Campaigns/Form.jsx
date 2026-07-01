import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Copy } from 'lucide-react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { CurrencyInput } from '@/components/ui/currency-input';
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

function StepBadge({ n }) {
    return (
        <span className="flex size-6 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-foreground">
            {n}
        </span>
    );
}

function CopyValue({ value, mono = true }) {
    const t = useT();
    const copy = async () => {
        const ok = await copyToClipboard(value);
        ok ? toast.success(t('pages.campaigns.copied')) : toast.error(t('common.copy_failed'));
    };

    return (
        <button
            type="button"
            onClick={copy}
            title={t('common.copy')}
            className="group inline-flex max-w-full items-center gap-1.5 rounded-md border bg-background px-2 py-1 text-left hover:border-primary/50"
        >
            <code className={`truncate text-xs ${mono ? 'font-mono' : ''}`}>{value}</code>
            <Copy className="size-3.5 shrink-0 text-muted-foreground group-hover:text-primary" />
        </button>
    );
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
                            <CardTitle className="text-base">{t('pages.campaigns.connect_title')}</CardTitle>
                            <CardDescription>{t('pages.campaigns.connect_desc')}</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-5 text-sm">
                            <p className="rounded-md border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-xs text-amber-800 dark:text-amber-200">
                                {t('pages.campaigns.connect_per_landing')}
                            </p>
                            <div className="flex gap-3">
                                <StepBadge n={1} />
                                <div className="space-y-1">
                                    <p className="font-medium">{t('pages.campaigns.connect_step1_title')}</p>
                                    <p className="text-xs text-muted-foreground">{t('pages.campaigns.connect_step1_desc')}</p>
                                </div>
                            </div>

                            <div className="flex gap-3">
                                <StepBadge n={2} />
                                <div className="min-w-0 flex-1 space-y-2">
                                    <p className="font-medium">{t('pages.campaigns.connect_step2_title')}</p>
                                    <p className="text-xs text-muted-foreground">{t('pages.campaigns.connect_step2_desc')}</p>
                                    <div className="space-y-1.5">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="w-44 shrink-0 text-xs text-muted-foreground">{t('pages.campaigns.connection_name')}</span>
                                            <CopyValue value={campaign.name} mono={false} />
                                        </div>
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="w-44 shrink-0 text-xs text-muted-foreground">{t('pages.campaigns.api_url')}</span>
                                            <CopyValue value={campaign.webhook_url} />
                                        </div>
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="w-44 shrink-0 text-xs text-muted-foreground">{t('pages.campaigns.content_type')}</span>
                                            <CopyValue value="x-www-form-urlencoded" />
                                        </div>
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="w-44 shrink-0 text-xs text-muted-foreground">{t('pages.campaigns.send_via_label')}</span>
                                            <span className="rounded-md border border-emerald-500/40 bg-emerald-500/10 px-2 py-1 text-xs font-medium text-emerald-700 dark:text-emerald-300">
                                                {t('pages.campaigns.send_via_value')}
                                            </span>
                                        </div>
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="w-44 shrink-0 text-xs text-muted-foreground">{t('pages.campaigns.header_label')}</span>
                                            <span className="rounded-md border bg-muted px-2 py-1 text-xs text-muted-foreground">
                                                {t('pages.campaigns.header_value')}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div className="flex gap-3">
                                <StepBadge n={3} />
                                <div className="min-w-0 flex-1 space-y-2">
                                    <p className="font-medium">{t('pages.campaigns.connect_step3_title')}</p>
                                    <p className="text-xs text-muted-foreground">{t('pages.campaigns.connect_step3_desc')}</p>
                                    <div className="overflow-hidden rounded-md border bg-background">
                                        <table className="w-full text-xs">
                                            <thead>
                                                <tr className="border-b bg-muted/40 text-left text-muted-foreground">
                                                    <th className="px-3 py-2 font-medium">{t('pages.campaigns.map_form_field')}</th>
                                                    <th className="px-3 py-2 font-medium">{t('pages.campaigns.map_api_name')}</th>
                                                    <th className="px-3 py-2 font-medium">{t('pages.campaigns.map_required')}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {fieldMapping?.map((row) => (
                                                    <tr key={row.api_name} className="border-b border-border/50 last:border-0">
                                                        <td className="px-3 py-2">{t(`pages.campaigns.fields.${row.key}`)}</td>
                                                        <td className="px-3 py-2">
                                                            <CopyValue value={row.api_name} />
                                                        </td>
                                                        <td className="px-3 py-2">
                                                            {row.required ? (
                                                                <span className="font-medium text-destructive">{t('pages.campaigns.required_yes')}</span>
                                                            ) : (
                                                                <span className="text-muted-foreground">{t('pages.campaigns.required_no')}</span>
                                                            )}
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <p className="rounded-md bg-muted/40 px-3 py-2 text-xs text-muted-foreground">
                                {t('pages.campaigns.connect_note')}
                            </p>
                        </CardContent>
                    </Card>
                )}

                {!isEdit && (
                    <Card className="border-dashed">
                        <CardContent className="py-4 text-sm text-muted-foreground">
                            {t('pages.campaigns.connect_save_first')}
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
                                <CurrencyInput
                                    value={data.budget}
                                    onChange={(amount) => setData('budget', amount)}
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
            </div>
        </AppLayout>
    );
}
