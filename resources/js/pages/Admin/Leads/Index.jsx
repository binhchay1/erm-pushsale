import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, Info, Plug, Search, UserPlus, X } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

import { DeleteRowButton } from '@/components/ui/delete-row-button';
import { StatusBadge } from '@/components/ui/status-badge';
import { ManualLeadDialogs } from '@/components/leads/ManualLeadDialogs';
import { PageHeader } from '@/components/layout/PageHeader';
import AppLayout from '@/layouts/AppLayout';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import { useRealtimeReload } from '@/hooks/useRealtimeReload';
import { useLabels } from '@/hooks/use-labels';
import { useTableSort } from '@/hooks/use-table-sort';
import { formatCurrency, formatDateTime } from '@/lib/format';
import { leadTone } from '@/lib/status-tones';
import { useT } from '@/providers/I18nProvider';

export default function LeadsIndex({
    leads,
    filters,
    exceptionCount = 0,
    platforms,
    statuses,
    packetTypes = [],
    campaigns = [],
    salesUsers = [],
    allocateUrl = '/admin/leads/allocate',
    deleteUrlPrefix = '/admin/leads',
    listUrl = '/admin/leads',
    canDelete = true,
    canReview = false,
    reviewUrlPrefix = '/admin/leads',
    showAllocationTools = true,
    realtimeChannel = 'dashboard.admin',
    allocationMode = 'auto',
    allocationModeUrl = '/admin/leads/allocation-mode',
    manualUrl = '/admin/leads/manual',
    importUrl = '/admin/leads/import',
    templateUrl = '/admin/leads/import-template',
    products = [],
    importFields = [],
    canManageTemplate = false,
    companyTemplate = null,
    templateUploadUrl = '/admin/company/lead-template',
    templateRemoveUrl = '/admin/company/lead-template',
}) {
    const t = useT();
    const labels = useLabels();
    const pageRows = leads.data ?? [];
    const { sortedRows, sort, toggleSort } = useTableSort(pageRows, { defaultKey: 'created_at', defaultDir: 'desc' });

    useRealtimeReload(realtimeChannel, '.leads.changed', ['leads']);
    useRealtimeReload(realtimeChannel, '.lead.ingested', ['leads']);
    const [selected, setSelected] = useState([]);
    const [saleUserId, setSaleUserId] = useState('');
    const [allocating, setAllocating] = useState(false);
    const [savingMode, setSavingMode] = useState(false);
    const [searchDraft, setSearchDraft] = useState(filters.search ?? '');

    const manualOnly = allocationMode === 'manual';
    const exceptionsOnly = filters.bucket === 'exceptions';

    const viewExceptions = () => {
        router.get(listUrl, { bucket: 'exceptions' }, { preserveState: true });
    };

    const toggleMode = (next) => {
        setSavingMode(true);
        router.post(
            allocationModeUrl,
            { mode: next ? 'manual' : 'auto' },
            {
                preserveScroll: true,
                preserveState: false,
                onSuccess: () =>
                    toast.success(next ? t('pages.leads.mode_manual_on') : t('pages.leads.mode_auto_on')),
                onError: () => toast.error(t('pages.leads.mode_failed')),
                onFinish: () => setSavingMode(false),
            },
        );
    };

    const search = (overrides) => {
        router.get(listUrl, { ...filters, ...overrides }, { preserveState: true });
    };

    const clearFilters = () => {
        setSearchDraft('');
        router.get(listUrl, {}, { preserveState: true });
    };

    const applySearch = () => {
        search({ search: searchDraft.trim() || null });
    };

    const toggleRow = (id) => {
        setSelected((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]));
    };

    const toggleAllPending = () => {
        const pendingOnPage = pageRows.filter((r) => r.status === 'pending' && r.counts_as_lead).map((r) => r.id);
        const allSelected = pendingOnPage.length > 0 && pendingOnPage.every((id) => selected.includes(id));
        setSelected(allSelected ? selected.filter((id) => !pendingOnPage.includes(id)) : [...new Set([...selected, ...pendingOnPage])]);
    };

    const allocate = () => {
        if (!selected.length) {
            toast.error(t('pages.leads.select_pending'));
            return;
        }
        if (!saleUserId) {
            toast.error(t('pages.leads.select_sale'));
            return;
        }

        setAllocating(true);
        router.post(
            allocateUrl,
            { lead_ids: selected, sale_user_id: saleUserId },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSelected([]);
                    setSaleUserId('');
                    toast.success(t('pages.leads.allocate_done'));
                },
                onError: (errors) => {
                    toast.error(errors.lead_ids ?? errors.sale_user_id ?? t('pages.leads.allocate_failed'));
                },
                onFinish: () => setAllocating(false),
            },
        );
    };

    const markReviewed = (row, resolution = 'acknowledge') => {
        const confirmation = resolution === 'merge_original'
            ? t('pages.leads.review_merge_confirm')
            : resolution === 'create_supplemental_order'
              ? t('pages.leads.review_create_confirm')
              : null;

        if (confirmation && !window.confirm(confirmation)) {
            return;
        }

        router.patch(
            `${reviewUrlPrefix}/${row.id}/review`,
            { resolution },
            {
                preserveScroll: true,
                onSuccess: () => toast.success(t('pages.leads.review_done')),
                onError: (errors) => toast.error(errors.resolution ?? t('pages.leads.review_failed')),
            },
        );
    };

    const pendingOnPage = (leads.data ?? []).filter((r) => r.status === 'pending' && r.counts_as_lead);
    const allPendingSelected =
        pendingOnPage.length > 0 && pendingOnPage.every((r) => selected.includes(r.id));

    const selectedSale = salesUsers.find((u) => String(u.id) === String(saleUserId));
    const canAllocate = selected.length > 0 && !!saleUserId && !allocating;
    const leadTableColumns = 10 + (showAllocationTools ? 1 : 0) + (canDelete ? 1 : 0);
    const allocateGuide = canAllocate
        ? t('pages.leads.ready_to_allocate', { count: selected.length, name: selectedSale?.name ?? '' })
        : saleUserId
          ? t('pages.leads.need_pick_leads', { name: selectedSale?.name ?? '' })
          : selected.length
            ? t('pages.leads.need_choose_sale', { count: selected.length })
            : t('pages.leads.allocate_steps');

    return (
        <AppLayout>
            <Head title={t('pages.leads.title')} />

            <div className="space-y-6">
                <PageHeader
                    title={t('pages.leads.title')}
                    description={t('pages.leads.desc_detail')}
                    actions={
                        canDelete && (
                            <Button variant="outline" asChild>
                                <Link href="/admin/integrations">
                                    <Plug className="size-4" />
                                    {t('pages.leads.configure_platforms')}
                                </Link>
                            </Button>
                        )
                    }
                />

                <ManualLeadDialogs
                    manualUrl={manualUrl}
                    importUrl={importUrl}
                    templateUrl={templateUrl}
                    productOptions={products}
                    sources={campaigns}
                    importFields={importFields}
                    canManageTemplate={canManageTemplate}
                    companyTemplate={companyTemplate}
                    templateUploadUrl={templateUploadUrl}
                    templateRemoveUrl={templateRemoveUrl}
                />

                {exceptionCount > 0 && (
                    <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-rose-200/80 bg-rose-50/60 p-4 dark:border-rose-900/40 dark:bg-rose-950/20">
                        <div className="flex items-start gap-3">
                            <span className="mt-0.5 inline-flex size-8 shrink-0 items-center justify-center rounded-full bg-rose-500/15 text-rose-600 dark:text-rose-400">
                                <AlertTriangle className="size-4" />
                            </span>
                            <div>
                                <p className="text-sm font-semibold text-rose-700 dark:text-rose-300">
                                    {t('pages.leads.exceptions_title')}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {t('pages.leads.exceptions_hint', { count: exceptionCount })}
                                </p>
                            </div>
                        </div>
                        {exceptionsOnly ? (
                            <StatusBadge tone="danger">{t('pages.leads.exceptions_only_badge')}</StatusBadge>
                        ) : (
                            <Button size="sm" variant="outline" onClick={viewExceptions}>
                                <AlertTriangle className="size-4" />
                                {t('pages.leads.exceptions_view')}
                            </Button>
                        )}
                    </div>
                )}

                {showAllocationTools && (
                <div className="space-y-3 rounded-xl border border-amber-200/80 bg-amber-50/50 p-4 dark:border-amber-900/40 dark:bg-amber-950/20">
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-amber-200/60 pb-3 dark:border-amber-900/30">
                        <div className="flex items-center gap-3">
                            <Switch
                                id="allocation-mode"
                                checked={manualOnly}
                                onCheckedChange={toggleMode}
                                disabled={savingMode}
                            />
                            <label htmlFor="allocation-mode" className="cursor-pointer select-none">
                                <span className="block text-sm font-medium">{t('pages.leads.mode_toggle_label')}</span>
                                <span className="block text-xs text-muted-foreground">
                                    {manualOnly ? t('pages.leads.mode_manual_hint') : t('pages.leads.mode_auto_hint')}
                                </span>
                            </label>
                        </div>
                        <StatusBadge tone={manualOnly ? 'warning' : 'success'}>
                            {manualOnly ? t('pages.leads.mode_manual_badge') : t('pages.leads.mode_auto_badge')}
                        </StatusBadge>
                    </div>

                    <div className="flex flex-wrap items-end gap-3">
                        <div className="min-w-[200px] flex-1 space-y-1">
                            <p className="flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                                <span className="inline-flex size-4 items-center justify-center rounded-full bg-primary/15 text-[10px] font-bold text-primary">
                                    1
                                </span>
                                {t('pages.leads.step_choose_sale')}
                            </p>
                            <select
                                className="input-soft h-9 w-full max-w-xs px-2"
                                value={saleUserId}
                                onChange={(e) => setSaleUserId(e.target.value)}
                            >
                                <option value="">{t('pages.leads.select_telesale')}</option>
                                {salesUsers.map((u) => (
                                    <option key={u.id} value={u.id}>
                                        {u.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="space-y-1">
                            <p className="flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                                <span className="inline-flex size-4 items-center justify-center rounded-full bg-primary/15 text-[10px] font-bold text-primary">
                                    2
                                </span>
                                {t('pages.leads.step_allocate')}
                            </p>
                            <Button
                                size="sm"
                                onClick={allocate}
                                disabled={!canAllocate}
                                title={canAllocate ? undefined : allocateGuide}
                            >
                                <UserPlus className="size-4" />
                                {t('pages.leads.allocate_btn', { count: selected.length })}
                            </Button>
                        </div>
                    </div>

                    <p
                        className={
                            canAllocate
                                ? 'flex items-center gap-1.5 text-xs font-medium text-emerald-700 dark:text-emerald-400'
                                : 'flex items-center gap-1.5 text-xs text-muted-foreground'
                        }
                    >
                        {canAllocate ? <CheckCircle2 className="size-3.5" /> : <Info className="size-3.5" />}
                        {allocateGuide}
                    </p>
                </div>
                )}

                <div className="flex flex-wrap gap-3 rounded-xl border bg-card p-4">
                    <input
                        className="input-soft h-8 min-w-[200px] flex-1 px-2"
                        placeholder={t('pages.leads.filter_search')}
                        value={searchDraft}
                        onChange={(e) => setSearchDraft(e.target.value)}
                        onKeyDown={(e) => e.key === 'Enter' && applySearch()}
                    />
                    <select
                        className="input-soft h-8 px-2"
                        value={filters.marketing_source_id ?? ''}
                        onChange={(e) => search({ marketing_source_id: e.target.value || null })}
                    >
                        <option value="">{t('pages.leads.filter_campaign')}</option>
                        {campaigns.map((c) => (
                            <option key={c.id} value={c.id}>
                                {c.name}
                            </option>
                        ))}
                    </select>
                    <select
                        className="input-soft h-8 px-2"
                        value={filters.platform ?? ''}
                        onChange={(e) => search({ platform: e.target.value || null })}
                    >
                        <option value="">{t('pages.leads.filter_platform')}</option>
                        {platforms.map((p) => (
                            <option key={p} value={p}>
                                {p}
                            </option>
                        ))}
                    </select>
                    <select
                        className="input-soft h-8 px-2"
                        value={filters.status ?? ''}
                        onChange={(e) => search({ status: e.target.value || null })}
                    >
                        <option value="">{t('pages.leads.filter_status')}</option>
                        {statuses.map((s) => (
                            <option key={s.value} value={s.value}>
                                {s.label}
                            </option>
                        ))}
                    </select>
                    <select
                        className="input-soft h-8 px-2"
                        value={filters.packet_type ?? ''}
                        onChange={(e) => search({ packet_type: e.target.value || null })}
                    >
                        <option value="">{t('pages.leads.filter_packet_type')}</option>
                        {packetTypes.map((type) => (
                            <option key={type.value} value={type.value}>
                                {type.label}
                            </option>
                        ))}
                    </select>
                    <select
                        className="input-soft h-8 px-2"
                        value={filters.review ?? ''}
                        onChange={(e) => search({ review: e.target.value || null })}
                    >
                        <option value="">{t('pages.leads.filter_review')}</option>
                        <option value="pending">{t('pages.leads.review_pending')}</option>
                        <option value="reviewed">{t('pages.leads.reviewed')}</option>
                    </select>
                    <input
                        type="date"
                        className="input-soft h-8 px-2"
                        value={filters.date_from ?? ''}
                        onChange={(e) => search({ date_from: e.target.value || null })}
                        title={t('pages.leads.filter_date_from')}
                    />
                    <input
                        type="date"
                        className="input-soft h-8 px-2"
                        value={filters.date_to ?? ''}
                        onChange={(e) => search({ date_to: e.target.value || null })}
                        title={t('pages.leads.filter_date_to')}
                    />
                    <Button size="sm" variant="outline" onClick={() => search({ status: 'pending', bucket: null })}>
                        {t('pages.leads.filter_pending_only')}
                    </Button>
                    <Button size="sm" onClick={applySearch}>
                        <Search className="size-4" />
                        {t('common.filter')}
                    </Button>
                    <Button size="sm" variant="ghost" onClick={clearFilters}>
                        <X className="size-4" />
                        {t('pages.leads.clear_filters')}
                    </Button>
                </div>

                <ScrollDataTable>
                    <table className="min-w-[2100px] w-full border-collapse text-xs">
                        <thead>
                            <tr>
                                {showAllocationTools && (
                                    <Th className="w-10 text-center">
                                        <input
                                            type="checkbox"
                                            checked={allPendingSelected}
                                            onChange={toggleAllPending}
                                            disabled={!pendingOnPage.length}
                                            title={t('pages.leads.select_all_pending')}
                                        />
                                    </Th>
                                )}
                                <Th sortable sortKey="id" sort={sort} onSort={toggleSort}>#</Th>
                                <Th sortable sortKey="created_at" sort={sort} onSort={toggleSort}>
                                    <div>Nguồn dữ liệu</div>
                                    <div className="mt-0.5 text-xs font-normal">Ngày data về</div>
                                </Th>
                                <Th sortable sortKey="customer_name" sort={sort} onSort={toggleSort}>
                                    <div>Họ tên</div>
                                    <div className="mt-0.5 text-xs font-normal">Số điện thoại</div>
                                </Th>
                                <Th>
                                    <div>Địa chỉ</div>
                                    <div className="mt-0.5 text-xs font-normal">Địa chỉ nhận hàng</div>
                                </Th>
                                <Th>
                                    <div>Tin nhắn</div>
                                    <div className="mt-0.5 text-xs font-normal">Ghi chú khách hàng</div>
                                </Th>
                                <Th>
                                    <div>Sản phẩm</div>
                                    <div className="mt-0.5 text-xs font-normal">Số lượng · Đơn giá</div>
                                </Th>
                                <Th sortable sortKey="sale_name" sort={sort} onSort={toggleSort}>
                                    <div>Sale</div>
                                    <div className="mt-0.5 text-xs font-normal">Ngày nhận data</div>
                                </Th>
                                <Th>
                                    <div>Tác nghiệp</div>
                                    <div className="mt-0.5 text-xs font-normal">Kết quả · Ngày chốt</div>
                                </Th>
                                <Th sortable sortKey="status" sort={sort} onSort={toggleSort}>
                                    <div>Trạng thái lead</div>
                                    <div className="mt-0.5 text-xs font-normal">Mã đơn</div>
                                </Th>
                                <Th>
                                    <div>Lỗi / Ghi chú</div>
                                    <div className="mt-0.5 text-xs font-normal">Thời gian xử lý</div>
                                </Th>
                                {canDelete && <Th />}
                            </tr>
                        </thead>
                        <tbody>
                            {sortedRows.length ? (
                                sortedRows.map((row) => (
                                    <tr
                                        key={row.id}
                                        className={
                                            row.is_exception
                                                ? 'bg-rose-50/50 hover:bg-rose-50 dark:bg-rose-950/20 dark:hover:bg-rose-950/30'
                                                : 'hover:bg-muted/30'
                                        }
                                    >
                                        {showAllocationTools && (
                                            <Td className="text-center">
                                                {row.status === 'pending' && row.counts_as_lead ? (
                                                    <input
                                                        type="checkbox"
                                                        checked={selected.includes(row.id)}
                                                        onChange={() => toggleRow(row.id)}
                                                    />
                                                ) : null}
                                            </Td>
                                        )}
                                        <Td className="text-center font-medium">{row.id}</Td>
                                        <Td className="min-w-52 whitespace-normal text-center">
                                            <div className="font-semibold text-[#2467b5]">{row.campaign_name ?? row.utm_campaign ?? row.platform ?? '—'}</div>
                                            <div className="mt-1 text-[11px] text-muted-foreground">{row.platform || '—'}</div>
                                            <div className="mt-1 flex flex-wrap justify-center gap-1">
                                                <span className={`rounded px-1.5 py-0.5 text-[10px] font-semibold ${row.counts_as_lead ? 'bg-emerald-100 text-emerald-700' : 'bg-sky-100 text-sky-700'}`}>
                                                    {row.packet_type_label}
                                                </span>
                                                {!row.counts_as_lead && (
                                                    <span className="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] text-slate-600">
                                                        {t('pages.leads.not_counted_as_lead')}
                                                    </span>
                                                )}
                                            </div>
                                            <div className="mt-1 text-[11px] text-muted-foreground">{formatDateTime(row.created_at)}</div>
                                        </Td>
                                        <Td className="min-w-48 whitespace-normal">
                                            <div className="font-semibold text-[#2467b5]">{row.customer_name ?? '—'}</div>
                                            <div className="mt-1 font-mono text-[#2467b5]">{row.customer_phone ?? '—'}</div>
                                        </Td>
                                        <Td className="min-w-72 max-w-[340px] whitespace-normal leading-relaxed">
                                            <div>{row.address ?? '—'}</div>
                                            {row.address_inherited && <div className="mt-1 text-[10px] text-muted-foreground">{t('pages.leads.inherited_from_order')}</div>}
                                        </Td>
                                        <Td className="min-w-64 max-w-[320px] whitespace-normal leading-relaxed text-muted-foreground">
                                            <div>{row.message ?? '—'}</div>
                                            {row.message_inherited && <div className="mt-1 text-[10px]">{t('pages.leads.inherited_from_order')}</div>}
                                        </Td>
                                        <Td className="min-w-72 whitespace-normal">
                                            {row.products?.length ? row.products.map((product, index) => (
                                                <div
                                                    key={`${row.id}-${product.name}-${index}`}
                                                    className="flex items-start justify-between gap-3 border-b border-dashed py-1.5 last:border-b-0"
                                                >
                                                    <span className="min-w-0 flex-1 font-medium">{product.name}</span>
                                                    <span className="shrink-0">x{product.quantity}</span>
                                                    <span className="w-20 shrink-0 text-right">
                                                        {Number(product.unit_price) > 0 ? formatCurrency(product.unit_price) : '—'}
                                                    </span>
                                                </div>
                                            )) : (
                                                <span className="text-muted-foreground">{row.incoming ?? '—'}</span>
                                            )}
                                        </Td>
                                        <Td className="min-w-48 whitespace-normal text-center">
                                            <div className="font-semibold">{row.sale_name ?? '—'}</div>
                                            {row.sale_team && <div className="mt-1 text-[11px] text-muted-foreground">{row.sale_team}</div>}
                                            <div className="mt-1 text-[11px] text-muted-foreground">{formatDateTime(row.assigned_at)}</div>
                                        </Td>
                                        <Td className="min-w-52 whitespace-normal text-center">
                                            <div className="font-semibold">{row.operation_stage ?? '—'}</div>
                                            <div className="mt-1 text-muted-foreground">{row.operation_result ?? '—'}</div>
                                            {row.closed_at && (
                                                <div className="mt-1 text-[11px] text-muted-foreground">{formatDateTime(row.closed_at)}</div>
                                            )}
                                        </Td>
                                        <Td className="min-w-44 whitespace-normal text-center">
                                            <StatusBadge tone={leadTone(row.status)}>
                                                {labels.lead_ingestion_status?.[row.status] ?? row.status_label}
                                            </StatusBadge>
                                            <div className="mt-2 font-mono font-semibold">
                                                {row.order_code ?? (row.conflict_order_code
                                                    ? <span className="text-rose-600 dark:text-rose-400" title={t('pages.leads.conflict_order')}>{row.conflict_order_code}</span>
                                                    : '—')}
                                            </div>
                                            {row.order_relation === 'related' && (
                                                <div className="mt-1 text-[10px] text-amber-700">{t('pages.leads.related_order_only')}</div>
                                            )}
                                            {row.reviewed_at && (
                                                <div className="mt-1 text-[10px] text-emerald-700">{t('pages.leads.reviewed')} · {formatDateTime(row.reviewed_at)}</div>
                                            )}
                                        </Td>
                                        <Td
                                            className={`min-w-72 max-w-[360px] whitespace-normal leading-relaxed ${row.is_exception ? 'text-rose-600 dark:text-rose-400' : 'text-muted-foreground'}`}
                                        >
                                            <div>{row.error_message ?? '—'}</div>
                                            {row.processed_at && (
                                                <div className="mt-2 text-[11px]">{formatDateTime(row.processed_at)}</div>
                                            )}
                                            {canReview && row.requires_review && !row.reviewed_at && (
                                                <div className="mt-2 space-y-2">
                                                    <div className="text-[10px] text-muted-foreground">
                                                        {t('pages.leads.review_action_hint')}
                                                    </div>
                                                    <div className="flex flex-wrap gap-1.5">
                                                        {row.can_merge_original && (
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                                onClick={() => markReviewed(row, 'merge_original')}
                                                            >
                                                                {t('pages.leads.merge_original_order')}
                                                            </Button>
                                                        )}
                                                        {row.can_create_supplemental_order && (
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                                onClick={() => markReviewed(row, 'create_supplemental_order')}
                                                            >
                                                                {t('pages.leads.create_supplemental_order')}
                                                            </Button>
                                                        )}
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            onClick={() => markReviewed(row, 'acknowledge')}
                                                        >
                                                            <CheckCircle2 className="size-3.5" />
                                                            {t('pages.leads.mark_reviewed')}
                                                        </Button>
                                                    </div>
                                                </div>
                                            )}
                                        </Td>
                                        {canDelete && (
                                            <Td>
                                                <DeleteRowButton
                                                    url={`${deleteUrlPrefix}/${row.id}`}
                                                    label={`#${row.id}`}
                                                />
                                            </Td>
                                        )}
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <Td colSpan={leadTableColumns} className="py-8 text-center text-muted-foreground">
                                        {t('pages.leads.empty')}
                                    </Td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </ScrollDataTable>

                {leads.links?.length > 3 && (
                    <div className="flex flex-wrap gap-2">
                        {leads.links.map((link) => (
                            <Button
                                key={link.label}
                                variant={link.active ? 'default' : 'outline'}
                                size="sm"
                                disabled={!link.url}
                                onClick={() => link.url && router.get(link.url)}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
