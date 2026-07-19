import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useRef, useState } from 'react';

import { CurrencyInput } from '@/components/ui/currency-input';
import AppLayout from '@/layouts/AppLayout';
import { PushsaleDialog } from '@/components/ui/pushsale-dialog';
import { formatCurrency, formatNumber } from '@/lib/format';

function DialogShell({ title, open, onClose, children, wide = false }) {
    return (
        <PushsaleDialog
            open={open}
            onOpenChange={(nextOpen) => !nextOpen && onClose()}
            title={title}
            width={wide ? 'calc(100vw - 60px)' : '900px'}
            bodyClassName="ps-source-dialog-body"
        >
            {children}
        </PushsaleDialog>
    );
}

function Pagination({ pagination }) {
    return (
        <div className="ps-pagination-bar">
            <span>{pagination.from ?? 0} - {pagination.to ?? 0} / {pagination.total ?? 0}</span>
            <ul className="pagination pagination-sm">
                {(pagination.links ?? []).map((link, index) => (
                    <li key={`${link.label}-${index}`} className={`${link.active ? 'active' : ''} ${!link.url ? 'disabled' : ''}`}>
                        <button type="button" disabled={!link.url} onClick={() => link.url && router.get(link.url, {}, { preserveState: true, preserveScroll: true })} dangerouslySetInnerHTML={{ __html: link.label }} />
                    </li>
                ))}
            </ul>
        </div>
    );
}

const emptyProduct = {
    name: '', sku: '', unit: '', cost_price: 0, unit_price: 0, vat_percent: 0,
    vat_code: 'KCT', weight_grams: 0, is_active: true, available_marketing: true,
    available_sale: true, available_care: true, category_ids: [], type: 'product',
};

