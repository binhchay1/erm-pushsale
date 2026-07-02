import { Head, Link, router } from '@inertiajs/react';
import { CheckCircle2, Info, Plug, Search, UserPlus, X } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

import { DeleteRowButton } from '@/components/ui/delete-row-button';
import { StatusBadge } from '@/components/ui/status-badge';
import AppLayout from '@/layouts/AppLayout';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import { useRealtimeReload } from '@/hooks/useRealtimeReload';
import { useLabels } from '@/hooks/use-labels';
import { useTableSort } from '@/hooks/use-table-sort';
import { leadTone } from '@/lib/status-tones';
import { useT } from '@/providers/I18nProvider';

export default function LeadsIndex({
    leads,
    filters,
    platforms,
    statuses,
    campaigns = [],
    salesUsers = [],
    allocateUrl = '/admin/leads/allocate',
    deleteUrlPrefix = '/admin/leads',
    listUrl = '/admin/leads',
    canDelete = true,
    realtimeChannel = 'dashboard.admin',
    allocationMode = 'auto',
    allocationModeUrl = '/admin/leads/allocation-mode',
}) {
    const t = useT();
    const labels = useLabels();
    const pageRows = leads.data ?? [];
    const { sortedRows, sort, toggleSort } = useTableSort(pageRows, { defaultKey: 'created_at', defaultDir: 'desc' });

    useRealtimeReload(realtimeChannel, '.leads.changed', ['leads']);
    const [selected, setSelected] = useState([]);
    const [saleUserId, setSaleUserId] = useState('');
    const [allocating, setAllocating] = useState(false);
    const [savingMode, setSavingMode] = useState(false);
    const [searchDraft, setSearchDraft] = useState(filters.search ?? '');

    const manualOnly = allocationMode === 'manual';

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
        const pendingOnPage = pageRows.filter((r) => r.status === 'pending').map((r) => r.id);
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

    const pendingOnPage = (leads.data ?? []).filter((r) => r.status === 'pending');
    const allPendingSelected =
        pendingOnPage.length > 0 && pendingOnPage.every((r) => selected.includes(r.id));

    const selectedSale = salesUsers.find((u) => String(u.id) === String(saleUserId));
    const canAllocate = selected.length > 0 && !!saleUserId && !allocating;
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
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">{t('pages.leads.title')}</h1>
                        <p className="text-sm text-muted-foreground">{t('pages.leads.desc_detail')}</p>
                    </div>
                    {canDelete && (
                        <Button variant="outline" asChild>
                            <Link href="/admin/integrations">
                                <Plug className="size-4" />
                                {t('pages.leads.configure_platforms')}
                            </Link>
                        </Button>
                    )}
                </div>

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
                    <Button size="sm" variant="outline" onClick={() => search({ status: 'pending' })}>
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
                    <table className="w-full border-collapse text-xs">
                        <thead>
                            <tr>
                                <Th className="w-10">
                                    <input
                                        type="checkbox"
                                        checked={allPendingSelected}
                                        onChange={toggleAllPending}
                                        disabled={!pendingOnPage.length}
                                        title={t('pages.leads.select_all_pending')}
                                    />
                                </Th>
                                <Th sortable sortKey="id" sort={sort} onSort={toggleSort}>{t('pages.leads.col_id')}</Th>
                                <Th sortable sortKey="created_at" sort={sort} onSort={toggleSort}>{t('pages.leads.col_time')}</Th>
                                <Th sortable sortKey="platform" sort={sort} onSort={toggleSort}>{t('pages.leads.col_platform')}</Th>
                                <Th sortable sortKey="campaign_name" sort={sort} onSort={toggleSort}>{t('pages.leads.col_campaign')}</Th>
                                <Th sortable sortKey="customer_name" sort={sort} onSort={toggleSort}>{t('pages.leads.col_customer')}</Th>
                                <Th sortable sortKey="customer_phone" sort={sort} onSort={toggleSort}>{t('pages.leads.col_phone')}</Th>
                                <Th sortable sortKey="status" sort={sort} onSort={toggleSort}>{t('pages.leads.col_status')}</Th>
                                <Th sortable sortKey="order_code" sort={sort} onSort={toggleSort}>{t('pages.leads.col_order')}</Th>
                                <Th sortable sortKey="note" sort={sort} onSort={toggleSort}>{t('pages.leads.col_note')}</Th>
                                {canDelete && <Th />}
                            </tr>
                        </thead>
                        <tbody>
                            {sortedRows.length ? (
                                sortedRows.map((row) => (
                                    <tr key={row.id} className="hover:bg-muted/30">
                                        <Td>
                                            {row.status === 'pending' ? (
                                                <input
                                                    type="checkbox"
                                                    checked={selected.includes(row.id)}
                                                    onChange={() => toggleRow(row.id)}
                                                />
                                            ) : null}
                                        </Td>
                                        <Td>{row.id}</Td>
                                        <Td>{row.created_at}</Td>
                                        <Td className="font-medium">{row.platform}</Td>
                                        <Td>{row.campaign_name ?? row.utm_campaign ?? '—'}</Td>
                                        <Td>{row.customer_name ?? '—'}</Td>
                                        <Td className="font-mono">{row.customer_phone ?? '—'}</Td>
                                        <Td>
                                            <StatusBadge tone={leadTone(row.status)}>
                                                {labels.lead_ingestion_status?.[row.status] ?? row.status_label}
                                            </StatusBadge>
                                        </Td>
                                        <Td className="font-mono">{row.order_code ?? '—'}</Td>
                                        <Td className="max-w-xs truncate text-muted-foreground">
                                            {row.error_message ?? row.product_interest ?? '—'}
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
                                    <Td
                                        colSpan={canDelete ? 11 : 10}
                                        className="py-8 text-center text-muted-foreground"
                                    >
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
