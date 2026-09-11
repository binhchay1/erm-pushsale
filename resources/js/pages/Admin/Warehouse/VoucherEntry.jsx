import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';

import { ProductSearchSelect } from '@/components/filters/ProductSearchSelect';
import { PushsalePageShell } from '@/components/layout/PushsalePageShell';
import { useConfirm } from '@/hooks/use-confirm';
import AppLayout from '@/layouts/AppLayout';
import { useT } from '@/providers/I18nProvider';

const numberFmt = new Intl.NumberFormat('vi-VN');

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

async function requestJson(url, method, payload) {
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: payload ? JSON.stringify(payload) : undefined,
    });

    const body = await response.json().catch(() => ({}));
    if (!response.ok) {
        const errors = Object.values(body.errors ?? {}).flat().join(' ');
        throw new Error(errors || body.message || 'Request failed');
    }

    return body;
}

async function requestFormData(url, formData) {
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: formData,
    });
    const body = await response.json().catch(() => ({}));
    if (!response.ok) {
        const errors = Object.values(body.errors ?? {}).flat().join(' ');
        throw new Error(errors || body.message || 'Import failed');
    }
    return body;
}

function todayIso() {
    return new Date().toISOString().slice(0, 10);
}

function emptyLine() {
    return {
        key: `new-${Date.now()}-${Math.random().toString(36).slice(2, 7)}`,
        product_id: '',
        document_quantity: 1,
        quantity: 1,
        unit_cost: 0,
        batch_code: '',
        expiry_date: '',
        location_code: '',
        note: '',
    };
}

function mapVoucherLines(lines = []) {
    return (Array.isArray(lines) ? lines : []).map((line, index) => ({
        key: `line-${line.id ?? index}`,
        product_id: line.product_id ? String(line.product_id) : '',
        document_quantity: Number(line.document_quantity ?? line.quantity ?? 0),
        quantity: Number(line.quantity ?? 0),
        unit_cost: Number(line.unit_cost ?? 0),
        batch_code: line.batch_code ?? '',
        expiry_date: line.expiry_date ?? '',
        location_code: line.location_code ?? '',
        note: line.note ?? '',
        product: line.product,
        sku: line.sku,
        uom: line.uom,
    }));
}

function statusLabel(status, t) {
    if (status === 'confirmed') return t('operations.voucher_entry.status_confirmed');
    if (status === 'cancelled') return t('operations.voucher_entry.status_cancelled');
    return t('operations.voucher_entry.status_draft');
}

function typePrefix(type) {
    if (type === 'inbound') return 'PNK';
    if (type === 'scrap') return 'PXH';
    if (type === 'internal') return 'PXNB';
    return 'PXK';
}

