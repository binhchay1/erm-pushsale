import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';

import AppLayout from '@/layouts/AppLayout';
import { PushsaleSelect, PushsaleMultiSelect } from '@/components/pushsale/PushsaleSelect';
import { PushsaleDialog } from '@/components/ui/pushsale-dialog';

const channelLabels = {
    facebook_ads: 'Facebook ads',
    youtube: 'Youtube',
    google_ads: 'Google ads',
    tiktok_ads: 'Tiktok Ads',
    zalo_ads: 'Zalo ads',
    hotline: 'Hotline',
    seo: 'SEO',
    affiliate: 'Affiliate',
    other: 'Kênh khác',
};

const budgetTypeOptions = [
    { value: 'total', label: 'Ngân sách tổng' },
    { value: 'daily', label: 'Ngân sách/ngày' },
];

const formatMoney = (value) => new Intl.NumberFormat('vi-VN').format(Number(value || 0));

function defaultDates(row = {}) {
    const pad = (value) => String(value).padStart(2, '0');
    const start = new Date();
    const end = new Date();
    end.setDate(end.getDate() + 29);
    return {
        start: row.budget_start_date || `${start.getFullYear()}-${pad(start.getMonth() + 1)}-${pad(start.getDate())}`,
        end: row.budget_end_date || `${end.getFullYear()}-${pad(end.getMonth() + 1)}-${pad(end.getDate())}`,
    };
}

function ApprovalDialog({ open, onClose, children }) {
    return (
        <PushsaleDialog
            open={Boolean(open)}
            onOpenChange={(nextOpen) => !nextOpen && onClose?.()}
            title="DUYỆT KẾT NỐI DỮ LIỆU"
            width="min(1280px, calc(100vw - 32px))"
            className="pslc-dialog pslc-approval-dialog"
            bodyClassName="pslc-dialog-shell"
        >
            {children}
        </PushsaleDialog>
    );
}

