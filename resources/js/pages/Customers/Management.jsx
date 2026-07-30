import { Head, router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

import { CareCampaignFormDialog, SelectBox } from '@/components/customers/CareCampaignDialogs';
import { CustomerSegmentDialog } from '@/components/customers/CustomerSegmentDialog';
import { PushsalePageShell } from '@/components/layout/PushsalePageShell';
import { PushsaleDialog } from '@/components/ui/pushsale-dialog';
import { PushsalePagination } from '@/components/pagination/PushsalePagination';
import AppLayout from '@/layouts/AppLayout';
import { apiRequest } from '@/lib/api';
import { useT } from '@/providers/I18nProvider';

function toDateRangeLabel(filters) {
    const from = filters?.date_from ? String(filters.date_from).split('-').reverse().join('/') : '';
    const to = filters?.date_to ? String(filters.date_to).split('-').reverse().join('/') : '';
    return `${from} 00:00 - ${to} 23:59`.trim();
}

function parseDateRange(value) {
    const matches = String(value ?? '').match(/(\d{1,2})\/(\d{1,2})\/(\d{4}).*?(\d{1,2})\/(\d{1,2})\/(\d{4})/);
    if (!matches) return null;
    const [, fd, fm, fy, td, tm, ty] = matches;
    return {
        date_from: `${fy}-${String(fm).padStart(2, '0')}-${String(fd).padStart(2, '0')}`,
        date_to: `${ty}-${String(tm).padStart(2, '0')}-${String(td).padStart(2, '0')}`,
    };
}

function Customer360Table({ rows, selected, setSelected, onAdd, onAttachOne }) {
    const t = useT();
    const c = (key, params = {}) => t(`pages.customer360.${key}`, params);
    const allSelected = rows.length > 0 && rows.every((row) => selected.has(String(row.id)));

    const toggleAll = (checked) => {
        setSelected((current) => {
            const next = new Set(current);
            rows.forEach((row) => (checked ? next.add(String(row.id)) : next.delete(String(row.id))));
            return next;
        });
    };

    const toggleOne = (id, checked) => {
        setSelected((current) => {
            const next = new Set(current);
            checked ? next.add(String(id)) : next.delete(String(id));
            return next;
        });
    };

    return (
        <div id="customer360-table" className="ps-customer360-table-wrap">
            <table className="table table-bordered table-multi-select table-sale ps-customer360-table">
                <thead>
                    <tr>
                        <th className="text-center ps-col-check">
                            <span className="chk-all">
                                <input id="customer360-check-all" type="checkbox" checked={allSelected} onChange={(event) => toggleAll(event.target.checked)} />
                                <label htmlFor="customer360-check-all">&nbsp;</label>
                            </span>
                        </th>
                        <th className="text-center">{c('col_sale')}</th>
                        <th className="text-center">{c('col_marketing')}</th>
                        <th className="text-center">{c('col_customer_code')}</th>
                        <th className="text-center">{c('col_customer_name')}</th>
                        <th className="text-center">{c('col_age')}</th>
                        <th className="text-center">{c('col_phone')}</th>
                        <th className="text-center">{c('col_gender')}</th>
                        <th className="text-center">{c('col_message')}</th>
                        <th className="text-center">{c('col_created_at')}</th>
                        <th className="text-center">{c('col_updated_at')}</th>
                        <th className="text-center no-wrap">
                            <button type="button" className="btn-icon ps-customer360-add" title={c('add_customer')} onClick={onAdd}>
                                <i className="fa fa-plus" /> <span className="text">{c('add')}</span>
                            </button>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {rows.length ? rows.map((row) => {
                        const checked = selected.has(String(row.id));
                        return (
                            <tr key={row.id} className={checked ? 'ps-row-selected' : ''}>
                                <td className="text-center">
                                    <span className="chk-item">
                                        <input id={`customer360-row-${row.id}`} type="checkbox" checked={checked} onChange={(event) => toggleOne(row.id, event.target.checked)} />
                                        <label htmlFor={`customer360-row-${row.id}`}>&nbsp;</label>
                                    </span>
                                </td>
                                <td>{row.saleName ?? '—'}{row.saleEmail ? <><br /><span className="small-tip">({row.saleEmail})</span></> : null}</td>
                                <td>{row.marketingName ?? '—'}{row.marketingEmail ? <><br /><span className="small-tip">({row.marketingEmail})</span></> : null}</td>
                                <td className="text-center"><a href={`/admin/customers?search=${encodeURIComponent(row.customerPhone ?? row.customerCode ?? '')}`}>{row.customerCode}</a></td>
                                <td>{row.customerName ?? '—'}</td>
                                <td className="text-center">{row.age ?? ''}</td>
                                <td className="text-center"><a href={`/admin/customers?search=${encodeURIComponent(row.customerPhone ?? '')}`}>{row.customerPhone ?? '—'}</a></td>
                                <td className="text-center">{row.gender ?? ''}</td>
                                <td>{row.message ?? ''}</td>
                                <td className="text-center no-wrap">{row.createdAt ?? ''}</td>
                                <td className="text-center no-wrap">{row.updatedAt ?? ''}</td>
                                <td className="text-center ps-customer360-row-actions">
                                    <a href={`/admin/customers?search=${encodeURIComponent(row.customerPhone ?? '')}`} title={c('open_customer_profile')}><i className="fa fa-external-link" /></a>
                                    <button type="button" title={c('add_to_campaign')} onClick={() => onAttachOne(row.id)}>
                                        <i className="fa fa-user-plus" />
                                    </button>
                                </td>
                            </tr>
                        );
                    }) : (
                        <tr>
                            <td colSpan={12} className="text-center">Không có khách hàng trong bộ lọc</td>
                        </tr>
                    )}
                </tbody>
            </table>
        </div>
    );
}

export default function Customer360Management({
    filters = {},
    filterOptions = {},
    report,
    routeUrl = '/admin/customer-management',
    pageTitle = 'Khách hàng 360',
}) {
    const t = useT();
    const c = (key, params = {}) => t(`pages.customer360.${key}`, params);
    const resolvedPageTitle = c('title') || pageTitle;
    const rows = report?.rows?.data ?? [];
    const pagination = report?.rows?.meta ?? { current_page: 1, last_page: 1, per_page: 20, total: 0, from: 0, to: 0 };
    const [form, setForm] = useState({ ...filters, keyword: filters.search ?? '' });
    const [dateRange, setDateRange] = useState(toDateRangeLabel(filters));
    const [selected, setSelected] = useState(new Set());
    const [dialog, setDialog] = useState(null);
    const [campaignId, setCampaignId] = useState('');
    const [segments, setSegments] = useState(filterOptions.segments ?? []);
    const [processing, setProcessing] = useState(false);
    const [recalcStatus, setRecalcStatus] = useState(null);

    useEffect(() => setSelected(new Set()), [rows.map((row) => row.id).join(',')]);
    useEffect(() => setSegments(filterOptions.segments ?? []), [filterOptions.segments]);

    const selectedIds = useMemo(() => [...selected].map((id) => Number(id)).filter(Boolean), [selected]);
    const setField = (name, value) => setForm((current) => ({ ...current, [name]: value, page: 1 }));

    const normalizedForm = () => {
        const parsed = parseDateRange(dateRange);
        return {
            ...form,
            ...(parsed ?? {}),
            search: form.keyword ?? form.search ?? '',
            page: 1,
        };
    };

    const search = () => router.get(routeUrl, normalizedForm(), { preserveState: true, preserveScroll: true, replace: true });
    const exportCsv = () => {
        const query = new URLSearchParams(normalizedForm());
        selectedIds.forEach((id) => query.append('ids[]', String(id)));
        window.location.assign(`${routeUrl}/export?${query.toString()}`);
    };

    const postJson = async (url, body, successMessage, method = 'POST') => {
        setProcessing(true);
        try {
            const data = await apiRequest(url, { method, body });
            toast.success(data.message ?? successMessage);
            setDialog(null);
            router.reload({ preserveScroll: true });
            return data;
        } catch (error) {
            toast.error(error.message ?? c('action_failed'));
            return null;
        } finally {
            setProcessing(false);
        }
    };

    const createCampaign = (payload) => {
        if (!String(payload.name ?? '').trim()) {
            toast.warning(c('enter_campaign_name'));
            return;
        }
        postJson(`${routeUrl}/campaigns`, {
            name: payload.name.trim(),
            starts_at: payload.starts_at || null,
            ends_at: payload.ends_at || null,
            status: payload.status || 'active',
            filters: { ...normalizedForm(), ...(payload.filters ?? {}) },
            customer_ids: selectedIds,
        }, c('created_campaign'));
    };

    const attachCampaign = () => {
        if (!campaignId) {
            toast.warning(c('select_campaign_warning'));
            return;
        }
        if (!selectedIds.length) {
            toast.warning(c('select_customer_warning'));
            return;
        }
        postJson(`${routeUrl}/campaigns/attach`, { campaign_id: Number(campaignId), customer_ids: selectedIds }, c('attached_customers'));
    };

    const saveSegments = (nextSegments) => {
        const clean = nextSegments
            .map((segment) => ({
                name: String(segment.name ?? '').trim(),
                color: segment.color ?? '#337ab7',
                min_successful_order_value: Number(segment.min_successful_order_value ?? 0),
            }))
            .filter((segment) => segment.name);
        if (!clean.length) {
            toast.warning(c('segment_required'));
            return;
        }
        postJson(`${routeUrl}/segments`, { segments: clean }, c('saved_segments'), 'PUT');
    };

    const recalculateSegments = async () => {
        const data = await postJson(`${routeUrl}/segments/recalculate`, { sync: true }, 'Đã tính toán phân loại khách hàng');
        if (data) {
            setRecalcStatus(`Đã phân loại ${data.assigned ?? 0}/${data.phones ?? 0} khách`);
        }
    };

    return (
        <AppLayout>
            <Head title={resolvedPageTitle} />
            <PushsalePageShell
                title={resolvedPageTitle}
                pageCode="3.1"
                className="ps-customer360-page"
                filters={(
                    <>
                        <input
                            className="form-control date-range"
                            value={dateRange}
                            onChange={(event) => setDateRange(event.target.value)}
                            onBlur={() => setForm((current) => ({ ...current, ...(parseDateRange(dateRange) ?? {}) }))}
                        />
                        <SelectBox value={form.campaign_id} onChange={(value) => setField('campaign_id', value)} options={filterOptions.campaigns} placeholder={c('select_campaign')} />
                        <input
                            className="form-control"
                            value={form.keyword ?? ''}
                            placeholder={c('keyword_placeholder')}
                            onChange={(event) => setField('keyword', event.target.value)}
                            onKeyDown={(event) => event.key === 'Enter' && search()}
                        />
                    </>
                )}
                actions={(
                    <>
                        <button type="button" className="btn btn-sm btn-primary" onClick={search}><i className="fa fa-search" /> {c('search')}</button>
                        <button type="button" className="btn btn-sm btn-primary" onClick={exportCsv}><i className="fa fa-file-excel-o" /> {c('export_excel')}</button>
                    </>
                )}
                advancedFilters={(
                    <div className="ps-adv-filter-panel">
                        <div className="ps-adv-filter-row ps-customer360-filter-grid" style={{ '--ps-adv-cols': 5 }}>
                            <SelectBox value={form.sale_id} onChange={(value) => setField('sale_id', value)} options={filterOptions.sales} placeholder={c('select_sale')} />
                            <SelectBox value={form.marketer_id} onChange={(value) => setField('marketer_id', value)} options={filterOptions.marketers} placeholder={c('select_marketing')} />
                            <SelectBox value={form.customer_type} onChange={(value) => setField('customer_type', value)} options={filterOptions.customerTypes} placeholder={c('purchase_times')} />
                            <SelectBox value={form.segment_id} onChange={(value) => setField('segment_id', value)} options={segments.map((segment) => ({ value: segment.id, label: segment.name }))} placeholder={c('customer_segment')} />
                            <SelectBox value={form.gender} onChange={(value) => setField('gender', value)} options={[{ value: 'male', label: c('male') }, { value: 'female', label: c('female') }]} placeholder={c('gender_placeholder')} />
                            <SelectBox value={form.per_page} onChange={(value) => setField('per_page', value)} options={[20, 50, 100].map((value) => ({ value, label: value }))} placeholder="20" />
                        </div>
                    </div>
                )}
                toolbar={(
                    <div className="ps-customer360-actions">
                        <button type="button" className="btn btn-sm btn-primary" onClick={() => setDialog('createCampaign')}><i className="fa fa-plus" /> {c('create_campaign_from_filter')}</button>
                        <button type="button" className="btn btn-sm btn-primary" onClick={() => setDialog('attachCampaign')}><i className="fa fa-user-plus" /> {c('attach_customers_to_campaign')}</button>
                        <button type="button" className="btn btn-sm btn-primary" onClick={() => setDialog('segments')}><i className="fa fa-diamond" /> {c('manage_segments')}</button>
                    </div>
                )}
            >
                <Customer360Table
                    rows={rows}
                    selected={selected}
                    setSelected={setSelected}
                    onAdd={() => setDialog('createCustomer')}
                    onAttachOne={(id) => {
                        setSelected(new Set([String(id)]));
                        setDialog('attachCampaign');
                    }}
                />
                <PushsalePagination routeUrl={routeUrl} filters={normalizedForm()} meta={pagination} scrollTargetId="customer360-table" />
            </PushsalePageShell>

            <CareCampaignFormDialog
                open={dialog === 'createCampaign'}
                onClose={() => setDialog(null)}
                onSave={createCampaign}
                filterOptions={{
                    ...filterOptions,
                    segments,
                    customerTypes: filterOptions.customerTypes ?? [
                        { value: 'new', label: 'Khách mới' },
                        { value: 'returning', label: 'Khách mua lại' },
                    ],
                }}
                processing={processing}
            />

            <PushsaleDialog
                open={dialog === 'attachCampaign'}
                onOpenChange={(next) => !next && setDialog(null)}
                title={c('attach_campaign_dialog_title')}
                width="560px"
                bodyClassName="ps-dialog-body"
                footer={(
                    <>
                        <button type="button" className="btn btn-default" onClick={() => setDialog(null)}>{c('close')}</button>
                        <button type="button" className="btn btn-primary" disabled={processing} onClick={attachCampaign}><i className="fa fa-save" /> {c('save')}</button>
                    </>
                )}
            >
                <label>{c('campaign')}</label>
                <SelectBox value={campaignId} onChange={setCampaignId} options={filterOptions.campaigns} placeholder={c('select_campaign_short')} />
                <p className="small-tip mt-2">{c('selected_customers', { count: selectedIds.length })}</p>
            </PushsaleDialog>

            <CustomerSegmentDialog
                open={dialog === 'segments'}
                onClose={() => setDialog(null)}
                segments={segments}
                onSave={saveSegments}
                onRecalculate={recalculateSegments}
                processing={processing}
                recalcStatus={recalcStatus}
            />

            <PushsaleDialog
                open={dialog === 'createCustomer'}
                onOpenChange={(next) => !next && setDialog(null)}
                title={c('add_customer')}
                width="520px"
                bodyClassName="ps-dialog-body"
                footer={<button type="button" className="btn btn-default" onClick={() => setDialog(null)}>{c('close')}</button>}
            >
                <p>{c('create_customer_hint')}</p>
            </PushsaleDialog>
        </AppLayout>
    );
}
