import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

import AppLayout from '@/layouts/AppLayout';
import { apiRequest } from '@/lib/api';
import { formatCurrency } from '@/lib/format';
import { openShippingLabel } from '@/lib/shipping';

function maskPhone(phone, enabled) {
    const digits = String(phone || '').replace(/\D+/g, '');
    if (!enabled || digits.length < 7) return phone || '';
    return `${digits.slice(0, 3)}***${digits.slice(-3)}`;
}

function sortLabels(labels, sortBy) {
    const list = [...labels];
    list.sort((a, b) => {
        if (sortBy === 'order_code') return String(a.order_code).localeCompare(String(b.order_code));
        if (sortBy === 'warehouse') return String(a.warehouse_name || '').localeCompare(String(b.warehouse_name || ''));
        if (sortBy === 'province') return String(a.province || '').localeCompare(String(b.province || ''));
        return 0;
    });
    return list;
}

function InternalLabelCard({ label, settings }) {
    const t = settings.toggles || {};
    const products = (label.products || []).filter((item) => {
        if (t.print_combo === false && item.item_type === 'combo') return false;
        return true;
    });
    const phone = maskPhone(label.receiver_phone, t.mask_customer_phone);
    const tracking = t.use_pushsale_as_tracking ? label.order_code : (label.tracking_number || label.order_code);
    const sender = settings.sender_text || label.sender_print_note || label.sender_name || '';
    const note = settings.note_text || label.shipping_notes || label.customer_note || '';

    return (
        <article className="ps-print-label-card" style={{ fontSize: `${settings.font_product || 12}px` }}>
            <header className="ps-print-label-head">
                <div className="ps-print-barcode" style={{ fontSize: `${settings.font_barcode || 11}px` }}>*{tracking}*</div>
                <div className="ps-print-title">PHIẾU GỬI HÀNG</div>
                <div className="ps-print-province">{label.province || '—'}</div>
            </header>
            <div className="ps-print-meta">
                <div>
                    <b>Mã vận đơn:</b>
                    {' '}
                    {label.tracking_number || '—'}
                </div>
                <div style={{ fontSize: `${settings.font_order_code || 11}px` }}>
                    <b>Mã đơn:</b>
                    {' '}
                    {label.order_code}
                </div>
            </div>
            <div className="ps-print-block">
                <b>Người gửi:</b>
                {' '}
                {sender || '—'}
                {t.show_sale_phone && label.sale_phone ? ` · Sale: ${label.sale_phone}` : ''}
                {t.show_print_date ? ` · ${new Date().toLocaleDateString('vi-VN')}` : ''}
            </div>
            <div className="ps-print-block">
                <div>
                    <b>Người nhận:</b>
                    {' '}
                    {label.receiver_name}
                </div>
                <div>
                    <b>SĐT:</b>
                    {' '}
                    <strong>{phone}</strong>
                </div>
                <div>
                    <b>ĐC:</b>
                    {' '}
                    {[label.address, label.ward, label.district, label.province].filter(Boolean).join(', ')}
                </div>
            </div>
            <table className="ps-print-products">
                <tbody>
                    {products.map((item, index) => (
                        <tr key={`${label.id}-${index}`}>
                            <td>
                                {t.hide_product_name ? (t.show_sku ? (item.sku || 'SP') : '—') : item.name}
                                {t.show_sku && item.sku ? ` (${item.sku})` : ''}
                            </td>
                            <td className="text-right">
                                x
                                {item.quantity}
                            </td>
                            {t.show_unit_price ? <td className="text-right">{formatCurrency(item.unit_price)}</td> : null}
                        </tr>
                    ))}
                </tbody>
            </table>
            <div className="ps-print-money">
                {!t.hide_shipping_fee ? (
                    <div>
                        Phí VC:
                        {' '}
                        {formatCurrency(label.shipping_fee)}
                    </div>
                ) : null}
                {t.show_discount ? (
                    <div>
                        CK:
                        {' '}
                        {formatCurrency(label.discount)}
                    </div>
                ) : null}
                <div className="ps-print-cod">
                    Tiền thu hộ:
                    {' '}
                    {formatCurrency(label.cod)}
                </div>
            </div>
            {note ? (
                <div className="ps-print-note" style={{ fontSize: `${settings.font_note || 12}px` }}>{note}</div>
            ) : null}
            {settings.footer_text ? (
                <div className="ps-print-footer" style={{ fontSize: `${settings.font_footer || 12}px` }}>{settings.footer_text}</div>
            ) : null}
            {!t.hide_qr ? (
                <div className="ps-print-qr" style={{ width: settings.qr_size || 70, height: settings.qr_size || 70 }}>
                    QR
                </div>
            ) : null}
        </article>
    );
}