export default function LandingApprovalPage({
    campaigns = [],
    products = [],
    approveBaseUrl = '/admin/marketing/landing-approvals',
    activeMenuCode = '2.4.3',
}) {
    const [selected, setSelected] = useState(null);
    const [status, setStatus] = useState('pending');
    const [search, setSearch] = useState('');
    const form = useForm({
        product_ids: [],
        budget_type: 'total',
        budget_amount: 0,
        budget_start_date: '',
        budget_end_date: '',
    });

    const productOptions = useMemo(() => products.map((product) => ({
        value: String(product.id),
        label: product.name,
        subLabel: `${product.sku || ''}${product.type === 'combo' ? ' · Gói sản phẩm' : ''}`.trim(),
    })), [products]);

    const rows = useMemo(() => {
        const needle = String(search || '').toLowerCase().trim();
        return campaigns.filter((row) => {
            if (status === 'pending' && (row.is_approved || row.rejected_at)) return false;
            if (status === 'approved' && !row.is_approved) return false;
            if (status === 'rejected' && !row.rejected_at) return false;
            if (!needle) return true;
            return `${row.name || ''} ${row.marketer || ''} ${row.source_url || ''}`.toLowerCase().includes(needle);
        });
    }, [campaigns, search, status]);

    const openApprove = (row) => {
        const dates = defaultDates(row);
        setSelected(row);
        form.setData({
            product_ids: (row.product_ids || []).map(Number),
            budget_type: row.budget_type || 'total',
            budget_amount: Number(row.budget || 0),
            budget_start_date: dates.start,
            budget_end_date: dates.end,
        });
        form.clearErrors();
    };

    const approve = (event) => {
        event.preventDefault();
        if (!selected) return;
        form.post(`${approveBaseUrl}/${selected.id}/approve`, {
            preserveScroll: true,
            onSuccess: () => setSelected(null),
        });
    };

    const reject = (row) => {
        const reason = window.prompt('Lý do từ chối kết nối dữ liệu này?');
        if (!reason) return;
        router.post(`${approveBaseUrl}/${row.id}/reject`, { reason }, { preserveScroll: true });
    };

    return (
        <AppLayout activeMenuCode={activeMenuCode}>
            <Head title="Duyệt kết nối dữ liệu" />
            <section className="ps-adminlte-page pslc-page pslc-approval-page" data-page-code={activeMenuCode}>
                <form className="ps-page-header ps-page-header-v119 pslc-approval-header" onSubmit={(event) => event.preventDefault()}>
                    <div className="ps-page-header-main">
                        <div className="ps-title ps-page-title">Duyệt kết nối dữ liệu</div>
                        <div className="ps-page-primary-filters pslc-approval-filters">
                            <PushsaleSelect
                                options={[
                                    { value: 'pending', label: 'Chờ duyệt' },
                                    { value: 'approved', label: 'Đã duyệt' },
                                    { value: 'rejected', label: 'Từ chối' },
                                    { value: 'all', label: 'Tất cả' },
                                ]}
                                value={status}
                                searchable={false}
                                onChange={(value) => setStatus(value || 'pending')}
                            />
                            <input className="form-control" value={search} placeholder="Tên nguồn dữ liệu / marketing / URL" onChange={(event) => setSearch(event.target.value)} />
                        </div>
                    </div>
                </form>

                <div className="box-body pslc-table-card pslc-approval-table-card">
                    <div className="pslc-table-scroll">
                        <table className="table table-bordered table-multi-select pslc-table pslc-approval-table">
                            <thead>
                                <tr>
                                    <th className="text-center pslc-col-stt">STT</th>
                                    <th className="text-center">Marketing</th>
                                    <th className="text-center pslc-col-source">Tên nguồn kết nối<br /><span>Url nguồn dữ liệu</span></th>
                                    <th className="text-center">Kênh quảng cáo</th>
                                    <th className="text-center">Sản phẩm / gói sản phẩm</th>
                                    <th className="text-center">Ngân sách</th>
                                    <th className="text-center">Trạng thái</th>
                                    <th className="text-center">Cập nhật</th>
                                    <th className="text-center">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.length ? rows.map((row, index) => (
                                    <tr key={row.id}>
                                        <td className="text-center">{index + 1} -</td>
                                        <td className="text-center">{row.marketer || '—'}<br />{row.marketer_email && <span className="small-tip">({row.marketer_email})</span>}</td>
                                        <td className="text-left">{row.name}<br /><span className="small-tip">{row.source_url || row.webhook_url || '—'}</span></td>
                                        <td className="text-center">{channelLabels[row.ad_channel] || row.ad_channel || '—'}</td>
                                        <td className="text-left">{row.products?.length ? row.products.map((item) => <div key={item.id}>{item.product_name}</div>) : <span className="text-muted">Chưa gắn sản phẩm/gói</span>}</td>
                                        <td className="text-right">{formatMoney(row.budget)} đ<br /><span className="small-tip">{row.budget_type === 'daily' ? 'Theo ngày' : 'Tổng'}</span></td>
                                        <td className="text-center">
                                            {row.is_approved ? <span className="ps-status ps-status-ok">Đã duyệt</span> : row.rejected_at ? <span className="ps-status ps-status-danger">Từ chối</span> : <span className="ps-status ps-status-warning">Chờ duyệt</span>}
                                        </td>
                                        <td className="text-center">{row.approved_by || row.creator || 'admin'}<br />{row.approved_at || row.created_at}</td>
                                        <td className="text-center pslc-actions">
                                            <button type="button" className="btn-icon" onClick={() => openApprove(row)} title="Cập nhật & duyệt"><i className="fa fa-edit" /></button>
                                            {!row.is_approved && <button type="button" className="btn-icon text-danger" onClick={() => reject(row)} title="Từ chối"><i className="fa fa-trash" /></button>}
                                        </td>
                                    </tr>
                                )) : <tr><td colSpan="9" className="text-center text-muted">Không có kết nối dữ liệu cần duyệt.</td></tr>}
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <ApprovalDialog open={Boolean(selected)} onClose={() => setSelected(null)}>
                <form className="pslc-form pslc-approval-form" onSubmit={approve}>
                    <div className="pslc-dialog-body">
                        <div className="pslc-source-simple-form pslc-approval-simple-form">
                            <label>Tên nguồn dữ liệu</label>
                            <input className="form-control" readOnly value={selected?.name || ''} />

                            <label>Url nguồn dữ liệu</label>
                            <input className="form-control" readOnly value={selected?.source_url || ''} />

                            <label>Sản phẩm / gói sản phẩm <span className="required">(*)</span></label>
                            <PushsaleMultiSelect
                                label="Sản phẩm"
                                options={productOptions}
                                selectedIds={form.data.product_ids}
                                enabled
                                onEnabledChange={() => {}}
                                onChange={(ids) => form.setData('product_ids', ids)}
                                allLabel="Chọn sản phẩm / gói sản phẩm"
                                placeholder="--Chọn sản phẩm / gói sản phẩm--"
                                emptyLabel="Chưa chọn sản phẩm"
                            />

                            <label>Loại ngân sách</label>
                            <PushsaleSelect options={budgetTypeOptions} value={form.data.budget_type} searchable={false} onChange={(value) => form.setData('budget_type', value || 'total')} />

                            <label>Ngân sách</label>
                            <input className="form-control text-right" type="number" min="0" value={form.data.budget_amount} onChange={(event) => form.setData('budget_amount', event.target.value)} />

                            <label>Từ ngày</label>
                            <input className="form-control" type="date" value={form.data.budget_start_date || ''} onChange={(event) => form.setData('budget_start_date', event.target.value)} />

                            <label>Đến ngày</label>
                            <input className="form-control" type="date" value={form.data.budget_end_date || ''} onChange={(event) => form.setData('budget_end_date', event.target.value)} />
                        </div>
                        {Object.keys(form.errors).length > 0 && <div className="alert alert-danger pslc-errors">{Object.entries(form.errors).map(([key, message]) => <div key={key}><strong>{key}:</strong> {message}</div>)}</div>}
                    </div>
                    <footer className="pslc-dialog-footer"><button className="btn btn-primary" disabled={form.processing}><i className="fa fa-save" /> Cập nhật & duyệt</button></footer>
                </form>
            </ApprovalDialog>
        </AppLayout>
    );
}