export default function ProductsIndex({ products, filters = {}, categories = [], attributes = [], vatCodes = [] }) {
    const [query, setQuery] = useState({
        search: filters.search ?? '', active: filters.active ?? '', category_id: filters.category_id ?? '',
        marketing: filters.marketing ?? '', sale: filters.sale ?? '', care: filters.care ?? '',
        vat: filters.vat ?? '', sort: filters.sort ?? 'newest',
    });
    const [selected, setSelected] = useState(new Set());
    const [productOpen, setProductOpen] = useState(false);
    const [editingId, setEditingId] = useState(null);
    const [taxonomy, setTaxonomy] = useState(null);
    const importRef = useRef(null);
    const productForm = useForm(emptyProduct);
    const taxonomyForm = useForm({ name: '', product_attribute_id: '', is_active: true });
    const rows = products?.data ?? [];

    const submitFilters = (event) => {
        event.preventDefault();
        router.get('/admin/products', Object.fromEntries(Object.entries(query).filter(([, value]) => value !== '')), { preserveState: true, replace: true });
    };

    const openCreate = () => {
        setEditingId(null);
        productForm.setData(emptyProduct);
        productForm.clearErrors();
        setProductOpen(true);
    };

    const openEdit = (row) => {
        setEditingId(row.id);
        productForm.setData({
            name: row.name ?? '', sku: row.sku ?? '', unit: row.unit ?? '', cost_price: row.cost_price ?? 0,
            unit_price: row.unit_price ?? 0, vat_percent: row.vat_percent ?? 0, vat_code: row.vat_code ?? 'KCT',
            weight_grams: row.weight_grams ?? 0, is_active: row.is_active, available_marketing: row.available_marketing,
            available_sale: row.available_sale, available_care: row.available_care, category_ids: row.category_ids ?? [], type: 'product',
        });
        productForm.clearErrors();
        setProductOpen(true);
    };

    const saveProduct = (event) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => setProductOpen(false) };
        if (editingId) productForm.put(`/admin/products/${editingId}`, options);
        else productForm.post('/admin/products', options);
    };

    const saveTaxonomy = (event) => {
        event.preventDefault();
        const urls = {
            category: '/admin/products/categories',
            attribute: '/admin/products/attributes',
            value: '/admin/products/attribute-values',
        };
        taxonomyForm.post(urls[taxonomy], { preserveScroll: true, onSuccess: () => { taxonomyForm.reset(); setTaxonomy(null); } });
    };

    const toggleSelected = (id) => setSelected((current) => {
        const next = new Set(current);
        next.has(id) ? next.delete(id) : next.add(id);
        return next;
    });

    const deleteSelected = () => {
        if (!selected.size || !window.confirm(`Xóa ${selected.size} sản phẩm đã chọn?`)) return;
        [...selected].forEach((id) => router.delete(`/admin/products/${id}`, { preserveScroll: true, onFinish: () => setSelected(new Set()) }));
    };

    const exportCsv = () => {
        const headers = ['ID', 'Phân loại', 'Tên', 'Mã sản phẩm', 'Đơn vị', 'Giá nhập', 'Đơn giá', 'VAT', 'Mã VAT', 'Khối lượng', 'Ngừng KD', 'Marketing', 'Sale', 'CSKH'];
        const lines = rows.map((row) => [row.id, row.category_names, row.name, row.sku, row.unit, row.cost_price, row.unit_price, row.vat_percent, row.vat_code, row.weight_grams, !row.is_active, row.available_marketing, row.available_sale, row.available_care]);
        const quote = (value) => `"${String(value ?? '').replaceAll('"', '""')}"`;
        const blob = new Blob(['\ufeff' + [headers, ...lines].map((line) => line.map(quote).join(',')).join('\n')], { type: 'text/csv;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const anchor = document.createElement('a'); anchor.href = url; anchor.download = 'danh-sach-san-pham.csv'; anchor.click(); URL.revokeObjectURL(url);
    };

    const importFile = (event) => {
        const file = event.target.files?.[0];
        if (!file) return;
        const data = new FormData(); data.append('file', file);
        router.post('/admin/products/import', data, { forceFormData: true, preserveScroll: true, onFinish: () => { event.target.value = ''; } });
    };

    const allSelected = useMemo(() => rows.length > 0 && rows.every((row) => selected.has(row.id)), [rows, selected]);

    return (
        <AppLayout>
            <Head title="Quản lý sản phẩm" />
            <section className="ps-adminlte-page ps-products-page" data-page-code="1.3.1">
                <form onSubmit={submitFilters}>
                    <div className="m-header-wrap">
                        <div className="m-header ps-product-header">
                            <div className="ps-title">Quản lý sản phẩm</div>
                            <div className="ps-header-search ps-product-search">
                                <select className="form-control" value={query.vat} onChange={(event) => setQuery((old) => ({ ...old, vat: event.target.value }))}>
                                    <option value="">---VAT---</option>
                                    {vatCodes.map((code) => <option key={code} value={code}>{code}</option>)}
                                </select>
                                <input className="form-control" placeholder="Tên" value={query.search} onChange={(event) => setQuery((old) => ({ ...old, search: event.target.value }))} />
                                <button className="btn btn-sm btn-primary"><i className="fa fa-search" /> Tìm kiếm</button>
                            </div>
                        </div>
                    </div>
                    <div className="box-body ps-filter-row ps-product-filters">
                        <select className="form-control" value={query.sort} onChange={(event) => setQuery((old) => ({ ...old, sort: event.target.value }))}>
                            <option value="newest">Sắp xếp theo ngày tạo</option><option value="oldest">Cũ nhất</option><option value="name">Theo tên</option><option value="price_asc">Giá tăng dần</option><option value="price_desc">Giá giảm dần</option>
                        </select>
                        <select className="form-control" value={query.active} onChange={(event) => setQuery((old) => ({ ...old, active: event.target.value }))}><option value="">--Trạng thái kinh doanh--</option><option value="1">Đang kinh doanh</option><option value="0">Ngừng kinh doanh</option></select>
                        <select className="form-control" value={query.category_id} onChange={(event) => setQuery((old) => ({ ...old, category_id: event.target.value }))}><option value="">--Chọn phân loại--</option>{categories.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select>
                        <select className="form-control" value={query.marketing} onChange={(event) => setQuery((old) => ({ ...old, marketing: event.target.value }))}><option value="">--Chọn marketing--</option><option value="1">Được sử dụng</option><option value="0">Không sử dụng</option></select>
                        <select className="form-control" value={query.sale} onChange={(event) => setQuery((old) => ({ ...old, sale: event.target.value }))}><option value="">--Chọn sale--</option><option value="1">Được sử dụng</option><option value="0">Không sử dụng</option></select>
                        <select className="form-control" value={query.care} onChange={(event) => setQuery((old) => ({ ...old, care: event.target.value }))}><option value="">--Chọn CSKH--</option><option value="1">Được sử dụng</option><option value="0">Không sử dụng</option></select>
                    </div>
                </form>

                <div className="box-body ps-toolbar">
                    <button className="btn btn-sm btn-primary" type="button" onClick={openCreate}><i className="fa fa-plus" /> Thêm mới</button>
                    <button className="btn btn-sm btn-primary" type="button" onClick={() => { taxonomyForm.reset(); setTaxonomy('category'); }}><i className="fa fa-list-alt" /> Phân loại sản phẩm</button>
                    <button className="btn btn-sm btn-primary" type="button" onClick={() => { taxonomyForm.reset(); setTaxonomy('attribute'); }}><i className="fa fa-list-alt" /> Thuộc tính sản phẩm</button>
                    <button className="btn btn-sm btn-primary" type="button" onClick={() => { taxonomyForm.reset(); setTaxonomy('value'); }}><i className="fa fa-list-alt" /> Thuộc tính giá trị</button>
                    <button className="btn btn-sm btn-primary" type="button" onClick={() => importRef.current?.click()}><i className="fa fa-file-excel-o" /> Import sản phẩm</button>
                    <input ref={importRef} type="file" accept=".csv,.txt" hidden onChange={importFile} />
                    <button className="btn btn-sm btn-default" type="button" onClick={exportCsv}><i className="fa fa-file-excel-o" /> Export sản phẩm</button>
                    <button className="btn btn-sm btn-danger" type="button" disabled={!selected.size} onClick={deleteSelected}><i className="fa fa-file-excel-o" /> Xóa sản phẩm</button>
                </div>

                <div className="ps-table-scroll">
                    <table className="table table-bordered ps-source-table ps-product-table">
                        <thead><tr>
                            <th><input type="checkbox" checked={allSelected} onChange={() => setSelected(allSelected ? new Set() : new Set(rows.map((row) => row.id)))} /></th>
                            <th>#</th><th>Phân loại</th><th>Tên / mã sản phẩm</th><th>Đ.vị tính</th><th>Giá nhập</th><th>Đơn giá</th><th>VAT (%)</th><th>Mã VAT</th><th>KL(gram)</th><th>Ngừng KD</th><th>Marketing</th><th>Sale</th><th>Chăm sóc KH</th><th>Cập nhật</th><th>Thao tác</th>
                        </tr></thead>
                        <tbody>
                            {rows.length ? rows.map((row) => <tr key={row.id}>
                                <td className="text-center"><input type="checkbox" checked={selected.has(row.id)} onChange={() => toggleSelected(row.id)} /></td>
                                <td className="text-center"><strong>{row.id}</strong></td>
                                <td>{row.category_names}</td>
                                <td><strong>{row.name}</strong>{row.sku && <small>({row.sku})</small>}</td>
                                <td className="text-center">{row.unit}</td>
                                <td className="text-right">{row.cost_price ? formatCurrency(row.cost_price) : ''}</td>
                                <td className="text-right"><strong>{formatCurrency(row.unit_price)}</strong></td>
                                <td className="text-center">{row.vat_percent} %</td>
                                <td className="text-center">{row.vat_code}</td>
                                <td className="text-center">{formatNumber(row.weight_grams)}</td>
                                <td className="text-center"><input type="checkbox" readOnly checked={!row.is_active} /></td>
                                <td className="text-center">{row.available_marketing ? <i className="fa fa-check text-green" /> : ''}</td>
                                <td className="text-center">{row.available_sale ? <i className="fa fa-check text-green" /> : ''}</td>
                                <td className="text-center">{row.available_care ? <i className="fa fa-check text-green" /> : ''}</td>
                                <td className="text-center"><strong>{row.updated_at}</strong></td>
                                <td className="text-center ps-row-actions"><button type="button" onClick={() => openEdit(row)} title="Cập nhật"><i className="fa fa-pencil-square-o" /></button><button type="button" onClick={() => window.confirm(`Xóa sản phẩm ${row.name}?`) && router.delete(`/admin/products/${row.id}`, { preserveScroll: true })} title="Xóa"><i className="fa fa-trash" /></button></td>
                            </tr>) : <tr><td colSpan="16" className="ps-empty">Chưa có sản phẩm phù hợp.</td></tr>}
                        </tbody>
                    </table>
                </div>
                <Pagination pagination={products} />
            </section>

            <DialogShell title={editingId ? 'CẬP NHẬT SẢN PHẨM' : 'THÊM MỚI SẢN PHẨM'} open={productOpen} onClose={() => setProductOpen(false)} wide>
                <form onSubmit={saveProduct} className="ps-product-form">
                    <h4>THÔNG TIN SẢN PHẨM</h4>
                    <div className="ps-guide"><strong>Chỉ dẫn:</strong><br />- Tên, mã, đơn vị tính, mã vị trí của sản phẩm không được trùng.<br />- Các quyền Marketing, Sale và Chăm sóc KH quyết định nhóm nào được sử dụng sản phẩm.</div>
                    <div className="ps-form-grid ps-form-grid-3">
                        <label className="span-2">Tên SP gốc (*)<input className="form-control" value={productForm.data.name} onChange={(event) => productForm.setData('name', event.target.value)} required /></label>
                        <label>Phân loại<select className="form-control" value={productForm.data.category_ids?.[0] ?? ''} onChange={(event) => productForm.setData('category_ids', event.target.value ? [Number(event.target.value)] : [])}><option value="">--Phân loại--</option>{categories.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label>
                        <label>Mã SP<input className="form-control" value={productForm.data.sku} onChange={(event) => productForm.setData('sku', event.target.value)} /></label>
                        <label>KL(gram)<input className="form-control" type="number" min="0" value={productForm.data.weight_grams} onChange={(event) => productForm.setData('weight_grams', Number(event.target.value))} /></label>
                        <label>Đ.vị tính<input className="form-control" value={productForm.data.unit} onChange={(event) => productForm.setData('unit', event.target.value)} /></label>
                        <label>Giá nhập (VND)<CurrencyInput className="form-control" min="0" value={productForm.data.cost_price} onChange={(value) => productForm.setData('cost_price', value)} /></label>
                        <label>Đơn giá (VND)<CurrencyInput className="form-control" min="0" value={productForm.data.unit_price} onChange={(value) => productForm.setData('unit_price', value)} required /></label>
                        <label>SP ngừng kinh doanh<span className="ps-check-label"><input type="checkbox" checked={!productForm.data.is_active} onChange={(event) => productForm.setData('is_active', !event.target.checked)} /> Ngừng kinh doanh</span></label>
                        <label>Mã VAT<input className="form-control" value={productForm.data.vat_code} onChange={(event) => productForm.setData('vat_code', event.target.value)} /></label>
                        <label>VAT (%)<input className="form-control" type="number" min="0" max="100" value={productForm.data.vat_percent} onChange={(event) => productForm.setData('vat_percent', Number(event.target.value))} /></label>
                    </div>
                    <h4>PHÂN QUYỀN</h4>
                    <div className="ps-permission-checks">
                        <label><input type="checkbox" checked={productForm.data.available_marketing} onChange={(event) => productForm.setData('available_marketing', event.target.checked)} /> Marketing</label>
                        <label><input type="checkbox" checked={productForm.data.available_sale} onChange={(event) => productForm.setData('available_sale', event.target.checked)} /> Sale</label>
                        <label><input type="checkbox" checked={productForm.data.available_care} onChange={(event) => productForm.setData('available_care', event.target.checked)} /> Chăm sóc khách hàng</label>
                    </div>
                    {Object.keys(productForm.errors).length > 0 && <div className="alert alert-danger">{Object.values(productForm.errors).join(' · ')}</div>}
                    <button className="btn btn-primary" disabled={productForm.processing}><i className="fa fa-save" /> Lưu</button>
                </form>
            </DialogShell>

            <DialogShell title={taxonomy === 'category' ? 'PHÂN LOẠI SẢN PHẨM' : taxonomy === 'attribute' ? 'THUỘC TÍNH SẢN PHẨM' : 'THUỘC TÍNH GIÁ TRỊ'} open={Boolean(taxonomy)} onClose={() => setTaxonomy(null)}>
                <form onSubmit={saveTaxonomy} className="ps-taxonomy-form">
                    {taxonomy === 'value' && <label>Thuộc tính<select className="form-control" value={taxonomyForm.data.product_attribute_id} onChange={(event) => taxonomyForm.setData('product_attribute_id', event.target.value)} required><option value="">--Chọn thuộc tính--</option>{attributes.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label>}
                    <label>Tên<input className="form-control" value={taxonomyForm.data.name} onChange={(event) => taxonomyForm.setData('name', event.target.value)} required /></label>
                    <button className="btn btn-primary" disabled={taxonomyForm.processing}><i className="fa fa-save" /> Lưu</button>
                </form>
            </DialogShell>
        </AppLayout>
    );
}
