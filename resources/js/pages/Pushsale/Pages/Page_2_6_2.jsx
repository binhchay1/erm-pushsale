import { Head, router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';

import AppLayout from '@/layouts/AppLayout';
import { PushsalePagination } from '@/components/pagination/PushsalePagination';

function currentQuery() {
    if (typeof window === 'undefined') return {};
    return Object.fromEntries(new URLSearchParams(window.location.search).entries());
}

function optionText(option) {
    return String(option?.label ?? option?.name ?? option?.source ?? option?.sku ?? option?.id ?? '');
}

function normalize(value) {
    return String(value ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();
}

function formatDateTime(value) {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);

    return new Intl.DateTimeFormat('vi-VN', {
        day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit',
    }).format(date);
}

function isSelected(value, optionId, multiple) {
    const key = String(optionId);
    if (multiple) return (value ?? []).map(String).includes(key);
    return String(value ?? '') === key;
}

function PushsaleDdl({ label, placeholder, options = [], value, multiple = false, onChange, maxResults = 20 }) {
    const rootRef = useRef(null);
    const [open, setOpen] = useState(false);
    const [keyword, setKeyword] = useState('');

    useEffect(() => {
        const handler = (event) => {
            if (!rootRef.current?.contains(event.target)) setOpen(false);
        };
        document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, []);

    const selectedOptions = useMemo(() => {
        const ids = multiple ? (value ?? []).map(String) : [String(value ?? '')];
        return options.filter((option) => ids.includes(String(option.id)));
    }, [multiple, options, value]);

    const filtered = useMemo(() => {
        const q = normalize(keyword);
        return options
            .filter((option) => !q || normalize(optionText(option)).includes(q) || normalize(option?.sku).includes(q))
            .slice(0, maxResults);
    }, [keyword, maxResults, options]);

    const selectOption = (option) => {
        const id = String(option.id);
        if (multiple) {
            const current = (value ?? []).map(String);
            onChange(current.includes(id) ? current.filter((item) => item !== id) : [...current, id]);
            return;
        }
        onChange(id);
        setOpen(false);
    };

    const clearOne = (event, optionId) => {
        event.preventDefault();
        event.stopPropagation();
        if (!multiple) {
            onChange('');
            return;
        }
        onChange((value ?? []).map(String).filter((item) => item !== String(optionId)));
    };

    return (
        <div className="ps-manual-lead-field">
            <span className="h-label">{label}</span>
            <div className={`ps-ddl ps-manual-ddl ${open ? 'is-open' : ''}`} ref={rootRef}>
                <button type="button" className="ps-ddl-display-text" onClick={() => setOpen((state) => !state)}>
                    {selectedOptions.length ? (
                        <span className="ps-ddl-selected-list">
                            {selectedOptions.slice(0, 3).map((option) => (
                                <span key={option.id} item-id={option.id} className={`ps-ddl-selected-item ${multiple ? 'multiple-select' : ''}`}>
                                    {optionText(option)}
                                    <i className="fa fa-close ps-ddl-inside" onClick={(event) => clearOne(event, option.id)} aria-hidden="true" />
                                </span>
                            ))}
                            {selectedOptions.length > 3 && <span className="ps-ddl-selected-item multiple-select">+{selectedOptions.length - 3}</span>}
                        </span>
                    ) : (
                        <span item-id="-1" className={`ps-ddl-selected-item ${multiple ? 'multiple-select' : ''}`}>{placeholder}</span>
                    )}
                </button>
                <button type="button" className="ps-ddl-arow" onClick={() => setOpen((state) => !state)} aria-label="Mở danh sách">
                    <i className="fa fa-angle-down" />
                </button>
                {open && (
                    <div className="ps-ddl-search-area">
                        <input
                            type="text"
                            className="ps-ddl-search-box"
                            placeholder="Nhập từ khóa để tìm kiếm"
                            value={keyword}
                            onChange={(event) => setKeyword(event.target.value)}
                            autoFocus
                        />
                        <div className="result-items">
                            {filtered.length ? filtered.map((option) => (
                                <button
                                    type="button"
                                    key={option.id}
                                    className={`ps-ddl-result-item ${isSelected(value, option.id, multiple) ? 'is-selected' : ''}`}
                                    onClick={() => selectOption(option)}
                                >
                                    {multiple && <i className={`fa ${isSelected(value, option.id, multiple) ? 'fa-check-square-o' : 'fa-square-o'}`} aria-hidden="true" />}
                                    <span>{optionText(option)}</span>
                                </button>
                            )) : <div className="result-not-found">Không tìm thấy</div>}
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}

function emptyForm() {
    return {
        marketing_source_id: '',
        product_ids: [],
        name: '',
        phone: '',
        message: '',
        utm_source: '',
        utm_medium: '',
        utm_campaign: '',
        utm_content: '',
        utm_term: '',
    };
}

function cleanPayload(form) {
    const payload = {
        marketing_source_id: form.marketing_source_id || null,
        product_ids: (form.product_ids ?? []).map((id) => Number(id)).filter(Boolean),
        name: form.name,
        phone: form.phone,
        message: form.message,
        utm_source: form.utm_source,
        utm_medium: form.utm_medium,
        utm_campaign: form.utm_campaign,
        utm_content: form.utm_content,
        utm_term: form.utm_term,
    };

    Object.keys(payload).forEach((key) => {
        if (payload[key] === '' || payload[key] === null || (Array.isArray(payload[key]) && payload[key].length === 0)) delete payload[key];
    });

    return payload;
}

export default function ManualMarketingLeadPage({ schema, rows = [], pagination = {}, filterOptions = {}, routeUrl, pageRuntimeError = null }) {
    const [form, setForm] = useState(() => emptyForm());
    const [showUtm, setShowUtm] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [localRows, setLocalRows] = useState([]);
    const [message, setMessage] = useState('');

    const sources = useMemo(() => (filterOptions.sources ?? []).slice(0, 1000), [filterOptions.sources]);
    const products = useMemo(() => (filterOptions.products ?? []).filter((item) => item.is_active !== false), [filterOptions.products]);
    const displayRows = localRows.concat(rows ?? []).slice(0, 100);

    const setField = (key, value) => setForm((current) => ({ ...current, [key]: value }));

    const submit = (event) => {
        event.preventDefault();
        setMessage('');
        if (!form.phone.trim()) {
            setMessage('Số điện thoại là bắt buộc.');
            return;
        }

        setProcessing(true);
        const payload = cleanPayload(form);
        router.post('/admin/leads/manual', payload, {
            preserveScroll: true,
            onSuccess: () => {
                setLocalRows((current) => [{
                    index: 1,
                    customer_name: form.name || '—',
                    customer_phone: form.phone,
                    message: form.message,
                    created_at: new Date().toISOString(),
                }, ...current].map((row, index) => ({ ...row, index: index + 1 })));
                setForm(emptyForm());
                setMessage('Đã lưu data thủ công vào luồng nhận lead.');
            },
            onError: (errors) => {
                setMessage(Object.values(errors ?? {})[0] ?? 'Không lưu được data. Kiểm tra lại số điện thoại hoặc dữ liệu bắt buộc.');
            },
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <AppLayout>
            <Head title={schema?.title ?? 'Nhập data thủ công'} />
            <div className="ps-manual-lead-page">
                <div className="ps-manual-lead-titlebar">
                    <h1>{schema?.title ?? 'Nhập data thủ công'}</h1>
                </div>

                {pageRuntimeError && <div className="alert alert-warning">{pageRuntimeError}</div>}

                <div className="ps-manual-lead-panel">
                    <div className="notice ps-manual-lead-notice">
                        <b>Lưu ý:</b><br />
                        - Dữ liệu nhập tại đây được xem giống như dữ liệu nhận về từ nguồn dữ liệu<br />
                        - Chỉ hiển thị lựa chọn tối đa 20 nguồn dữ liệu, vui lòng sử dụng chức năng tìm kiếm để tìm nguồn dữ liệu cần chọn<br />
                        - Đối với trường thông tin Sản phẩm, nếu điền thì bản ghi data trên hệ thống sẽ hiển thị kèm theo sản phẩm vừa điền, nếu không điền bản ghi data trên hệ thống sẽ lấy theo cấu hình nguồn dữ liệu
                    </div>

                    <div className="ps-manual-lead-body">
                        <form className="ps-manual-lead-form" onSubmit={submit}>
                            <PushsaleDdl
                                label="Nguồn dữ liệu"
                                placeholder="--Chọn nguồn dữ liệu--"
                                options={sources}
                                value={form.marketing_source_id}
                                onChange={(value) => setField('marketing_source_id', value)}
                            />

                            <PushsaleDdl
                                label="Sản phẩm"
                                placeholder="--Chọn sản phẩm--"
                                options={products}
                                multiple
                                value={form.product_ids}
                                onChange={(value) => setField('product_ids', value)}
                            />

                            <label className="ps-manual-lead-field">
                                <span className="h-label">Họ tên khách hàng</span>
                                <input
                                    type="text"
                                    className="form-control"
                                    maxLength={100}
                                    value={form.name}
                                    onChange={(event) => setField('name', event.target.value)}
                                />
                            </label>

                            <label className="ps-manual-lead-field">
                                <span className="h-label">Số điện thoại</span>
                                <input
                                    type="text"
                                    className="form-control phone-number"
                                    maxLength={20}
                                    required
                                    value={form.phone}
                                    onChange={(event) => setField('phone', event.target.value)}
                                />
                            </label>

                            <label className="ps-manual-lead-field">
                                <span className="h-label">Tin nhắn</span>
                                <textarea
                                    className="form-control"
                                    rows={4}
                                    maxLength={500}
                                    value={form.message}
                                    onChange={(event) => setField('message', event.target.value)}
                                />
                            </label>

                            {showUtm && (
                                <div className="ps-manual-lead-utm">
                                    {['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'].map((key) => (
                                        <label key={key} className="ps-manual-lead-field">
                                            <span className="h-label">{key}</span>
                                            <input
                                                type="text"
                                                className="form-control"
                                                maxLength={50}
                                                value={form[key]}
                                                onChange={(event) => setField(key, event.target.value)}
                                            />
                                        </label>
                                    ))}
                                </div>
                            )}

                            <div className="ps-manual-lead-actions disabled-block1">
                                <button type="submit" className="btn btn-sm btn-default ml15" disabled={processing}>
                                    <i className="fa fa-save" aria-hidden="true" /> Lưu đơn
                                </button>
                                <button type="button" className="btn-icon" onClick={() => setShowUtm((state) => !state)} title="Mở rộng UTM">
                                    <i className={`fa ${showUtm ? 'fa-angle-double-up' : 'fa-angle-double-down'}`} aria-hidden="true" />
                                </button>
                            </div>
                            {message && <div className="ps-manual-lead-message">{message}</div>}
                        </form>

                        <div className="ps-manual-lead-list">
                            <table className="table table-bordered ps-manual-lead-table">
                                <thead>
                                    <tr>
                                        <th style={{ width: 60 }} className="text-center">STT</th>
                                        <th className="text-center no-wrap">Họ tên</th>
                                        <th className="text-center no-wrap">Số điện thoại</th>
                                        <th className="text-center no-wrap">Tin nhắn</th>
                                        <th className="text-center no-wrap">Ngày tạo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {displayRows.length ? displayRows.map((row, index) => (
                                        <tr key={`${row.customer_phone ?? index}-${row.created_at ?? index}`}>
                                            <td className="text-center">{index + 1}</td>
                                            <td>{row.customer_name || '—'}</td>
                                            <td className="no-wrap">{row.customer_phone || '—'}</td>
                                            <td>{row.message || row.product_interest || ''}</td>
                                            <td className="no-wrap text-center">{formatDateTime(row.created_at)}</td>
                                        </tr>
                                    )) : (
                                        <tr>
                                            <td colSpan={5} className="text-center ps-manual-lead-empty">Không có dữ liệu.</td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                            <PushsalePagination meta={pagination} routeUrl={routeUrl} filters={currentQuery()} itemLabel="contact" align="split" />
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
