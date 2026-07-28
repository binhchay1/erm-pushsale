import { useEffect, useState } from 'react';
import { CheckCircle2, Copy, XCircle } from 'lucide-react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { StatusBadge } from '@/components/ui/status-badge';
import { copyToClipboard } from '@/lib/clipboard';
import { formatCurrency } from '@/lib/format';
import { cn } from '@/lib/utils';
import { useT } from '@/providers/I18nProvider';

function DetailItem({ label, children, className }) {
    return (
        <div className={cn('space-y-1', className)}>
            <p className="text-xs font-medium text-muted-foreground">{label}</p>
            <div className="text-sm">{children ?? '—'}</div>
        </div>
    );
}

export function CampaignApprovalDetailDialog({
    campaign,
    open,
    onOpenChange,
    fieldMapping,
    products = [],
    onApprove,
    onReject,
    approving = false,
    rejecting = false,
}) {
    const t = useT();
    const [rejectOpen, setRejectOpen] = useState(false);
    const [reason, setReason] = useState('');
    const [pickedProductId, setPickedProductId] = useState('');

    useEffect(() => {
        setPickedProductId('');
    }, [campaign?.id]);

    const copyUrl = async (url) => {
        const ok = await copyToClipboard(url);
        ok ? toast.success(t('pages.landing.copy_url_success')) : toast.error(t('pages.landing.copy_url_failed'));
    };

    const submitReject = () => {
        if (!campaign || !reason.trim()) return;
        onReject?.(campaign, reason.trim());
        setRejectOpen(false);
        setReason('');
    };

    if (!campaign) {
        return null;
    }

    const canDecide = !campaign.is_approved && !campaign.rejected_at;
    const missingProduct = !!campaign.missing_product && !campaign.is_approved;

    return (
        <>
            <Dialog open={open} onOpenChange={onOpenChange}>
                <DialogContent className="max-h-[90vh] max-w-2xl overflow-y-auto">
                    <DialogHeader>
                        <div className="flex flex-wrap items-start justify-between gap-3 pr-6">
                            <div className="space-y-1">
                                <DialogTitle>{campaign.name}</DialogTitle>
                                <DialogDescription>{t('pages.landing.detail_desc')}</DialogDescription>
                            </div>
                            {campaign.is_approved ? (
                                <StatusBadge tone="success">{t('pages.approved')}</StatusBadge>
                            ) : campaign.rejected_at ? (
                                <StatusBadge tone="destructive">{t('pages.landing.reject')}</StatusBadge>
                            ) : (
                                <StatusBadge tone="warning">{t('pages.pending_approval')}</StatusBadge>
                            )}
                        </div>
                    </DialogHeader>

                    <div className="space-y-5">
                        {missingProduct && (
                            <div className="space-y-2 rounded-lg border border-amber-200/80 bg-amber-50/60 p-3 text-xs text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/20 dark:text-amber-200">
                                <div className="flex items-start gap-2">
                                    <XCircle className="mt-0.5 size-4 shrink-0" />
                                    <span>Chưa gắn sản phẩm/gói — vẫn duyệt được. Có thể chọn sau hoặc chọn ngay bên dưới.</span>
                                </div>
                                <div className="space-y-1">
                                    <label className="font-medium text-foreground">{t('pages.landing.pick_product_label')}</label>
                                    <select
                                        className="input-soft h-9 w-full px-2 text-sm text-foreground"
                                        value={pickedProductId}
                                        onChange={(e) => setPickedProductId(e.target.value)}
                                    >
                                        <option value="">{t('pages.landing.pick_product_placeholder')}</option>
                                        {products.map((p) => (
                                            <option key={p.id} value={p.id}>
                                                {p.name}
                                                {p.sku ? ` (${p.sku})` : ''}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            </div>
                        )}

                        <section className="space-y-3">
                            <p className="text-sm font-semibold">{t('pages.landing.section_campaign')}</p>
                            <div className="grid gap-4 rounded-lg border p-4 sm:grid-cols-2">
                                <DetailItem label={t('pages.landing.col_creator')}>
                                    <span className="font-medium">{campaign.creator ?? '—'}</span>
                                </DetailItem>
                                <DetailItem label={t('pages.landing.col_marketer')}>
                                    <span className="font-medium">{campaign.marketer ?? '—'}</span>
                                </DetailItem>
                                <DetailItem label={t('pages.landing.col_created')}>{campaign.created_at}</DetailItem>
                                <DetailItem label={t('pages.landing.ad_channel')}>
                                    <span className="font-mono text-xs">{campaign.ad_channel ?? '—'}</span>
                                </DetailItem>
                                <DetailItem label={t('pages.landing.receiving')}>
                                    {campaign.is_active ? (
                                        <StatusBadge tone="success">{t('pages.landing.receiving_yes')}</StatusBadge>
                                    ) : (
                                        <StatusBadge tone="muted">{t('pages.landing.receiving_no')}</StatusBadge>
                                    )}
                                </DetailItem>
                                <DetailItem label={t('pages.landing.budget_label')}>
                                    <span className="font-semibold tabular-nums">
                                        {formatCurrency(campaign.budget)}
                                    </span>
                                </DetailItem>
                                {campaign.approved_by && (
                                    <>
                                        <DetailItem label={t('pages.landing.approved_by')}>{campaign.approved_by}</DetailItem>
                                        <DetailItem label={t('pages.landing.approved_at')}>{campaign.approved_at}</DetailItem>
                                    </>
                                )}
                                {campaign.rejected_by && (
                                    <>
                                        <DetailItem label={t('pages.landing.rejected_by')}>{campaign.rejected_by}</DetailItem>
                                        <DetailItem label={t('pages.landing.rejected_at')}>{campaign.rejected_at}</DetailItem>
                                        <DetailItem label={t('pages.landing.rejection_reason')} className="sm:col-span-2">
                                            {campaign.rejection_reason}
                                        </DetailItem>
                                    </>
                                )}
                            </div>
                        </section>

                        <section className="space-y-3">
                            <p className="text-sm font-semibold">{t('pages.landing.section_product')}</p>
                            <div className="grid gap-4 rounded-lg border p-4 sm:grid-cols-2">
                                <DetailItem label={t('pages.campaigns.product')}>
                                    <span className="font-medium">{campaign.product ?? '—'}</span>
                                </DetailItem>
                                <DetailItem label={t('pages.landing.product_sku')}>
                                    <span className="font-mono text-xs">{campaign.product_sku ?? '—'}</span>
                                </DetailItem>
                                <DetailItem label={t('pages.landing.product_price')} className="sm:col-span-2">
                                    <span className="text-base font-semibold tabular-nums text-primary">
                                        {campaign.product_unit_price
                                            ? formatCurrency(campaign.product_unit_price)
                                            : '—'}
                                    </span>
                                </DetailItem>
                            </div>
                        </section>

                        <section className="space-y-3">
                            <p className="text-sm font-semibold">{t('pages.landing.section_tracking')}</p>
                            <div className="grid gap-4 rounded-lg border p-4 sm:grid-cols-2">
                                <DetailItem label="utm_campaign">
                                    <span className="font-mono text-xs">{campaign.utm_campaign ?? '—'}</span>
                                </DetailItem>
                                <DetailItem label={t('pages.landing.utm_source')}>
                                    <span className="font-mono text-xs">{campaign.utm_source ?? '—'}</span>
                                </DetailItem>
                                <DetailItem label={t('pages.landing.webhook_label')} className="sm:col-span-2">
                                    {campaign.webhook_url ? (
                                        <div className="flex gap-2">
                                            <code className="block min-w-0 flex-1 truncate rounded bg-muted px-2 py-1.5 text-[11px]">
                                                {campaign.webhook_url}
                                            </code>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="icon-sm"
                                                onClick={() => copyUrl(campaign.webhook_url)}
                                            >
                                                <Copy className="size-3.5" />
                                            </Button>
                                        </div>
                                    ) : (
                                        '—'
                                    )}
                                </DetailItem>
                            </div>
                        </section>

                        {fieldMapping?.length > 0 && (
                            <section className="space-y-3">
                                <div>
                                    <p className="text-sm font-semibold">{t('pages.campaigns.ladipage_map')}</p>
                                    <p className="text-xs text-muted-foreground">
                                        {t('pages.campaigns.ladipage_map_desc')}
                                    </p>
                                </div>
                                <div className="overflow-hidden rounded-lg border">
                                    <table className="w-full text-xs">
                                        <thead>
                                            <tr className="border-b bg-muted/40 text-left text-muted-foreground">
                                                <th className="px-3 py-2">{t('pages.campaigns.ladipage_col')}</th>
                                                <th className="px-3 py-2">{t('pages.campaigns.system_col')}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {fieldMapping.map((row) => (
                                                <tr key={row.ladipage} className="border-b border-border/50 last:border-0">
                                                    <td className="px-3 py-2 font-mono">{row.ladipage}</td>
                                                    <td className="px-3 py-2">{row.system}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </section>
                        )}
                    </div>

                    <DialogFooter className="gap-2 sm:gap-0">
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            {t('common.close')}
                        </Button>
                        {canDecide && (
                            <>
                                <Button
                                    type="button"
                                    variant="destructive"
                                    disabled={rejecting}
                                    onClick={() => setRejectOpen(true)}
                                >
                                    <XCircle className="size-4" />
                                    {t('pages.landing.reject')}
                                </Button>
                                <Button
                                    type="button"
                                    disabled={approving}
                                    onClick={() => onApprove(campaign, pickedProductId ? Number(pickedProductId) : null)}
                                >
                                    <CheckCircle2 className="size-4" />
                                    {t('pages.landing.approve')}
                                </Button>
                            </>
                        )}
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={rejectOpen} onOpenChange={setRejectOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('pages.landing.reject_title')}</DialogTitle>
                        <DialogDescription>{t('pages.landing.reject_desc', { name: campaign.name })}</DialogDescription>
                    </DialogHeader>
                    <textarea
                        className="min-h-24 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        value={reason}
                        onChange={(e) => setReason(e.target.value)}
                        placeholder={t('pages.landing.reject_reason_placeholder')}
                        rows={4}
                    />
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setRejectOpen(false)}>
                            {t('common.cancel')}
                        </Button>
                        <Button type="button" variant="destructive" disabled={!reason.trim() || rejecting} onClick={submitReject}>
                            {t('pages.landing.reject')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
