import { Head, Link, router } from '@inertiajs/react';
import { CheckCircle2, Clock, Copy, Pencil, Plus, Target, Trash2 } from 'lucide-react';
import { toast } from 'sonner';

import { PageHeader } from '@/components/layout/PageHeader';
import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import { Button } from '@/components/ui/button';
import { useConfirm } from '@/hooks/use-confirm';
import { formatCurrency } from '@/lib/format';
import { copyToClipboard } from '@/lib/clipboard';
import AppLayout from '@/layouts/AppLayout';
import { useT } from '@/providers/I18nProvider';

export default function CampaignIndex({ campaigns }) {
    const t = useT();
    const { ask, ConfirmDialogPortal } = useConfirm();

    const remove = async (id, name) => {
        const ok = await ask({
            title: t('pages.campaigns.marketing_delete_title'),
            description: t('pages.campaigns.marketing_delete_desc', { name }),
            confirmLabel: t('common.delete'),
            variant: 'destructive',
        });
        if (!ok) return;
        router.delete(`/marketing/campaigns/${id}`);
    };

    const copyUrl = async (url) => {
        const ok = await copyToClipboard(url);
        ok ? toast.success(t('pages.campaigns.marketing_copy_success')) : toast.error(t('common.copy_failed'));
    };

    return (
        <AppLayout>
            <Head title={t('pages.campaigns.marketing_title')} />

            <div className="space-y-6">
                <PageHeader
                    title={t('pages.campaigns.marketing_title')}
                    description={t('pages.campaigns.marketing_desc_detail')}
                    actions={
                        <Button asChild>
                            <Link href="/marketing/campaigns/create">
                                <Plus className="size-4" />
                                {t('pages.campaigns.marketing_create')}
                            </Link>
                        </Button>
                    }
                />

                <ScrollDataTable>
                    <table className="w-full min-w-[1100px] border-collapse text-xs">
                        <thead>
                            <tr>
                                <Th>{t('pages.campaigns.col_campaign')}</Th>
                                <Th>{t('pages.campaigns.col_webhook')}</Th>
                                <Th>{t('pages.campaigns.col_utm_code')}</Th>
                                <Th>{t('pages.campaigns.col_product')}</Th>
                                <Th>{t('pages.campaigns.col_orders_revenue')}</Th>
                                <Th>{t('pages.campaigns.col_approval')}</Th>
                                <Th>{t('pages.actions')}</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {campaigns.length ? (
                                campaigns.map((row) => (
                                    <tr key={row.id} className="hover:bg-muted/30">
                                        <Td className="font-medium">{row.name}</Td>
                                        <Td>
                                            {row.webhook_url ? (
                                                <div className="flex max-w-xs items-center gap-1">
                                                    <span className="truncate font-mono text-[10px] text-primary">
                                                        {row.webhook_url}
                                                    </span>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon-sm"
                                                        onClick={() => copyUrl(row.webhook_url)}
                                                    >
                                                        <Copy className="size-3.5" />
                                                    </Button>
                                                </div>
                                            ) : (
                                                '—'
                                            )}
                                        </Td>
                                        <Td className="font-mono">{row.utm_campaign}</Td>
                                        <Td>{row.product ?? '—'}</Td>
                                        <Td className="text-right">
                                            {row.orders_count} / {formatCurrency(row.revenue)}
                                        </Td>
                                        <Td>
                                            {row.is_approved ? (
                                                <span className="inline-flex items-center gap-1 text-emerald-600">
                                                    <CheckCircle2 className="size-3.5" />
                                                    {t('pages.approved')}
                                                </span>
                                            ) : (
                                                <span className="inline-flex items-center gap-1 text-amber-600">
                                                    <Clock className="size-3.5" />
                                                    {t('pages.pending_approval')}
                                                </span>
                                            )}
                                        </Td>
                                        <Td>
                                            <div className="flex gap-1">
                                                <Button variant="outline" size="icon-sm" asChild>
                                                    <Link href={`/marketing/campaigns/${row.id}/edit`}>
                                                        <Pencil className="size-4" />
                                                    </Link>
                                                </Button>
                                                <Button
                                                    variant="outline"
                                                    size="icon-sm"
                                                    className="text-destructive"
                                                    onClick={() => remove(row.id, row.name)}
                                                >
                                                    <Trash2 className="size-4" />
                                                </Button>
                                            </div>
                                        </Td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <Td colSpan={7} className="py-10 text-center text-muted-foreground">
                                        <Target className="mx-auto mb-2 size-6 opacity-50" />
                                        {t('pages.campaigns.marketing_empty')}
                                    </Td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </ScrollDataTable>
            </div>

            <ConfirmDialogPortal />
        </AppLayout>
    );
}