export default function VoucherEntry({
    schema,
    filterOptions = {},
    routeUrl = '/admin/warehouse/vouchers/entry',
    voucher = null,
    defaults = {},
    canTesterTools = false,
    pageRuntimeError = null,
}) {
    const t = useT();
    const { ask } = useConfirm();
    const page = usePage();
    const authUser = page.props?.auth?.user;
    const importInputRef = useRef(null);

    const warehouses = filterOptions.warehouses ?? [];
    const products = filterOptions.products ?? [];
    const voucherTypes = filterOptions.warehouseVoucherTypes ?? [
        { id: 'inbound', label: t('operations.voucher_entry.type_inbound') },
        { id: 'outbound', label: t('operations.voucher_entry.type_outbound') },
        { id: 'internal', label: t('operations.voucher_entry.type_internal') },
        { id: 'scrap', label: t('operations.voucher_entry.type_scrap') },
    ];

    const productById = useMemo(() => {
        const map = new Map();
        products.forEach((product) => map.set(String(product.id), product));
        return map;
    }, [products]);

    const [form, setForm] = useState(() => ({
        warehouse_id: String(voucher?.warehouse_id || defaults?.warehouse_id || ''),
        type: voucher?.type || 'inbound',
        code: voucher?.code || '',
        document_date: voucher?.document_date || todayIso(),
        partner: voucher?.partner || '',
        note: voucher?.note || '',
        deliverer_name: voucher?.deliverer_name || '',
        warehouse_keeper: voucher?.warehouse_keeper || '',
        chief_accountant: voucher?.chief_accountant || '',
        creator_name: voucher?.created_by || authUser?.name || '',
    }));
    const [lines, setLines] = useState(() => {
        const mapped = mapVoucherLines(voucher?.lines);
        if (mapped.length) return mapped;
        if (defaults?.product_id) {
            const product = products.find((item) => String(item.id) === String(defaults.product_id));
            return [{
                ...emptyLine(),
                product_id: String(defaults.product_id),
                unit_cost: Number(product?.cost_price || product?.unit_price || 0),
                sku: product?.sku,
                uom: product?.unit,
                product: product?.name,
            }];
        }
        return [];
    });
    const [pendingProductId, setPendingProductId] = useState('');
    const [pendingStockProductId, setPendingStockProductId] = useState('');
    const [status, setStatus] = useState(voucher?.status || 'draft');
    const [voucherId, setVoucherId] = useState(voucher?.id || null);
    const [createdAtLabel, setCreatedAtLabel] = useState(voucher?.created_at || '');
    const [busy, setBusy] = useState(false);
    const [boostBelow, setBoostBelow] = useState(0);
    const [boostAdd, setBoostAdd] = useState(1000);
    /** Pushsale-style column apply radios: when on, edits propagate to every line / new lines inherit last values. */
    const [applyFlags, setApplyFlags] = useState({
        quantity: false,
        unit_cost: false,
        batch_code: false,
        expiry_date: false,
        location_code: false,
    });

    useEffect(() => { if (pageRuntimeError) toast.error(pageRuntimeError); }, [pageRuntimeError]);

    const isConfirmed = status === 'confirmed';
    const title = voucherId
        ? `${schema?.title || t('operations.voucher_entry.title')} (${voucherId})`
        : (schema?.title || t('operations.voucher_entry.title'));

    const totals = useMemo(() => lines.reduce((acc, line) => {
        const qtyDoc = Number(line.document_quantity) || 0;
        const qty = Number(line.quantity) || 0;
        const cost = Number(line.unit_cost) || 0;
        acc.document_quantity += qtyDoc;
        acc.quantity += qty;
        acc.total += qty * cost;
        return acc;
    }, { document_quantity: 0, quantity: 0, total: 0 }), [lines]);

    const patchForm = (key, value) => setForm((prev) => ({ ...prev, [key]: value }));

    const patchLine = (key, field, value) => {
        setLines((prev) => {
            if (applyFlags[field]) {
                return prev.map((line) => ({ ...line, [field]: value }));
            }
            return prev.map((line) => (line.key === key ? { ...line, [field]: value } : line));
        });
    };

    const applyColumnLabelKey = (field) => {
        if (field === 'quantity') return 'col_qty';
        if (field === 'unit_cost') return 'col_unit_cost';
        if (field === 'batch_code') return 'col_batch';
        if (field === 'expiry_date') return 'col_expiry';
        if (field === 'location_code') return 'col_location';
        return 'col_note';
    };

    const toggleApplyFlag = (field) => {
        setApplyFlags((prev) => {
            const next = !prev[field];
            if (next) {
                const seed = lines.find((line) => String(line[field] ?? '').trim() !== '')?.[field]
                    ?? lines[0]?.[field]
                    ?? '';
                if (seed !== '' && seed !== undefined && seed !== null) {
                    setLines((rows) => rows.map((line) => (
                        field === 'quantity'
                            ? { ...line, quantity: seed, document_quantity: seed }
                            : { ...line, [field]: seed }
                    )));
                    toast.success(t('operations.voucher_entry.apply_column_ok', {
                        column: t(`operations.voucher_entry.${applyColumnLabelKey(field)}`),
                    }));
                }
            }
            return { ...prev, [field]: next };
        });
    };

    const renderApplyTick = (field) => (
        <label className="ps-voucher-col-apply" title={t('operations.voucher_entry.apply_column_hint')}>
            <input
                type="checkbox"
                className="ps-voucher-col-apply__tick"
                checked={Boolean(applyFlags[field])}
                disabled={isConfirmed || busy}
                onChange={() => toggleApplyFlag(field)}
            />
        </label>
    );

    const removeLine = (key) => setLines((prev) => prev.filter((line) => line.key !== key));

    const clearLines = async () => {
        if (!lines.length) return;
        const ok = await ask({
            title: t('operations.voucher_entry.confirm_clear_lines_title'),
            message: t('operations.voucher_entry.confirm_clear_lines'),
        });
        if (ok) setLines([]);
    };

    const addProductLine = (productId) => {
        const id = String(productId || '');
        if (!id) return;
        const product = productById.get(id);
        if (!product) {
            toast.error(t('operations.voucher_entry.product_required'));
            return;
        }
        const last = lines[lines.length - 1];
        const qtySeed = applyFlags.quantity && last ? Number(last.quantity) || 1 : 1;
        setLines((prev) => [
            ...prev,
            {
                ...emptyLine(),
                product_id: id,
                document_quantity: qtySeed,
                quantity: qtySeed,
                unit_cost: applyFlags.unit_cost && last
                    ? Number(last.unit_cost) || 0
                    : Number(product.cost_price || product.unit_price || 0),
                batch_code: applyFlags.batch_code && last ? (last.batch_code || '') : '',
                expiry_date: applyFlags.expiry_date && last ? (last.expiry_date || '') : '',
                location_code: applyFlags.location_code && last ? (last.location_code || '') : '',
                product: product.name,
                sku: product.sku,
                uom: product.unit,
            },
        ]);
        setPendingProductId('');
        setPendingStockProductId('');
    };

    const buildPayload = () => {
        const code = String(form.code || '').trim() || `${typePrefix(form.type)}-${Date.now()}`;
        return {
            warehouse_id: Number(form.warehouse_id),
            code,
            type: form.type,
            document_date: form.document_date || todayIso(),
            partner: form.partner || null,
            note: form.note || null,
            lines: lines.map((line) => ({
                product_id: Number(line.product_id),
                document_quantity: Number(line.document_quantity) || 0,
                quantity: Number(line.quantity) || 0,
                unit_cost: Number(line.unit_cost) || 0,
                batch_code: line.batch_code || null,
                expiry_date: line.expiry_date || null,
                location_code: line.location_code || null,
                note: line.note || null,
            })),
        };
    };

    const validateLocal = () => {
        if (!form.warehouse_id) return t('operations.voucher_entry.warehouse_required');
        if (!lines.length) return t('operations.voucher_entry.lines_required');
        if (lines.some((line) => !line.product_id)) return t('operations.voucher_entry.product_required');
        if (!lines.some((line) => Number(line.quantity) > 0)) return t('operations.voucher_entry.quantity_required');
        return '';
    };

    const applyVoucherResponse = (payload) => {
        const next = payload?.voucher || payload?.record || null;
        if (!next?.id) return;
        setVoucherId(next.id);
        setStatus(next.status || 'draft');
        setForm((prev) => ({
            ...prev,
            warehouse_id: String(next.warehouse_id || prev.warehouse_id),
            type: next.type || prev.type,
            code: next.code || prev.code,
            document_date: next.document_date || prev.document_date,
            partner: next.partner || '',
            note: next.note || '',
            creator_name: next.created_by || prev.creator_name,
        }));
        if (next.created_at) setCreatedAtLabel(next.created_at);
        if (Array.isArray(next.lines)) {
            setLines(mapVoucherLines(next.lines));
        }
        const nextUrl = `${routeUrl}?id=${next.id}`;
        if (typeof window !== 'undefined' && window.location.search !== `?id=${next.id}`) {
            window.history.replaceState({}, '', nextUrl);
        }
    };

    const persistDraft = async () => {
        const localError = validateLocal();
        if (localError) {
            toast.error(localError);
            throw new Error(localError);
        }
        const payload = buildPayload();
        if (!form.code) patchForm('code', payload.code);
        const body = voucherId
            ? await requestJson(`${routeUrl}/records/${voucherId}`, 'PUT', { payload })
            : await requestJson(`${routeUrl}/records`, 'POST', { payload });
        applyVoucherResponse(body);
        return body?.voucher || body?.record || null;
    };

    const saveDraft = async () => {
        setBusy(true);
        try {
            await persistDraft();
        } catch (exception) {
            toast.error(exception.message);
        } finally {
            setBusy(false);
        }
    };

    const completeVoucher = async () => {
        const ok = await ask({
            title: t('operations.voucher_entry.confirm_complete_title'),
            message: t('operations.voucher_entry.confirm_complete'),
        });
        if (!ok) return;

        setBusy(true);
        try {
            let currentId = voucherId;
            if (!isConfirmed) {
                const saved = await persistDraft();
                currentId = saved?.id || currentId;
            }
            if (!currentId) {
                throw new Error(t('operations.voucher_entry.lines_required'));
            }
            const body = await requestJson(`${routeUrl}/records/${currentId}/complete`, 'POST');
            applyVoucherResponse(body);
        } catch (exception) {
            toast.error(exception.message);
        } finally {
            setBusy(false);
        }
    };

    const deleteVoucher = async () => {
        if (!voucherId) {
            setForm({
                warehouse_id: '',
                type: 'inbound',
                code: '',
                document_date: todayIso(),
                partner: '',
                note: '',
                deliverer_name: '',
                warehouse_keeper: '',
                chief_accountant: '',
                creator_name: authUser?.name || '',
            });
            setLines([]);
            setStatus('draft');
            setCreatedAtLabel('');
            return;
        }
        const ok = await ask({
            title: t('operations.voucher_entry.confirm_delete_title'),
            message: t('operations.voucher_entry.confirm_delete'),
        });
        if (!ok) return;
        setBusy(true);
        try {
            await requestJson(`${routeUrl}/records/${voucherId}`, 'DELETE');
            router.visit('/admin/warehouse/vouchers');
        } catch (exception) {
            toast.error(exception.message);
            setBusy(false);
        }
    };

    const exportLines = () => {
        const headers = ['product_id', 'sku', 'quantity', 'document_quantity', 'unit_cost', 'batch_code', 'expiry_date', 'location_code', 'note'];
        const rows = lines.map((line) => {
            const product = productById.get(String(line.product_id));
            return [
                line.product_id,
                product?.sku || line.sku || '',
                line.quantity,
                line.document_quantity,
                line.unit_cost,
                line.batch_code,
                line.expiry_date,
                line.location_code,
                line.note,
            ];
        });
        const quote = (value) => `"${String(value ?? '').replaceAll('"', '""')}"`;
        const blob = new Blob([`\ufeff${[headers, ...rows].map((row) => row.map(quote).join(',')).join('\n')}`], {
            type: 'text/csv;charset=utf-8',
        });
        const url = URL.createObjectURL(blob);
        const anchor = document.createElement('a');
        anchor.href = url;
        anchor.download = `phieu-kho-${voucherId || 'moi'}.csv`;
        anchor.click();
        URL.revokeObjectURL(url);
    };

    const importLines = async (event) => {
        const file = event.target.files?.[0];
        event.target.value = '';
        if (!file) return;
        setBusy(true);
        try {
            const formData = new FormData();
            formData.append('file', file);
            if (voucherId) formData.append('voucher_id', String(voucherId));
            const body = await requestFormData(`${routeUrl}/import`, formData);
            if (body.voucher) {
                applyVoucherResponse(body);
            } else if (Array.isArray(body.lines)) {
                setLines(mapVoucherLines(body.lines.map((line) => {
                    const product = productById.get(String(line.product_id));
                    return {
                        ...line,
                        product: product?.name,
                        sku: product?.sku,
                        uom: product?.unit,
                    };
                })));
            }
        } catch (exception) {
            toast.error(exception.message);
        } finally {
            setBusy(false);
        }
    };

    const runBoostStock = async () => {
        if (!form.warehouse_id) {
            toast.error(t('operations.voucher_entry.warehouse_required'));
            return;
        }
        const ok = await ask({
            title: t('operations.voucher_entry.confirm_boost_title'),
            message: t('operations.voucher_entry.confirm_boost'),
        });
        if (!ok) return;
        setBusy(true);
        try {
            const body = await requestJson(`${routeUrl}/tester/boost-stock`, 'POST', {
                warehouse_id: Number(form.warehouse_id),
                below_quantity: Number(boostBelow) || 0,
                add_quantity: Number(boostAdd) || 1,
            });
            toast.success(body.message || t('operations.voucher_entry.boost_done'));
        } catch (exception) {
            toast.error(exception.message);
        } finally {
            setBusy(false);
        }
    };

    const runResetStock = async () => {
        if (!form.warehouse_id) {
            toast.error(t('operations.voucher_entry.warehouse_required'));
            return;
        }
        const ok = await ask({
            title: t('operations.voucher_entry.confirm_reset_title'),
            message: t('operations.voucher_entry.confirm_reset'),
        });
        if (!ok) return;
        setBusy(true);
        try {
            const body = await requestJson(`${routeUrl}/tester/reset-stock`, 'POST', {
                warehouse_id: Number(form.warehouse_id),
            });
            toast.success(body.message || t('operations.voucher_entry.reset_done'));
        } catch (exception) {
            toast.error(exception.message);
        } finally {
            setBusy(false);
        }
    };

    return (
        <AppLayout>
            <Head title={title} />
            <div className="pushsale-page ps-voucher-entry-page" data-page-code="5.3.1">
                <PushsalePageShell
                    title={title}
                    pageCode="5.3.1"
                    headerClassName="ps-voucher-entry-header"
                    actions={(
                        <button
                            type="button"
                            className="btn btn-sm btn-default ps-header-close-icon"
                            onClick={() => router.visit('/admin/warehouse/vouchers')}
                            aria-label={t('operations.voucher_entry.close')}
                            title={t('operations.voucher_entry.close')}
                        >
                            <i className="fa fa-close" aria-hidden="true" />
                        </button>
                    )}
                >
                    <div
                        id="dnn_ctr1658_Main_CapNhatPhieuXuatNhapKho_phInfo"
                        className="ps-voucher-entry-body box-body"
                    >
                        {/* Row 1 — Loại phiếu / Trạng thái | Phiếu tạm | Kho */}
                        <div className="row">
                            <div className="col-xs-2 form-group">
                                <span className="h-label">{t('operations.voucher_entry.type_status')}</span>
                            </div>
                            <div className="col-xs-2 form-group">
                                <select
                                    className="form-control"
                                    value={form.type}
                                    disabled={isConfirmed || busy}
                                    onChange={(event) => patchForm('type', event.target.value)}
                                >
                                    {voucherTypes.map((option) => (
                                        <option key={String(option.id)} value={String(option.value ?? option.id)}>
                                            {option.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="col-xs-2 form-group text-right">
                                <span className="h-text ps-voucher-entry-status">{statusLabel(status, t)}</span>
                            </div>
                            <div className="col-xs-2 form-group">
                                <span className="h-label">{t('operations.voucher_entry.warehouse')}</span>
                            </div>
                            <div className="col-xs-4 form-group">
                                <select
                                    className="form-control"
                                    value={form.warehouse_id}
                                    disabled={isConfirmed || busy}
                                    onChange={(event) => patchForm('warehouse_id', event.target.value)}
                                >
                                    <option value="">{t('operations.voucher_entry.select_warehouse')}</option>
                                    {warehouses.map((warehouse) => (
                                        <option key={warehouse.id} value={String(warehouse.id)}>
                                            {warehouse.label || warehouse.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>

                        {/* Row 2 — Mã phiếu / Ngày */}
                        <div className="row">
                            <div className="col-xs-2 form-group">
                                <span className="h-label">{t('operations.voucher_entry.code_date')}</span>
                            </div>
                            <div className="col-xs-2 form-group">
                                <input
                                    className="form-control ps-voucher-entry-code"
                                    value={form.code}
                                    disabled={isConfirmed || busy}
                                    onChange={(event) => patchForm('code', event.target.value.toUpperCase())}
                                />
                            </div>
                            <div className="col-xs-2 form-group">
                                <input
                                    type="date"
                                    className="form-control"
                                    value={form.document_date}
                                    disabled={isConfirmed || busy}
                                    onChange={(event) => patchForm('document_date', event.target.value)}
                                />
                            </div>
                            <div className="col-xs-2 form-group" />
                            <div className="col-xs-4 form-group" />
                        </div>

                        {/* Row 3 — Họ tên người giao | Nhà cung cấp */}
                        <div className="row">
                            <div className="col-xs-2 form-group">
                                <span className="h-label">{t('operations.voucher_entry.deliverer')}</span>
                            </div>
                            <div className="col-xs-4 form-group">
                                <input
                                    className="form-control"
                                    value={form.deliverer_name}
                                    disabled={isConfirmed || busy}
                                    onChange={(event) => patchForm('deliverer_name', event.target.value)}
                                />
                            </div>
                            <div className="col-xs-2 form-group">
                                <span className="h-label">{t('operations.voucher_entry.partner')}</span>
                            </div>
                            <div className="col-xs-4 form-group">
                                <input
                                    className="form-control"
                                    value={form.partner}
                                    disabled={isConfirmed || busy}
                                    onChange={(event) => patchForm('partner', event.target.value)}
                                />
                            </div>
                        </div>

                        {/* Row 4 — Người lập phiếu | Thủ kho */}
                        <div className="row">
                            <div className="col-xs-2 form-group">
                                <span className="h-label">{t('operations.voucher_entry.creator')}</span>
                            </div>
                            <div className="col-xs-4 form-group">
                                <input className="form-control" value={form.creator_name} disabled readOnly />
                            </div>
                            <div className="col-xs-2 form-group">
                                <span className="h-label">{t('operations.voucher_entry.warehouse_keeper')}</span>
                            </div>
                            <div className="col-xs-4 form-group">
                                <input
                                    className="form-control"
                                    value={form.warehouse_keeper}
                                    disabled={isConfirmed || busy}
                                    onChange={(event) => patchForm('warehouse_keeper', event.target.value)}
                                />
                            </div>
                        </div>

                        {/* Row 5 — Kế toán trưởng | Ghi chú */}
                        <div className="row">
                            <div className="col-xs-2 form-group">
                                <span className="h-label">{t('operations.voucher_entry.chief_accountant')}</span>
                            </div>
                            <div className="col-xs-4 form-group">
                                <input
                                    className="form-control"
                                    value={form.chief_accountant}
                                    disabled={isConfirmed || busy}
                                    onChange={(event) => patchForm('chief_accountant', event.target.value)}
                                />
                            </div>
                            <div className="col-xs-2 form-group">
                                <span className="h-label">{t('operations.voucher_entry.note')}</span>
                            </div>
                            <div className="col-xs-4 form-group">
                                <input
                                    className="form-control"
                                    value={form.note}
                                    disabled={isConfirmed || busy}
                                    onChange={(event) => patchForm('note', event.target.value)}
                                />
                            </div>
                        </div>

                        {/* Row 6 — Sản phẩm + Import/Xuất Excel */}
                        <div className="row">
                            <div className="col-xs-2 form-group">
                                <span className="h-label">{t('operations.voucher_entry.product')}</span>
                            </div>
                            <div className="col-xs-4 form-group">
                                <ProductSearchSelect
                                    products={products}
                                    value={pendingProductId}
                                    disabled={isConfirmed || busy}
                                    placeholder={t('operations.voucher_entry.select_product')}
                                    onChange={(value) => {
                                        setPendingProductId(value);
                                        if (value) addProductLine(value);
                                    }}
                                />
                            </div>
                            <div className="col-xs-2 form-group">
                                <span className="h-label">&nbsp;</span>
                            </div>
                            <div className="col-xs-2 form-group">
                                <a
                                    href="#import-excel"
                                    className="ps-voucher-excel-link hidden-print"
                                    onClick={(event) => {
                                        event.preventDefault();
                                        if (!isConfirmed && !busy) importInputRef.current?.click();
                                    }}
                                >
                                    <i className="fa fa-cloud-download" aria-hidden="true" />
                                    {' '}
                                    {t('operations.voucher_entry.import_excel')}
                                </a>
                            </div>
                            <div className="col-xs-2 form-group">
                                <a
                                    href="#export-excel"
                                    className="ps-voucher-excel-link"
                                    onClick={(event) => {
                                        event.preventDefault();
                                        if (!busy) exportLines();
                                    }}
                                    title={t('operations.voucher_entry.export_excel')}
                                >
                                    <i className="fa fa-file-excel-o" aria-hidden="true" />
                                    {' '}
                                    {t('operations.voucher_entry.export_excel')}
                                </a>
                                <input
                                    ref={importInputRef}
                                    type="file"
                                    accept=".csv,text/csv"
                                    className="hidden"
                                    onChange={importLines}
                                />
                            </div>
                        </div>

                        {/* Row 7 — Sản phẩm trong kho */}
                        <div className="row">
                            <div className="col-xs-2 form-group">
                                <span className="h-label">{t('operations.voucher_entry.stock_product')}</span>
                            </div>
                            <div className="col-xs-4 form-group">
                                <ProductSearchSelect
                                    products={products}
                                    value={pendingStockProductId}
                                    disabled={isConfirmed || busy}
                                    placeholder={t('operations.voucher_entry.select_stock_product')}
                                    onChange={(value) => {
                                        setPendingStockProductId(value);
                                        if (value) addProductLine(value);
                                    }}
                                />
                            </div>
                        </div>

                        {/* Row 8 — table */}
                        <div className="row">
                            <div className="col-xs-12 form-group">
                                <div className="ps-voucher-entry-table-wrap dragscroll1 tableFixHead">
                                    <table className="table table-bordered ps-voucher-entry-table pushsale-data-table" id="tableReportSale">
                                        <thead>
                                            <tr className="ps-voucher-head-labels">
                                                <th style={{ width: 50 }}>{t('operations.voucher_entry.col_index')}</th>
                                                <th className="no-wrap" style={{ minWidth: 130 }}>{t('operations.voucher_entry.col_product')}</th>
                                                <th className="no-wrap text-center" style={{ width: 100 }}>{t('operations.voucher_entry.col_sku')}</th>
                                                <th className="no-wrap text-center" style={{ width: 60 }}>{t('operations.voucher_entry.col_uom')}</th>
                                                <th className="no-wrap text-center" style={{ width: 70 }}>{t('operations.voucher_entry.col_doc_qty')}</th>
                                                <th className="no-wrap text-center" style={{ width: 70 }}>{t('operations.voucher_entry.col_qty')}</th>
                                                <th className="no-wrap text-center" style={{ width: 90 }}>{t('operations.voucher_entry.col_unit_cost')}</th>
                                                <th className="no-wrap text-center" style={{ width: 100 }}>{t('operations.voucher_entry.col_total')}</th>
                                                <th className="no-wrap text-center" style={{ width: 80 }}>{t('operations.voucher_entry.col_batch')}</th>
                                                <th className="no-wrap text-center" style={{ width: 100 }}>{t('operations.voucher_entry.col_expiry')}</th>
                                                <th className="no-wrap text-center">{t('operations.voucher_entry.col_location')}</th>
                                                <th className="no-wrap text-center">{t('operations.voucher_entry.col_note')}</th>
                                                <th className="no-wrap text-center hidden-print" style={{ width: 40 }} />
                                            </tr>
                                            {/* Tick apply-all: second header row under labels (not inline with titles) */}
                                            <tr className="ps-voucher-apply-row">
                                                <th colSpan={6} />
                                                <th className="text-center">{renderApplyTick('unit_cost')}</th>
                                                <th />
                                                <th className="text-center">{renderApplyTick('batch_code')}</th>
                                                <th className="text-center">{renderApplyTick('expiry_date')}</th>
                                                <th colSpan={3} />
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {lines.length === 0 ? (
                                                <tr>
                                                    <td colSpan={13} className="ps-voucher-entry-empty">
                                                        {t('operations.voucher_entry.empty_lines')}
                                                    </td>
                                                </tr>
                                            ) : lines.map((line, index) => {
                                                const product = productById.get(String(line.product_id));
                                                const total = (Number(line.quantity) || 0) * (Number(line.unit_cost) || 0);
                                                return (
                                                    <tr key={line.key}>
                                                        <td className="text-center">{index + 1}</td>
                                                        <td>{product?.name || line.product || '—'}</td>
                                                        <td className="text-center">{product?.sku || line.sku || ''}</td>
                                                        <td className="text-center">{product?.unit || line.uom || ''}</td>
                                                        <td>
                                                            <input
                                                                type="number"
                                                                min="0"
                                                                className="form-control text-right"
                                                                value={line.document_quantity}
                                                                disabled={isConfirmed || busy}
                                                                onChange={(event) => patchLine(line.key, 'document_quantity', event.target.value)}
                                                            />
                                                        </td>
                                                        <td>
                                                            <input
                                                                type="number"
                                                                min="0"
                                                                className="form-control text-right"
                                                                value={line.quantity}
                                                                disabled={isConfirmed || busy}
                                                                onChange={(event) => patchLine(line.key, 'quantity', event.target.value)}
                                                            />
                                                        </td>
                                                        <td>
                                                            <input
                                                                type="number"
                                                                min="0"
                                                                className="form-control text-right"
                                                                value={line.unit_cost}
                                                                disabled={isConfirmed || busy}
                                                                onChange={(event) => patchLine(line.key, 'unit_cost', event.target.value)}
                                                            />
                                                        </td>
                                                        <td className="text-right">{numberFmt.format(total)}</td>
                                                        <td>
                                                            <input
                                                                className="form-control"
                                                                value={line.batch_code}
                                                                disabled={isConfirmed || busy}
                                                                onChange={(event) => patchLine(line.key, 'batch_code', event.target.value.toUpperCase())}
                                                            />
                                                        </td>
                                                        <td>
                                                            <input
                                                                type="date"
                                                                className="form-control"
                                                                value={line.expiry_date || ''}
                                                                disabled={isConfirmed || busy}
                                                                onChange={(event) => patchLine(line.key, 'expiry_date', event.target.value)}
                                                            />
                                                        </td>
                                                        <td>
                                                            <input
                                                                className="form-control"
                                                                value={line.location_code}
                                                                disabled={isConfirmed || busy}
                                                                onChange={(event) => patchLine(line.key, 'location_code', event.target.value.toUpperCase())}
                                                            />
                                                        </td>
                                                        <td>
                                                            <input
                                                                className="form-control"
                                                                value={line.note}
                                                                disabled={isConfirmed || busy}
                                                                onChange={(event) => patchLine(line.key, 'note', event.target.value)}
                                                            />
                                                        </td>
                                                        <td className="text-center hidden-print">
                                                            <button
                                                                type="button"
                                                                className="btn-icon text-orange"
                                                                disabled={isConfirmed || busy}
                                                                onClick={() => removeLine(line.key)}
                                                                aria-label={t('operations.voucher_entry.remove_line')}
                                                            >
                                                                <i className="fa fa-trash" aria-hidden="true" />
                                                            </button>
                                                        </td>
                                                    </tr>
                                                );
                                            })}
                                            <tr className="ps-voucher-entry-total-row">
                                                <td colSpan={4} className="text-right">{t('operations.voucher_entry.total')}</td>
                                                <td className="text-right">{numberFmt.format(totals.document_quantity)}</td>
                                                <td className="text-right">{numberFmt.format(totals.quantity)}</td>
                                                <td />
                                                <td className="text-right">{numberFmt.format(totals.total)}</td>
                                                <td colSpan={4} />
                                                <td className="text-center hidden-print">
                                                    <button
                                                        type="button"
                                                        className="btn-icon text-orange"
                                                        disabled={isConfirmed || busy || !lines.length}
                                                        onClick={clearLines}
                                                        title={t('operations.voucher_entry.clear_lines')}
                                                    >
                                                        <i className="fa fa-trash" aria-hidden="true" />
                                                    </button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        {/* Footer — notice + actions + tester tools */}
                        <div className="row ps-voucher-entry-row--footer">
                            <div className="col-xs-6 form-group text-left">
                                <div className="notice ps-voucher-entry-notice hidden-print">
                                    - {t('operations.voucher_entry.notice_confirmed')}
                                    <br />
                                    - {t('operations.voucher_entry.notice_import')}
                                </div>
                                {(createdAtLabel || form.creator_name) ? (
                                    <span className="ps-voucher-entry-logs">
                                        <br />
                                        - {t('operations.voucher_entry.created_meta', {
                                            user: form.creator_name || '—',
                                            time: createdAtLabel || '—',
                                        })}
                                    </span>
                                ) : null}
                            </div>
                            <div className="col-xs-6 form-group text-right hidden-print">
                                <div className="ps-voucher-entry-footer-actions">
                                    <button
                                        type="button"
                                        className="btn btn-sm btn-danger mr15"
                                        disabled={busy || isConfirmed}
                                        onClick={deleteVoucher}
                                    >
                                        <i className="fa fa-trash" aria-hidden="true" /> {t('operations.voucher_entry.delete')}
                                    </button>
                                    <button
                                        type="button"
                                        className="btn btn-sm btn-success mr15"
                                        disabled={busy || isConfirmed}
                                        onClick={completeVoucher}
                                    >
                                        <i className="fa fa-check-circle-o" aria-hidden="true" /> {t('operations.voucher_entry.complete')}
                                    </button>
                                    <button
                                        type="button"
                                        className="btn btn-sm btn-primary"
                                        disabled={busy || isConfirmed}
                                        onClick={saveDraft}
                                    >
                                        <i className="fa fa-save" aria-hidden="true" /> {t('operations.voucher_entry.save')}
                                    </button>
                                    <button
                                        type="button"
                                        className="btn btn-sm btn-primary btn-in mr15"
                                        disabled={busy}
                                        onClick={() => window.print()}
                                    >
                                        <i className="fa fa-print" aria-hidden="true" /> {t('operations.voucher_entry.print')}
                                    </button>
                                </div>

                                {canTesterTools ? (
                                    <div className="ps-voucher-entry-tester">
                                        <div className="row">
                                            <div className="col-xs-2">
                                                <span className="h-label">{t('operations.voucher_entry.boost_below')}</span>
                                            </div>
                                            <div className="col-xs-2">
                                                <input
                                                    type="number"
                                                    className="form-control"
                                                    value={boostBelow}
                                                    onChange={(event) => setBoostBelow(event.target.value)}
                                                />
                                            </div>
                                            <div className="col-xs-2">
                                                <span className="h-label">{t('operations.voucher_entry.boost_add')}</span>
                                            </div>
                                            <div className="col-xs-3">
                                                <input
                                                    type="number"
                                                    className="form-control"
                                                    value={boostAdd}
                                                    onChange={(event) => setBoostAdd(event.target.value)}
                                                />
                                            </div>
                                            <div className="col-xs-3">
                                                <button
                                                    type="button"
                                                    className="btn btn-sm btn-primary"
                                                    disabled={busy}
                                                    onClick={runBoostStock}
                                                    title={t('operations.voucher_entry.boost_stock')}
                                                >
                                                    <i className="fa fa-plus" aria-hidden="true" /> {t('operations.voucher_entry.boost_stock')}
                                                </button>
                                            </div>
                                        </div>
                                        <div className="ps-voucher-entry-tester-reset">
                                            <span className="ps-voucher-entry-tester-hint">{t('operations.voucher_entry.reset_hint')}</span>
                                            <button
                                                type="button"
                                                className="btn btn-sm btn-primary"
                                                disabled={busy}
                                                onClick={runResetStock}
                                                title={t('operations.voucher_entry.reset_stock')}
                                            >
                                                <i className="fa fa-refresh" aria-hidden="true" /> {t('operations.voucher_entry.reset_stock')}
                                            </button>
                                        </div>
                                    </div>
                                ) : null}
                            </div>
                        </div>
                    </div>
                </PushsalePageShell>
            </div>
        </AppLayout>
    );
}