function OptionSelect({ value, onChange, options = [], empty = null }) {
    return (
        <select className="form-control" value={value ?? ''} onChange={(e) => onChange(e.target.value)}>
            {empty ? <option value="">{empty}</option> : null}
            {options.map((item) => (
                <option key={item.value} value={item.value}>{item.label}</option>
            ))}
        </select>
    );
}

export default function ShippingLabelPrint({
    profile,
    defaults = {},
    labels = [],
    unmatched = [],
    grouped = [],
    counts = {},
    featureFlags = {},
    backUrl = '/admin/warehouse/operations',
    actionApiBase = '/admin/warehouse/orders',
    shippingApiBase = '/admin/shipping/orders',
    activeMenuCode = '5.1',
}) {
    const [settings, setSettings] = useState(() => ({
        ...defaults,
        quantity: defaults.quantity ?? labels.length,
        toggles: { ...(defaults.toggles || {}) },
    }));
    const [busy, setBusy] = useState(false);
    const [carrierErrors, setCarrierErrors] = useState([]);
    const [activeTab, setActiveTab] = useState(defaults.tab || profile.tabs?.[0]?.value || 'print');

    useEffect(() => {
        const key = `ps-print-settings:${profile.key}`;
        try {
            const raw = localStorage.getItem(key);
            if (!raw) return;
            const saved = JSON.parse(raw);
            setSettings((old) => ({
                ...old,
                ...saved,
                toggles: { ...(old.toggles || {}), ...(saved.toggles || {}) },
                quantity: labels.length,
            }));
        } catch {
            // ignore
        }
    }, [profile.key, labels.length]);

    const persistSettings = (next) => {
        setSettings(next);
        try {
            localStorage.setItem(`ps-print-settings:${profile.key}`, JSON.stringify(next));
        } catch {
            // ignore
        }
    };

    const setField = (key, value) => persistSettings({ ...settings, [key]: value });
    const setToggle = (key, value) => persistSettings({
        ...settings,
        toggles: { ...(settings.toggles || {}), [key]: value },
    });

    const visibleLabels = useMemo(() => {
        const sorted = sortLabels(labels, settings.sort_by);
        const qty = Number(settings.quantity || sorted.length);
        return sorted.slice(0, Math.max(0, qty));
    }, [labels, settings.quantity, settings.sort_by]);

    const ids = visibleLabels.map((row) => row.id);

    const doMarkPrinted = async () => {
        if (!ids.length) {
            toast.error('Không có đơn để in.');
            return;
        }
        setBusy(true);
        try {
            const data = await apiRequest(`${actionApiBase}/print/mark-printed`, {
                method: 'POST',
                body: { ids },
            });
            toast.success(data.message || `Đã in ${ids.length} đơn.`);
            window.print();
        } catch (error) {
            toast.error(error.message);
        } finally {
            setBusy(false);
        }
    };

    const refresh = () => {
        router.reload({ only: ['labels', 'grouped', 'unmatched', 'counts'] });
        toast.success('Đã làm mới bản in.');
    };

    const openCarrierLabels = async ({ merge = false } = {}) => {
        setBusy(true);
        const errors = [];
        const urls = [];
        try {
            for (const row of visibleLabels) {
                const provider = row.label_provider ? `?provider=${encodeURIComponent(row.label_provider)}` : '';
                const url = `${shippingApiBase}/${row.id}/label${provider}`;
                try {
                    await openShippingLabel(url, 'Config is not exist.');
                    urls.push(url);
                } catch (error) {
                    errors.push({ order_code: row.order_code, message: error.message || 'Config is not exist.' });
                }
            }
            setCarrierErrors(errors);
            if (errors.length && !urls.length) toast.error(errors[0].message);
            else if (errors.length) toast.warning(`In được ${urls.length}, lỗi ${errors.length}`);
            else toast.success(merge ? `Đã mở ${urls.length} nhãn (gộp lần lượt).` : `Đã mở ${urls.length} nhãn.`);
            if (urls.length) {
                await apiRequest(`${actionApiBase}/print/mark-printed`, { method: 'POST', body: { ids } });
            }
        } finally {
            setBusy(false);
        }
    };

    const renderInternalSidebar = () => (
        <aside className="ps-print-sidebar">
            <div className="ps-print-tabs">
                {(profile.tabs || []).map((tab) => (
                    <button
                        key={tab.value}
                        type="button"
                        className={activeTab === tab.value ? 'active' : ''}
                        onClick={() => setActiveTab(tab.value)}
                    >
                        {tab.label}
                    </button>
                ))}
            </div>
            <div className="ps-print-qty-badge">
                <div>Số lượng In</div>
                <strong>{visibleLabels.length}</strong>
            </div>
            <div className="form-group">
                <span className="h-label">Mẫu in</span>
                <OptionSelect value={settings.template} onChange={(v) => setField('template', v)} options={profile.templates} />
            </div>
            <div className="form-group">
                <span className="h-label">Chiều cao</span>
                <input className="form-control" type="number" value={settings.height ?? 0} onChange={(e) => setField('height', Number(e.target.value || 0))} />
            </div>
            <div className="form-group">
                <span className="h-label">Sắp xếp theo</span>
                <OptionSelect value={settings.sort_by} onChange={(v) => setField('sort_by', v)} options={profile.sort_options} />
            </div>
            <div className="ps-print-font-grid">
                <label>
                    Cỡ chữ mã đơn
                    <input className="form-control" type="number" value={settings.font_order_code} onChange={(e) => setField('font_order_code', Number(e.target.value || 0))} />
                </label>
                <label>
                    Cỡ mã vạch
                    <input className="form-control" type="number" value={settings.font_barcode} onChange={(e) => setField('font_barcode', Number(e.target.value || 0))} />
                </label>
                <label>
                    Cỡ chữ sản phẩm
                    <input className="form-control" type="number" value={settings.font_product} onChange={(e) => setField('font_product', Number(e.target.value || 0))} />
                </label>
                <label>
                    Cỡ chữ mã hỗ trợ
                    <input className="form-control" type="number" value={settings.font_support_code} onChange={(e) => setField('font_support_code', Number(e.target.value || 0))} />
                </label>
            </div>
            <div className="form-group">
                <span className="h-label">PGH người gửi / Cỡ QR</span>
                <textarea className="form-control" rows={2} value={settings.sender_text || ''} onChange={(e) => setField('sender_text', e.target.value)} />
                <input className="form-control" type="number" value={settings.qr_size} onChange={(e) => setField('qr_size', Number(e.target.value || 0))} />
            </div>
            <div className="form-group">
                <span className="h-label">PGH ghi chú / Cỡ chữ</span>
                <textarea className="form-control" rows={2} value={settings.note_text || ''} onChange={(e) => setField('note_text', e.target.value)} />
                <input className="form-control" type="number" value={settings.font_note} onChange={(e) => setField('font_note', Number(e.target.value || 0))} />
            </div>
            <div className="form-group">
                <span className="h-label">PGH footer / Cỡ chữ</span>
                <textarea className="form-control" rows={2} value={settings.footer_text || ''} onChange={(e) => setField('footer_text', e.target.value)} />
                <input className="form-control" type="number" value={settings.font_footer} onChange={(e) => setField('font_footer', Number(e.target.value || 0))} />
            </div>
            <div className="ps-print-toggles">
                {(profile.toggles || []).map((toggle) => (
                    <label key={toggle.key}>
                        <input
                            type="checkbox"
                            checked={Boolean(settings.toggles?.[toggle.key])}
                            onChange={(e) => setToggle(toggle.key, e.target.checked)}
                        />
                        {' '}
                        {toggle.label}
                    </label>
                ))}
            </div>
            {featureFlags.watermark_logo ? <div className="text-muted small">Logo chìm đang được bật (1.6).</div> : null}
            <div className="ps-print-sidebar-actions">
                <button type="button" className="btn btn-primary" disabled={busy} onClick={doMarkPrinted}>
                    <i className="fa fa-print" />
                    {' '}
                    In
                </button>
                <button type="button" className="btn btn-info" disabled={busy} onClick={refresh}>
                    <i className="fa fa-refresh" />
                    {' '}
                    Làm mới bản in
                </button>
            </div>
        </aside>
    );

    const renderPlatform = () => (
        <div className="ps-print-platform">
            <div className="ps-print-platform-top">
                <div className="ps-print-tabs">
                    {(profile.tabs || []).map((tab) => (
                        <button key={tab.value} type="button" className={activeTab === tab.value ? 'active' : ''} onClick={() => setActiveTab(tab.value)}>
                            {tab.label}
                        </button>
                    ))}
                </div>
                <div className="ps-print-platform-controls">
                    <label>
                        Số lượng in
                        <input className="form-control" type="number" value={settings.quantity ?? ''} onChange={(e) => setField('quantity', Number(e.target.value || 0))} />
                    </label>
                    <label>
                        Mẫu in
                        <OptionSelect value={settings.template} onChange={(v) => setField('template', v)} options={profile.templates} />
                    </label>
                    {profile.supports_size ? (
                        <label>
                            Kích thước in
                            <OptionSelect value={settings.size} onChange={(v) => setField('size', v)} options={profile.sizes} />
                        </label>
                    ) : null}
                    <button type="button" className="btn btn-primary" disabled={busy} onClick={() => openCarrierLabels()}>
                        <i className="fa fa-refresh" />
                        {' '}
                        Làm mới bản in
                    </button>
                    <Link href={backUrl} className="btn btn-default">
                        <i className="fa fa-close" />
                        {' '}
                        Đóng
                    </Link>
                </div>
            </div>
            {carrierErrors.length ? (
                <div className="ps-print-alert">
                    {carrierErrors.slice(0, 3).map((item) => (
                        <div key={item.order_code}>
                            {item.order_code}
                            :
                            {' '}
                            {item.message}
                        </div>
                    ))}
                </div>
            ) : null}
            <table className="table table-bordered">
                <thead>
                    <tr>
                        <th>Mã đơn sàn</th>
                        <th>Trạng thái</th>
                        <th>Dữ liệu</th>
                    </tr>
                </thead>
                <tbody>
                    {(activeTab === 'remaining' || activeTab === 'error' ? unmatched : visibleLabels).length === 0 ? (
                        <tr><td colSpan={3}>{activeTab === 'error' ? 'Không có đơn lỗi.' : 'Không có đơn khớp mẫu / đơn vị giao hàng.'}</td></tr>
                    ) : (activeTab === 'remaining' || activeTab === 'error' ? unmatched : visibleLabels).map((row) => (
                        <tr key={row.id}>
                            <td>{row.tracking_number || row.order_code}</td>
                            <td>
                                {activeTab === 'remaining' || activeTab === 'error'
                                    ? (row.message || 'Không khớp PTGH')
                                    : (row.can_print_carrier_label ? 'Sẵn sàng in' : 'Thiếu mã vận đơn')}
                            </td>
                            <td>
                                {row.order_code}
                                {row.receiver_name ? ` · ${row.receiver_name}` : ''}
                                {row.cod != null ? ` · ${formatCurrency(row.cod)}` : ''}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
            {unmatched.length && activeTab !== 'remaining' && activeTab !== 'error' ? <div className="text-muted">Bỏ qua {unmatched.length} đơn không khớp PTGH.</div> : null}
        </div>
    );

    const renderCarrier = () => (
        <div className="ps-print-carrier">
            <aside className="ps-print-sidebar">
                <div className="form-group">
                    <span className="h-label">Số lượng in</span>
                    <input className="form-control" type="number" value={settings.quantity ?? ''} onChange={(e) => setField('quantity', Number(e.target.value || 0))} />
                </div>
                <div className="form-group">
                    <span className="h-label">Mẫu in</span>
                    <OptionSelect value={settings.template} onChange={(v) => setField('template', v)} options={profile.templates} />
                </div>
                {(profile.orientations || []).length ? (
                    <div className="form-group">
                        <span className="h-label">Kiểu in</span>
                        <OptionSelect value={settings.orientation} onChange={(v) => setField('orientation', v)} options={profile.orientations} />
                    </div>
                ) : null}
                <button type="button" className="btn btn-primary" disabled={busy} onClick={() => openCarrierLabels()}>
                    <i className="fa fa-refresh" />
                    {' '}
                    Làm mới bản in
                </button>
            </aside>
            <div className="ps-print-carrier-main">
                {(grouped.length ? grouped : [{ title: 'Đơn in', labels: visibleLabels }]).map((group) => (
                    <section key={group.title} className="ps-print-carrier-group">
                        <header>
                            <h3>{group.title}</h3>
                            <button type="button" className="btn btn-primary btn-xs" onClick={refresh}>
                                <i className="fa fa-refresh" />
                                {' '}
                                Tải lại
                            </button>
                        </header>
                        {profile.pretty_print ? (
                            <label className="ps-print-pretty">
                                <input type="checkbox" checked={Boolean(settings.pretty_print)} onChange={(e) => setField('pretty_print', e.target.checked)} />
                                {' '}
                                Pretty-print
                            </label>
                        ) : null}
                        <div className={`ps-print-carrier-body ${settings.pretty_print ? 'is-pretty' : ''}`}>
                            {(group.labels || []).slice(0, Number(settings.quantity || group.labels.length)).map((row) => (
                                <div key={row.id} className="ps-print-carrier-item">
                                    <b>{row.order_code}</b>
                                    {' · '}
                                    {row.tracking_number || 'Chưa có mã vận đơn'}
                                    {' · '}
                                    {row.receiver_name}
                                </div>
                            ))}
                        </div>
                    </section>
                ))}
                {carrierErrors.length ? (
                    <div className="ps-print-alert">
                        {carrierErrors.map((item) => (
                            <div key={item.order_code}>{item.order_code}: {item.message}</div>
                        ))}
                    </div>
                ) : null}
            </div>
        </div>
    );

    const renderMerge = () => (
        <div className="ps-print-merge">
            <button type="button" className="btn btn-success" disabled={busy || !visibleLabels.length} onClick={() => openCarrierLabels({ merge: true })}>
                <i className="fa fa-check-square-o" />
                {' '}
                {profile.merge_all_label || 'Gộp tất cả đơn'}
            </button>
            {carrierErrors.length ? (
                <div className="ps-print-alert">
                    {carrierErrors.map((item) => (
                        <div key={item.order_code}>{item.order_code}: {item.message}</div>
                    ))}
                </div>
            ) : null}
            <div className="ps-print-merge-list">
                {visibleLabels.map((row) => (
                    <div key={row.id}>{row.order_code} · {row.tracking_number || '—'}</div>
                ))}
            </div>
            {unmatched.length ? <div className="text-muted">Bỏ qua {unmatched.length} đơn không khớp PTGH.</div> : null}
        </div>
    );

    return (
        <AppLayout activeMenuCode={activeMenuCode}>
            <Head title={profile.title} />
            <section className={`ps-print-page ps-print-ui-${profile.ui}`} data-page-code={activeMenuCode}>
                <div className="ps-print-page-bar no-print">
                    <div>
                        <h2>{profile.title}</h2>
                        <div className="text-muted">
                            Chọn
                            {' '}
                            {counts.selected || 0}
                            {' · in được '}
                            {counts.printable || 0}
                            {counts.unmatched ? ` · bỏ qua ${counts.unmatched}` : ''}
                        </div>
                    </div>
                    <Link href={backUrl} className="btn btn-default">
                        <i className="fa fa-close" />
                        {' '}
                        Đóng
                    </Link>
                </div>

                {profile.ui === 'internal' ? (
                    <div className="ps-print-internal">
                        <div className="ps-print-preview">
                            {visibleLabels.map((label) => (
                                <InternalLabelCard key={label.id} label={label} settings={settings} />
                            ))}
                            {!visibleLabels.length ? <div className="ps-print-empty">Không có đơn để in.</div> : null}
                        </div>
                        <div className="no-print">{renderInternalSidebar()}</div>
                    </div>
                ) : null}

                {profile.ui === 'platform' ? <div className="no-print">{renderPlatform()}</div> : null}
                {profile.ui === 'carrier' ? <div className="no-print">{renderCarrier()}</div> : null}
                {profile.ui === 'merge' ? <div className="no-print">{renderMerge()}</div> : null}
            </section>
        </AppLayout>
    );
}
