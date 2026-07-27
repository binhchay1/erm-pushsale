import { Head, Link, router, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';

import { PushsalePagination } from '@/components/pagination/PushsalePagination';
import { CurrencyInput } from '@/components/ui/currency-input';
import { PageHeader } from '@/components/layout/PageHeader';
import AppLayout from '@/layouts/AppLayout';
import { PushsaleDialog } from '@/components/ui/pushsale-dialog';
import { ConfirmActionDialog } from '@/components/ui/ConfirmActionDialog';
import { formatCurrency, formatNumber } from '@/lib/format';
import { PushsaleMultiSelect, PushsaleSelect } from '@/components/pushsale/PushsaleSelect';

function DialogShell({ title, open, onClose, children, wide = false, hiddenHeader = false, className = '', bodyClassName = '', showClose = true }) {
    const isTaxonomy = String(className).includes('ps-taxonomy-source-modal');

    return (
        <PushsaleDialog
            open={open}
            onOpenChange={(nextOpen) => !nextOpen && onClose()}
            title={title}
            width={isTaxonomy ? 'calc(100vw - 8px)' : (wide ? '98vw' : '900px')}
            className={className}
            headerClassName={hiddenHeader ? 'sr-only' : undefined}
            bodyClassName={`ps-source-dialog-body ${bodyClassName}`}
            showClose={showClose}
            contentProps={isTaxonomy ? { style: { position: 'fixed', inset: '4px', left: '4px', top: '4px', right: '4px', bottom: '4px', transform: 'none', maxWidth: 'calc(100vw - 8px)', width: 'calc(100vw - 8px)', height: 'calc(100dvh - 8px)', maxHeight: 'calc(100dvh - 8px)', display: 'flex', flexDirection: 'column', padding: 0, overflow: 'hidden' } } : undefined}
        >
            {children}
        </PushsaleDialog>
    );
}

function currentFilters() {
    if (typeof window === 'undefined') return {};
    return Object.fromEntries(new URLSearchParams(window.location.search).entries());
}


function normalizeOptionText(value) {
    return String(value ?? '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
}

function OptionSearchSelect({ options = [], value = '', onChange, placeholder = '--Chọn--', disabled = false, className = '' }) {
    const [open, setOpen] = useState(false);
    const [keyword, setKeyword] = useState('');
    const rootRef = useRef(null);
    const selected = options.find((option) => String(option.value) === String(value));
    const filtered = useMemo(() => {
        const needle = normalizeOptionText(keyword);
        if (!needle) return options;
        return options.filter((option) => normalizeOptionText(`${option.label} ${option.subLabel ?? ''}`).includes(needle));
    }, [keyword, options]);

    useEffect(() => {
        if (!open) return undefined;
        const close = (event) => {
            if (!rootRef.current?.contains(event.target)) setOpen(false);
        };
        document.addEventListener('mousedown', close);
        return () => document.removeEventListener('mousedown', close);
    }, [open]);

    return (
        <div ref={rootRef} className={`ps-search-select ${className}`.trim()}>
            <button type="button" className="form-control ps-search-select-button" disabled={disabled} onClick={() => setOpen((current) => !current)}>
                <span>{selected?.label || placeholder}</span><i className="fa fa-caret-down" />
            </button>
            {open && !disabled && (
                <div className="ps-search-select-menu">
                    <input className="form-control ps-search-select-input" autoFocus value={keyword} placeholder="Tìm theo tên, mã..." onChange={(event) => setKeyword(event.target.value)} />
                    <button type="button" className="ps-search-select-option is-empty" onClick={() => { onChange(''); setOpen(false); setKeyword(''); }}>{placeholder}</button>
                    <div className="ps-search-select-options">
                        {filtered.length ? filtered.map((option) => (
                            <button key={option.value} type="button" className={`ps-search-select-option ${String(option.value) === String(value) ? 'active' : ''}`} onClick={() => { onChange(option.value); setOpen(false); setKeyword(''); }}>
                                <span>{option.label}</span>{option.subLabel && <small>{option.subLabel}</small>}
                            </button>
                        )) : <div className="ps-search-select-empty">Không có dữ liệu.</div>}
                    </div>
                </div>
            )}
        </div>
    );
}


function toIntArray(value) {
    if (!Array.isArray(value)) return [];
    return value.map((item) => Number(item)).filter((item, index, source) => item > 0 && source.indexOf(item) === index);
}

function idsEqual(left, right) {
    const a = toIntArray(left).sort((x, y) => x - y);
    const b = toIntArray(right).sort((x, y) => x - y);
    return a.length === b.length && a.every((value, index) => value === b[index]);
}

function PermissionMultiSelect({ label, enabled, selectedIds = [], options = [], onEnabledChange, onChange, placeholder }) {
    const [open, setOpen] = useState(false);
    const [keyword, setKeyword] = useState('');
    const rootRef = useRef(null);
    const ids = toIntArray(selectedIds);
    const allSelected = enabled && ids.length === 0;
    const optionMap = useMemo(() => new Map(options.map((option) => [Number(option.value), option])), [options]);
    const filtered = useMemo(() => {
        const needle = normalizeOptionText(keyword);
        if (!needle) return options;
        return options.filter((option) => normalizeOptionText(`${option.label} ${option.subLabel ?? ''}`).includes(needle));
    }, [keyword, options]);

    useEffect(() => {
        if (!open) return undefined;
        const close = (event) => {
            if (!rootRef.current?.contains(event.target)) setOpen(false);
        };
        document.addEventListener('mousedown', close);
        return () => document.removeEventListener('mousedown', close);
    }, [open]);

    const displayText = (() => {
        if (!enabled) return '';
        if (allSelected) return `Tất cả ${label} đều có quyền`;
        const names = ids.map((id) => optionMap.get(id)?.label).filter(Boolean);
        if (names.length <= 2) return names.join(', ');
        return `${names.slice(0, 2).join(', ')} +${names.length - 2}`;
    })();

    const toggleId = (id) => {
        const numberId = Number(id);
        const next = ids.includes(numberId) ? ids.filter((item) => item !== numberId) : [...ids, numberId];
        onEnabledChange(next.length > 0 || enabled);
        onChange(next);
    };

    return (
        <div ref={rootRef} className="ps-permission-select">
            <button type="button" className={`form-control ps-permission-select-button ${!enabled ? 'is-empty' : ''}`} onClick={() => setOpen((current) => !current)}>
                <span>{displayText || placeholder || `--Chọn ${label}--`}</span><i className="fa fa-caret-down" />
            </button>
            {open && (
                <div className="ps-permission-select-menu">
                    <input className="form-control ps-permission-search" autoFocus value={keyword} placeholder={`Tìm ${label.toLowerCase()}...`} onChange={(event) => setKeyword(event.target.value)} />
                    <button type="button" className={`ps-permission-option ${allSelected ? 'active' : ''}`} onClick={() => { onEnabledChange(true); onChange([]); setKeyword(''); }}>
                        <span>Tất cả {label} đều có quyền</span>
                    </button>
                    <button type="button" className={`ps-permission-option ${!enabled ? 'active' : ''}`} onClick={() => { onEnabledChange(false); onChange([]); setKeyword(''); }}>
                        <span>Không cho {label} sử dụng sản phẩm này</span>
                    </button>
                    <div className="ps-permission-options">
                        {filtered.length ? filtered.map((option) => {
                            const checked = ids.includes(Number(option.value));
                            return (
                                <button key={option.value} type="button" className={`ps-permission-option ${checked ? 'active' : ''}`} onClick={() => toggleId(option.value)}>
                                    <input type="checkbox" readOnly checked={checked} />
                                    <span>{option.label}{option.subLabel && <small>{option.subLabel}</small>}</span>
                                </button>
                            );
                        }) : <div className="ps-search-select-empty">Không có dữ liệu.</div>}
                    </div>
                </div>
            )}
        </div>
    );
}

const emptyProduct = {
    name: '', sku: '', unit: '', cost_price: 0, unit_price: 0, vat_percent: 0,
    vat_code: 'KCT', barcode: '', weight_grams: 0, length_cm: 0, width_cm: 0, height_cm: 0,
    warehouse_location: '', is_active: true, available_marketing: true, available_sale: true,
    available_care: true, marketing_team_ids: [], marketing_user_ids: [], sale_team_ids: [], sale_user_ids: [],
    care_team_ids: [], care_user_ids: [], category_ids: [], attribute_ids: ['', ''], attribute_value_ids: [], type: 'product', has_attributes: false,
};

const emptyTaxonomy = { id: '', name: '', product_attribute_id: '', is_active: true, clear_after_save: true, support_update: false };
const currency = (value) => (Number(value) ? formatCurrency(value) : '');

function taxonomyMeta(type) {
    return {
        category: {
            title: 'Danh sách phân loại',
            updateTitle: 'Cập nhật',
            searchPlaceholder: '',
            storeUrl: '/admin/products/categories',
            label: 'Tên',
            editTitle: 'Chỉnh sửa danh mục',
            deleteTitle: 'Xóa danh mục',
            deleteMessage: 'Chắc chắn bạn muốn xóa danh mục này?',
        },
        attribute: {
            title: 'Danh sách thuộc tính sản phẩm',
            updateTitle: 'Cập nhật',
            searchPlaceholder: '',
            storeUrl: '/admin/products/attributes',
            label: 'Tên',
            editTitle: 'Chỉnh sửa thuộc tính sản phẩm',
            deleteTitle: 'Xóa thuộc tính sản phẩm',
            updateNote: 'Chỉnh sửa tên thuộc tính sẽ tự động cập nhật các sản phẩm liên quan',
            deleteMessage: 'Chắc chắn bạn muốn xóa thuộc tính này?',
        },
        value: {
            title: 'Danh sách giá trị thuộc tính',
            updateTitle: 'Cập nhật',
            searchPlaceholder: '',
            storeUrl: '/admin/products/attribute-values',
            label: 'Tên',
            editTitle: 'Chỉnh sửa giá trị thuộc tính',
            deleteTitle: 'Xóa giá trị thuộc tính',
            updateNote: 'Chỉnh sửa tên giá trị thuộc tính sẽ tự động cập nhật các sản phẩm liên quan',
            deleteMessage: 'Chắc chắn bạn muốn xóa giá trị thuộc tính này?',
        },
    }[type] ?? null;
}

function cleanTaxonomyPayload(data, taxonomy) {
    const payload = {
        name: data.name,
        is_active: Boolean(data.is_active),
    };

    if (taxonomy === 'value') {
        payload.product_attribute_id = data.product_attribute_id;
    }

    return payload;
}

export default function ProductsIndex({ products, filters = {}, categories = [], attributes = [], attributeValues = [], vatCodes = [], permissionOptions = {} }) {
    const [query, setQuery] = useState({
        search: filters.search ?? '', active: filters.active ?? '', category_id: filters.category_id ?? '',
        marketing: filters.marketing ?? '', sale: filters.sale ?? '', care: filters.care ?? '',
        vat: filters.vat ?? '', sort: filters.sort ?? 'newest',
    });
    const [selected, setSelected] = useState(new Set());
    const [confirmAction, setConfirmAction] = useState(null);
    const [productOpen, setProductOpen] = useState(false);
    const [editingId, setEditingId] = useState(null);
    const [taxonomy, setTaxonomy] = useState(null);
    const [taxonomySearch, setTaxonomySearch] = useState('');
    const [taxonomyAttributeFilter, setTaxonomyAttributeFilter] = useState('');
    const [taxonomyPage, setTaxonomyPage] = useState(1);
    const [statusUpdating, setStatusUpdating] = useState(null);
    const productForm = useForm(emptyProduct);
    const taxonomyForm = useForm(emptyTaxonomy);
    const rows = products?.data ?? [];
    const marketingTeams = permissionOptions.marketingTeams ?? [];
    const marketingUsers = permissionOptions.marketingUsers ?? [];
    const saleTeams = permissionOptions.saleTeams ?? [];
    const saleUsers = permissionOptions.saleUsers ?? [];
    const careTeams = permissionOptions.careTeams ?? [];
    const careUsers = permissionOptions.careUsers ?? [];

    const setPermissionEnabled = (scope, enabled) => {
        productForm.setData(`available_${scope}`, Boolean(enabled));
    };

    const setPermissionUsers = (scope, ids) => {
        const userKey = `${scope}_user_ids`;
        productForm.setData(userKey, toIntArray(ids));
    };

    const setPermissionTeams = (scope, ids, teams, fillMembers = true) => {
        const teamIds = toIntArray(ids);
        const userIds = fillMembers
            ? teams.filter((team) => teamIds.includes(Number(team.value))).flatMap((team) => team.member_ids ?? [])
            : productForm.data[`${scope}_user_ids`] ?? [];

        productForm.setData({
            ...productForm.data,
            [`${scope}_team_ids`]: teamIds,
            [`${scope}_user_ids`]: toIntArray(userIds),
            [`available_${scope}`]: true,
        });
    };

    const resetPermission = (scope) => {
        productForm.setData({
            ...productForm.data,
            [`${scope}_team_ids`]: [],
            [`${scope}_user_ids`]: [],
            [`available_${scope}`]: false,
        });
    };

    const refillPermissionFromTeam = (scope, teams) => {
        const teamIds = toIntArray(productForm.data[`${scope}_team_ids`]);
        if (teamIds.length === 0) {
            productForm.setData({
                ...productForm.data,
                [`${scope}_user_ids`]: [],
                [`available_${scope}`]: true,
            });
            return;
        }

        const userIds = teams.filter((team) => teamIds.includes(Number(team.value))).flatMap((team) => team.member_ids ?? []);
        productForm.setData({
            ...productForm.data,
            [`${scope}_user_ids`]: toIntArray(userIds),
            [`available_${scope}`]: true,
        });
    };


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
            barcode: row.barcode ?? '', weight_grams: row.weight_grams ?? 0, length_cm: row.length_cm ?? 0,
            width_cm: row.width_cm ?? 0, height_cm: row.height_cm ?? 0, warehouse_location: row.warehouse_location ?? '',
            is_active: row.is_active, available_marketing: row.available_marketing, available_sale: row.available_sale,
            available_care: row.available_care,
            marketing_team_ids: toIntArray(row.marketing_team_ids), marketing_user_ids: toIntArray(row.marketing_user_ids),
            sale_team_ids: toIntArray(row.sale_team_ids), sale_user_ids: toIntArray(row.sale_user_ids),
            care_team_ids: toIntArray(row.care_team_ids), care_user_ids: toIntArray(row.care_user_ids),
            category_ids: row.category_ids ?? [],
            attribute_ids: attributeValues
                .filter((value) => (row.attribute_value_ids ?? []).map(String).includes(String(value.id)))
                .map((value) => String(value.product_attribute_id))
                .filter((value, index, source) => source.indexOf(value) === index)
                .slice(0, 2)
                .concat(['', ''])
                .slice(0, 2),
            attribute_value_ids: row.attribute_value_ids ?? [], type: 'product', has_attributes: (row.attribute_value_ids ?? []).length > 0,
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

    const openTaxonomy = (nextTaxonomy) => {
        taxonomyForm.setData(emptyTaxonomy);
        taxonomyForm.clearErrors();
        setTaxonomySearch('');
        setTaxonomyAttributeFilter('');
        setTaxonomyPage(1);
        setTaxonomy(nextTaxonomy);
    };

    const editTaxonomy = (row) => {
        taxonomyForm.setData({
            ...emptyTaxonomy,
            id: row.id,
            name: row.name ?? '',
            product_attribute_id: row.product_attribute_id ?? '',
            is_active: row.is_active ?? true,
        });
        taxonomyForm.clearErrors();
    };

    const resetTaxonomy = () => {
        taxonomyForm.setData(emptyTaxonomy);
        taxonomyForm.clearErrors();
    };

    const saveTaxonomy = (event) => {
        event.preventDefault();
        const meta = taxonomyMeta(taxonomy);
        if (!meta) return;

        const payload = cleanTaxonomyPayload(taxonomyForm.data, taxonomy);
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                if (taxonomyForm.data.clear_after_save) {
                    resetTaxonomy();
                }
            },
        };

        if (taxonomyForm.data.id) {
            router.patch(`${meta.storeUrl}/${taxonomyForm.data.id}`, payload, options);
            return;
        }

        router.post(meta.storeUrl, payload, options);
    };

    const performRemoveTaxonomy = (row) => {
        const meta = taxonomyMeta(taxonomy);
        if (!meta || !row?.id) return;
        router.delete(`${meta.storeUrl}/${row.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                if (String(taxonomyForm.data.id) === String(row.id)) resetTaxonomy();
            },
        });
    };

    const removeTaxonomy = (row) => {
        const meta = taxonomyMeta(taxonomy);
        if (!meta || !row?.id) return;
        setConfirmAction({
            title: 'Xóa cấu hình sản phẩm?',
            description: `Xóa "${row.name ?? meta.label}"? Những sản phẩm đang gắn cấu hình này có thể bị ảnh hưởng.`,
            onConfirm: () => performRemoveTaxonomy(row),
        });
    };

    const toggleSelected = (id) => setSelected((current) => {
        const next = new Set(current);
        next.has(id) ? next.delete(id) : next.add(id);
        return next;
    });

    const performDeleteSelected = () => {
        [...selected].forEach((id) => router.delete(`/admin/products/${id}`, { preserveScroll: true, onFinish: () => setSelected(new Set()) }));
    };

    const deleteSelected = () => {
        if (!selected.size) return;
        setConfirmAction({
            title: 'Xóa các sản phẩm đã chọn?',
            description: `Bạn đang xóa ${selected.size} sản phẩm. Đơn hàng và báo cáo cũ vẫn giữ lịch sử, nhưng cấu hình sản phẩm sẽ không còn dùng tiếp.`,
            onConfirm: performDeleteSelected,
        });
    };

    const toggleBusinessStatus = (row, event) => {
        if (!row?.id || statusUpdating === row.id) return;

        const stopBusiness = event.target.checked;
        setStatusUpdating(row.id);
        router.patch(`/admin/products/${row.id}/business-status`, { is_active: !stopBusiness }, {
            preserveScroll: true,
            preserveState: false,
            onFinish: () => setStatusUpdating(null),
        });
    };

    const exportCsv = () => {
        const headers = ['ID', 'Phân loại', 'Tên', 'Mã sản phẩm', 'Đơn vị', 'Giá nhập', 'Đơn giá', 'VAT', 'Mã VAT', 'Mã vạch', 'Khối lượng', 'Dài', 'Rộng', 'Cao', 'Mã vị trí', 'Ngừng KD', 'Marketing', 'Sale', 'CSKH'];
        const lines = rows.map((row) => [row.id, row.category_names, row.name, row.sku, row.unit, row.cost_price, row.unit_price, row.vat_percent, row.vat_code, row.barcode, row.weight_grams, row.length_cm, row.width_cm, row.height_cm, row.warehouse_location, !row.is_active, row.available_marketing, row.available_sale, row.available_care]);
        const quote = (value) => `"${String(value ?? '').replaceAll('"', '""')}"`;
        const blob = new Blob(['\ufeff' + [headers, ...lines].map((line) => line.map(quote).join(',')).join('\n')], { type: 'text/csv;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const anchor = document.createElement('a'); anchor.href = url; anchor.download = 'danh-sach-san-pham.csv'; anchor.click(); URL.revokeObjectURL(url);
    };

    const allSelected = useMemo(() => rows.length > 0 && rows.every((row) => selected.has(row.id)), [rows, selected]);
    const activeTaxonomyMeta = taxonomyMeta(taxonomy);
    const taxonomyRows = useMemo(() => {
        const source = taxonomy === 'category' ? categories : taxonomy === 'attribute' ? attributes : attributeValues;
        const keyword = taxonomySearch.trim().toLowerCase();

        return source.filter((row) => {
            if (taxonomy === 'value' && taxonomyAttributeFilter && String(row.product_attribute_id ?? '') !== String(taxonomyAttributeFilter)) {
                return false;
            }

            if (!keyword) {
                return true;
            }

            return `${row.id} ${row.name} ${row.attribute_name ?? ''}`.toLowerCase().includes(keyword);
        });
    }, [taxonomy, taxonomySearch, taxonomyAttributeFilter, categories, attributes, attributeValues]);
    const taxonomyPageSize = 15;
    const taxonomyTotalPages = Math.max(1, Math.ceil(taxonomyRows.length / taxonomyPageSize));
    const taxonomyPagedRows = taxonomyRows.slice((taxonomyPage - 1) * taxonomyPageSize, taxonomyPage * taxonomyPageSize);
    const taxonomyPagerFrom = taxonomyRows.length ? ((taxonomyPage - 1) * taxonomyPageSize) + 1 : 0;
    const taxonomyPagerTo = Math.min(taxonomyPage * taxonomyPageSize, taxonomyRows.length);
    const taxonomyPageNumbers = useMemo(() => {
        const start = Math.max(1, Math.min(taxonomyPage, Math.max(1, taxonomyTotalPages - 4)));
        return Array.from({ length: Math.min(5, taxonomyTotalPages) }, (_, index) => start + index).filter((page) => page <= taxonomyTotalPages);
    }, [taxonomyPage, taxonomyTotalPages]);


    const categoryOptions = useMemo(() => categories.map((item) => ({ value: item.id, label: item.name })), [categories]);
    const attributeOptions = useMemo(() => attributes.map((item) => ({ value: item.id, label: item.name })), [attributes]);
    const valueMetaById = useMemo(() => new Map(attributeValues.map((value) => [String(value.id), value])), [attributeValues]);
    const valuesByAttribute = useMemo(() => attributeValues.reduce((carry, value) => {
        const key = String(value.product_attribute_id ?? '');
        carry[key] ??= [];
        carry[key].push({ value: value.id, label: value.name, subLabel: value.attribute_name });
        return carry;
    }, {}), [attributeValues]);

    const setProductAttribute = (index, attributeId) => {
        const oldAttributeId = productForm.data.attribute_ids?.[index] ?? '';
        const nextAttributes = [...(productForm.data.attribute_ids ?? ['', ''])].concat(['', '']).slice(0, 2);
        nextAttributes[index] = attributeId;
        const oldValueIds = new Set(attributeValues.filter((value) => String(value.product_attribute_id) === String(oldAttributeId)).map((value) => String(value.id)));
        productForm.setData({
            ...productForm.data,
            attribute_ids: nextAttributes,
            attribute_value_ids: (productForm.data.attribute_value_ids ?? []).filter((valueId) => !oldValueIds.has(String(valueId))),
        });
    };

    const setProductAttributeValue = (attributeId, valueId) => {
        const attributeValueIds = new Set(attributeValues.filter((value) => String(value.product_attribute_id) === String(attributeId)).map((value) => String(value.id)));
        const nextValues = (productForm.data.attribute_value_ids ?? []).filter((current) => !attributeValueIds.has(String(current)));
        if (valueId) nextValues.push(Number(valueId));
        productForm.setData('attribute_value_ids', nextValues.slice(0, 2));
    };

    const selectedValueForAttribute = (attributeId) => (productForm.data.attribute_value_ids ?? []).find((valueId) => {
        const value = valueMetaById.get(String(valueId));
        return String(value?.product_attribute_id ?? '') === String(attributeId);
    }) ?? '';

    useEffect(() => {
        setTaxonomyPage(1);
    }, [taxonomySearch, taxonomyAttributeFilter, taxonomy]);

    useEffect(() => {
        if (taxonomyPage > taxonomyTotalPages) setTaxonomyPage(taxonomyTotalPages);
    }, [taxonomyPage, taxonomyTotalPages]);

    return (
        <AppLayout>
            <Head title="Quản lý sản phẩm" />
            <section className="ps-adminlte-page ps-products-page" data-page-code="1.3.1">
                <PageHeader
                    title="Quản lý sản phẩm"
                    pageCode="1.3.1"
                    actions={(
                        <form className="ps-header-search ps-product-search" onSubmit={submitFilters}>
                            <select className="form-control" value={query.vat} onChange={(event) => setQuery((old) => ({ ...old, vat: event.target.value }))}>
                                <option value="">---VAT---</option>
                                {vatCodes.map((code) => <option key={code} value={code}>{code}</option>)}
                            </select>
                            <input className="form-control" placeholder="Tên" value={query.search} onChange={(event) => setQuery((old) => ({ ...old, search: event.target.value }))} />
                            <button className="btn btn-sm btn-primary" type="submit"><i className="fa fa-search" /> Tìm kiếm</button>
                        </form>
                    )}
                    advanced={(
                        <form className="ps-adv-filter-panel ps-product-filters" onSubmit={submitFilters}>
                            <div className="ps-adv-filter-row" style={{ '--ps-adv-cols': 6 }}>
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
                    )}
                />

                <div className="box-body ps-toolbar">
                    <button className="btn btn-sm btn-primary" type="button" onClick={openCreate}><i className="fa fa-plus" /> Thêm mới</button>
                    <button className="btn btn-sm btn-primary" type="button" onClick={() => openTaxonomy('category')}><i className="fa fa-list-alt" /> Phân loại sản phẩm</button>
                    <button className="btn btn-sm btn-primary" type="button" onClick={() => openTaxonomy('attribute')}><i className="fa fa-list-alt" /> Thuộc tính sản phẩm</button>
                    <button className="btn btn-sm btn-primary" type="button" onClick={() => openTaxonomy('value')}><i className="fa fa-list-alt" /> Thuộc tính giá trị</button>
                    <Link className="btn btn-sm btn-primary" href="/admin/products/import" target="_blank"><i className="fa fa-file-excel-o" /> Import sản phẩm</Link>
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
                                <td className="text-right">{currency(row.cost_price)}</td>
                                <td className="text-right"><strong>{formatCurrency(row.unit_price)}</strong></td>
                                <td className="text-center">{row.vat_percent} %</td>
                                <td className="text-center">{row.vat_code}</td>
                                <td className="text-center">{formatNumber(row.weight_grams)}</td>
                                <td className="text-center ps-product-status-cell"><label className="ps-product-status-toggle" title={row.is_active ? `Ngừng kinh doanh ${row.name}` : `Mở kinh doanh lại ${row.name}`}><input type="checkbox" checked={!row.is_active} disabled={statusUpdating === row.id} onChange={(event) => toggleBusinessStatus(row, event)} /></label></td>
                                <td className="text-center">{row.available_marketing ? <i className="fa fa-check text-green" /> : ''}</td>
                                <td className="text-center">{row.available_sale ? <i className="fa fa-check text-green" /> : ''}</td>
                                <td className="text-center">{row.available_care ? <i className="fa fa-check text-green" /> : ''}</td>
                                <td className="text-center"><strong>{row.updated_at}</strong></td>
                                <td className="text-center ps-row-actions"><button type="button" onClick={() => openEdit(row)} aria-label="Cập nhật"><i className="fa fa-pencil-square-o" /></button><button type="button" onClick={() => setConfirmAction({ title: 'Xóa sản phẩm?', description: `Xóa sản phẩm "${row.name}"? Đơn hàng và báo cáo cũ vẫn giữ lịch sử, nhưng cấu hình sản phẩm sẽ không còn dùng tiếp.`, onConfirm: () => router.delete(`/admin/products/${row.id}`, { preserveScroll: true }) })} aria-label="Xóa"><i className="fa fa-trash" /></button></td>
                            </tr>) : <tr><td colSpan="16" className="ps-empty">Chưa có sản phẩm phù hợp.</td></tr>}
                        </tbody>
                    </table>
                </div>
                <PushsalePagination meta={products} routeUrl="/admin/products" filters={currentFilters()} itemLabel="sản phẩm" />
            </section>

            <DialogShell title={editingId ? 'CHỈNH SỬA SẢN PHẨM' : 'THÊM MỚI SẢN PHẨM'} open={productOpen} onClose={() => setProductOpen(false)} wide className="ps-product-source-modal" bodyClassName="ps-product-source-dialog-body">
                <form onSubmit={saveProduct} className="ps-product-source-form">
                    <h4>THÔNG TIN SẢN PHẨM</h4>
                    <div className="ps-guide ps-product-guide">
                        <strong>Chỉ dẫn:</strong><br />
                        <span>- Bạn chỉ có thể thiết lập thuộc tính sản phẩm khi tạo mới sản phẩm.</span><br />
                        <span>- Bạn có thể chọn tối đa 2 thuộc tính hoặc 1 thuộc tính hoặc không chọn thuộc tính</span><br />
                        <span>- Nếu sản phẩm của bạn có kích thước lớn hãy sử dụng chức năng tính khối lượng theo chiều dài, rộng, cao</span><br />
                        <span>- Tên, mã, đơn vị tính, mã vị trí của sản phẩm trong kho sẽ được cập nhật mới nhất.</span><br />
                        <span>- Lưu ý: Khi chỉnh sửa thêm bớt thuộc tính của sản phẩm, cần bấm “Tạo SP” trước khi bấm Lưu để hệ thống sinh sản phẩm mới và cập nhật.</span>
                    </div>

                    <label className="ps-source-check"><input type="checkbox" checked={productForm.data.has_attributes} onChange={(event) => productForm.setData({ ...productForm.data, has_attributes: event.target.checked, attribute_ids: event.target.checked ? (productForm.data.attribute_ids ?? ['', '']) : ['', ''], attribute_value_ids: event.target.checked ? (productForm.data.attribute_value_ids ?? []) : [] })} /> Sản phẩm có thuộc tính</label>

                    <div className="ps-product-source-grid">
                        <label className="ps-product-field span-2"><span>Tên SP gốc <b>(*)</b></span><input className="form-control" value={productForm.data.name} onChange={(event) => productForm.setData('name', event.target.value)} required /></label>
                        <label className="ps-product-field span-2"><span>Phân loại</span><PushsaleSelect searchable options={categoryOptions} value={productForm.data.category_ids?.[0] ?? ''} placeholder="--Phân loại--" onChange={(value) => productForm.setData('category_ids', value ? [Number(value)] : [])} /></label>
                        <label className="ps-product-field"><span>Mã SP</span><input className="form-control" value={productForm.data.sku} onChange={(event) => productForm.setData('sku', event.target.value)} /></label>
                        <label className="ps-product-field"><span>KL(gram)</span><input className="form-control" type="number" min="0" value={productForm.data.weight_grams} onChange={(event) => productForm.setData('weight_grams', Number(event.target.value))} /></label>
                        <label className="ps-product-field"><span>Đ.vị tính</span><input className="form-control" value={productForm.data.unit} onChange={(event) => productForm.setData('unit', event.target.value)} /></label>
                        <label className="ps-source-inline-check"><input type="checkbox" checked={!productForm.data.is_active} onChange={(event) => productForm.setData('is_active', !event.target.checked)} /> SP ngừng kinh doanh</label>
                        <label className="ps-product-field"><span>Giá nhập</span><CurrencyInput className="form-control" min="0" value={productForm.data.cost_price} onChange={(value) => productForm.setData('cost_price', value)} /></label>
                        <label className="ps-product-field"><span>Đơn giá</span><CurrencyInput className="form-control" min="0" value={productForm.data.unit_price} onChange={(value) => productForm.setData('unit_price', value)} required /></label>
                        <label className="ps-product-field span-2"><span>Mã vạch</span><input className="form-control" value={productForm.data.barcode} onChange={(event) => productForm.setData('barcode', event.target.value)} /></label>
                        <label className="ps-product-field ps-vat-field"><span>Mã VAT / VAT (%)</span><div><select className="form-control" value={productForm.data.vat_code} onChange={(event) => productForm.setData('vat_code', event.target.value)}><option value="KCT">KCT</option>{vatCodes.filter((code) => code !== 'KCT').map((code) => <option key={code} value={code}>{code}</option>)}</select><input className="form-control" type="number" min="0" max="100" value={productForm.data.vat_percent} onChange={(event) => productForm.setData('vat_percent', Number(event.target.value))} /></div></label>
                        <label className="ps-product-field"><span>Dài (cm)</span><input className="form-control" type="number" min="0" step="0.01" value={productForm.data.length_cm} onChange={(event) => productForm.setData('length_cm', Number(event.target.value))} /></label>
                        <label className="ps-product-field"><span>Rộng (cm)</span><input className="form-control" type="number" min="0" step="0.01" value={productForm.data.width_cm} onChange={(event) => productForm.setData('width_cm', Number(event.target.value))} /></label>
                        <label className="ps-product-field"><span>Cao (cm)</span><input className="form-control" type="number" min="0" step="0.01" value={productForm.data.height_cm} onChange={(event) => productForm.setData('height_cm', Number(event.target.value))} /></label>
                        <label className="ps-product-field"><span>Mã vị trí</span><input className="form-control" value={productForm.data.warehouse_location} onChange={(event) => productForm.setData('warehouse_location', event.target.value)} /></label>
                    </div>
                    {productForm.data.has_attributes && (
                        <div className="ps-product-attribute-builder">
                            <div className="ps-product-attribute-help">Chọn tối đa 2 thuộc tính và giá trị để hệ thống sinh/ghi nhận biến thể sản phẩm. Danh sách lấy trực tiếp từ nút “Thuộc tính sản phẩm” và “Thuộc tính giá trị”.</div>
                            {[0, 1].map((slot) => {
                                const attributeId = productForm.data.attribute_ids?.[slot] ?? '';
                                const otherAttributeId = productForm.data.attribute_ids?.[slot === 0 ? 1 : 0] ?? '';
                                const filteredAttributeOptions = attributeOptions.filter((option) => !otherAttributeId || String(option.value) !== String(otherAttributeId));
                                return (
                                    <div className="ps-product-attribute-row" key={slot}>
                                        <span>Thuộc tính {slot + 1}</span>
                                        <PushsaleSelect searchable options={filteredAttributeOptions} value={attributeId} placeholder="--Chọn thuộc tính--" onChange={(value) => setProductAttribute(slot, value)} />
                                        <span>Giá trị</span>
                                        <PushsaleSelect searchable options={valuesByAttribute[String(attributeId)] ?? []} value={attributeId ? selectedValueForAttribute(attributeId) : ''} placeholder="--Chọn giá trị--" disabled={!attributeId} onChange={(value) => setProductAttributeValue(attributeId, value)} />
                                    </div>
                                );
                            })}
                        </div>
                    )}
                    <button className="btn btn-primary ps-save-button" disabled={productForm.processing}><i className="fa fa-save" /> Lưu</button>

                    <h4>PHÂN QUYỀN</h4>
                    <div className="ps-product-permission-source">
                        <div className="ps-permission-row"><span>Chọn nhanh Marketing<br />từ Nhóm Marketing</span><PushsaleSelect searchable options={marketingTeams} value={productForm.data.marketing_team_ids?.[0] ?? ''} placeholder="--Chọn nhóm Marketing--" onChange={(value) => setPermissionTeams('marketing', value ? [Number(value)] : [], marketingTeams)} /><button type="button" onClick={() => refillPermissionFromTeam('marketing', marketingTeams)} title="Nạp lại danh sách marketing theo nhóm"><i className="fa fa-refresh" /></button><button type="button" onClick={() => resetPermission('marketing')} title="Xóa phân quyền marketing"><i className="fa fa-trash" /></button><small>* Chọn nhóm mkt sẽ tự động điền danh sách mkt vào trong phân chọn ưu tiên mkt phía dưới</small></div>
                        <div className="ps-permission-row"><span>Marketing</span><PushsaleMultiSelect label="Marketing" enabled={productForm.data.available_marketing} selectedIds={productForm.data.marketing_user_ids} options={marketingUsers} onEnabledChange={(enabled) => setPermissionEnabled('marketing', enabled)} onChange={(ids) => setPermissionUsers('marketing', ids)} /><small>* Cấu hình các Marketing được làm việc với sản phẩm. Nếu chọn “Tất cả” thì sản phẩm mở cho toàn bộ marketing đang hoạt động.</small></div>
                        <div className="ps-permission-row"><span>Chọn nhanh sale từ<br />Nhóm sale</span><PushsaleSelect searchable options={saleTeams} value={productForm.data.sale_team_ids?.[0] ?? ''} placeholder="--Chọn nhóm sale--" onChange={(value) => setPermissionTeams('sale', value ? [Number(value)] : [], saleTeams)} /><button type="button" onClick={() => refillPermissionFromTeam('sale', saleTeams)} title="Nạp lại danh sách sale theo nhóm"><i className="fa fa-refresh" /></button><button type="button" onClick={() => resetPermission('sale')} title="Xóa phân quyền sale"><i className="fa fa-trash" /></button><small>* Chọn nhóm sale sẽ tự động điền danh sách sale vào trong phân chọn ưu tiên sale phía dưới</small></div>
                        <div className="ps-permission-row"><span>Sale</span><PushsaleMultiSelect label="Sale" enabled={productForm.data.available_sale} selectedIds={productForm.data.sale_user_ids} options={saleUsers} onEnabledChange={(enabled) => setPermissionEnabled('sale', enabled)} onChange={(ids) => setPermissionUsers('sale', ids)} /><small>* Cấu hình các Sale được chia số nếu số về từ nguồn dữ liệu có cấu hình sản phẩm hiện tại.</small></div>
                        <div className="ps-permission-row"><span>Chọn nhanh CSKH từ<br />Nhóm CSKH</span><PushsaleSelect searchable options={careTeams} value={productForm.data.care_team_ids?.[0] ?? ''} placeholder="--Chọn nhóm CSKH--" onChange={(value) => setPermissionTeams('care', value ? [Number(value)] : [], careTeams)} /><button type="button" onClick={() => refillPermissionFromTeam('care', careTeams)} title="Nạp lại danh sách CSKH theo nhóm"><i className="fa fa-refresh" /></button><button type="button" onClick={() => resetPermission('care')} title="Xóa phân quyền CSKH"><i className="fa fa-trash" /></button><small>* Chọn nhóm cskh sẽ tự động điền danh sách cskh vào trong phân chọn ưu tiên cskh phía dưới</small></div>
                        <div className="ps-permission-row"><span>CSKH</span><PushsaleMultiSelect label="CSKH" enabled={productForm.data.available_care} selectedIds={productForm.data.care_user_ids} options={careUsers} onEnabledChange={(enabled) => setPermissionEnabled('care', enabled)} onChange={(ids) => setPermissionUsers('care', ids)} /><small>* Cấu hình các CSKH được chia số nếu số về từ nguồn dữ liệu có cấu hình sản phẩm hiện tại.</small></div>
                    </div>
                    {Object.keys(productForm.errors).length > 0 && <div className="alert alert-danger">{Object.values(productForm.errors).join(' · ')}</div>}
                    <button className="btn btn-primary ps-save-button" disabled={productForm.processing}><i className="fa fa-save" /> Lưu</button>
                </form>
            </DialogShell>

            <DialogShell title={activeTaxonomyMeta?.title ?? ''} open={Boolean(taxonomy)} onClose={() => setTaxonomy(null)} wide hiddenHeader={false} showClose className="ps-taxonomy-source-modal" bodyClassName="ps-taxonomy-source-body">
                <div className={`ps-taxonomy-source-form ps-taxonomy-canonical-v135 ps-taxonomy-${taxonomy ?? 'none'}`}>
                    <div className="m-header-wrap ps-taxonomy-header-wrap">
                        <div className="m-header"><div className="col-sm-9 form-group"><span className="text">{activeTaxonomyMeta?.title}</span></div><div className="col-sm-3 form-group ps-taxonomy-close-cell"><button type="button" className="ps-taxonomy-close" onClick={() => setTaxonomy(null)} aria-label="Đóng"><i className="fa fa-times" /></button></div></div>
                    </div>
                    <div className="box1 ps-taxonomy-box">
                        <div className="box-body ps-taxonomy-search-row">
                            <div className="ps-taxonomy-search-control ps-taxonomy-keyword"><input className="form-control" value={taxonomySearch} placeholder={activeTaxonomyMeta?.searchPlaceholder} onChange={(event) => setTaxonomySearch(event.target.value)} /></div>
                            {taxonomy === 'value' && <div className="ps-taxonomy-search-control ps-taxonomy-attribute-control"><select className="form-control ps-taxonomy-attribute-filter" value={taxonomyAttributeFilter} onChange={(event) => setTaxonomyAttributeFilter(event.target.value)}><option value="">--Chọn thuộc tính--</option>{attributes.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></div>}
                            <div className="ps-taxonomy-search-control ps-taxonomy-search-action"><button type="button" className="btn btn-sm btn-primary mr15"><i className="fa fa-search" /> Tìm kiếm</button></div>
                        </div>
                    </div>
                    <div className="ps-taxonomy-separator" />
                    <div className="box-body ps-taxonomy-main" style={{ flex: '1 1 auto', minHeight: 0 }}>
                        <div className="ps-taxonomy-grid" style={{ display: 'grid', gridTemplateColumns: taxonomy === 'value' ? 'minmax(560px, 1fr) minmax(520px, 0.85fr)' : 'minmax(520px, 0.72fr) minmax(520px, 1fr)', gap: 18, width: '100%', alignItems: 'start' }}>
                            <div className="ps-taxonomy-list-pane" style={{ minWidth: 0, width: '100%' }}>
                                <table className="table table-bordered ps-taxonomy-table">
                                    <thead><tr><th className="text-center">Id</th><th className="text-left no-wrap">Tên</th>{taxonomy === 'value' && <th className="text-left no-wrap">Thuộc tính</th>}<th className="text-center no-wrap">Cập nhật</th><th className="text-center" /></tr></thead>
                                    <tbody>
                                        {taxonomyPagedRows.length ? taxonomyPagedRows.map((row) => <tr key={`${taxonomy}-${row.id}`} className={`item${row.id}${String(taxonomyForm.data.id) === String(row.id) ? ' ps-taxonomy-selected-row' : ''}`}>
                                            <td className="text-center ps-taxonomy-id">{row.id}</td>
                                            <td className="text-left">{row.name}</td>
                                            {taxonomy === 'value' && <td className="text-left">{row.attribute_name}</td>}
                                            <td className="text-center"><strong>{row.updated_by ?? ''}</strong><br />{row.updated_at ?? ''}</td>
                                            <td className="text-center ps-taxonomy-actions"><button type="button" className="btn-icon aoh" aria-label={activeTaxonomyMeta?.editTitle} onClick={() => editTaxonomy(row)}><i className="fa fa-edit" /></button><button type="button" className="btn-icon aoh" aria-label={activeTaxonomyMeta?.deleteTitle} onClick={() => removeTaxonomy(row)}><i className="fa fa-trash" /></button></td>
                                        </tr>) : <tr><td colSpan={taxonomy === 'value' ? 5 : 4} className="text-center">Không có dữ liệu.</td></tr>}
                                    </tbody>
                                </table>
                                <div className="row ps-taxonomy-pager-row">
                                    <div className="col-xs-6 text-left form-group"><div className="btn-group pull-left"><button type="button" className="btn btn-default btn-sm">{taxonomyPagerFrom} - {taxonomyPagerTo} <span style={{ fontWeight: 'normal' }}> / </span> {taxonomyRows.length}</button><button type="button" className="btn btn-default btn-sm" disabled={taxonomyPage <= 1} onClick={() => setTaxonomyPage((page) => Math.max(1, page - 1))}><i className="fa fa-caret-left" /></button><button type="button" className="btn btn-default btn-sm" disabled={taxonomyPage >= taxonomyTotalPages} onClick={() => setTaxonomyPage((page) => Math.min(taxonomyTotalPages, page + 1))}><i className="fa fa-caret-right" /></button></div></div>
                                    <div className="col-xs-6 text-right form-group"><ul className="pagination pagination-sm no-margin pull-right"><li className={taxonomyPage <= 1 ? 'disabled' : ''}><button type="button" disabled={taxonomyPage <= 1} onClick={() => setTaxonomyPage((page) => Math.max(1, page - 1))}>«</button></li>{taxonomyPageNumbers.map((page) => <li key={page} className={page === taxonomyPage ? 'active' : ''}><button type="button" onClick={() => setTaxonomyPage(page)}>{page}</button></li>)}<li className={taxonomyPage >= taxonomyTotalPages ? 'disabled' : ''}><button type="button" disabled={taxonomyPage >= taxonomyTotalPages} onClick={() => setTaxonomyPage((page) => Math.min(taxonomyTotalPages, page + 1))}>»</button></li></ul></div>
                                </div>
                            </div>
                            <div className="ps-taxonomy-info-pane" style={{ minWidth: 0, width: '100%' }}>
                                <form onSubmit={saveTaxonomy}>
                                    <table className="table table-bordered table-line ps-taxonomy-info-table">
                                        <tbody>
                                            <tr><th colSpan="3">{activeTaxonomyMeta?.updateTitle}</th></tr>
                                            <tr><td className="text-right ps-taxonomy-label">Id:</td><td className="ps-taxonomy-value"><span className="fb">{taxonomyForm.data.id}</span></td><td /></tr>
                                            {taxonomy === 'value' && <tr><td className="text-right ps-taxonomy-label">Thuộc tính<span className="text-red"> (*)</span>:</td><td className="ps-taxonomy-value"><select className="form-control" value={taxonomyForm.data.product_attribute_id} onChange={(event) => taxonomyForm.setData('product_attribute_id', event.target.value)} required><option value="">--Chọn thuộc tính--</option>{attributes.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></td><td /></tr>}
                                            <tr><td className="text-right ps-taxonomy-label"><span>{activeTaxonomyMeta?.label}<span className="text-red"> (*)</span></span>:</td><td className="ps-taxonomy-value"><input className="form-control txt-dotted" maxLength="100" value={taxonomyForm.data.name} onChange={(event) => taxonomyForm.setData('name', event.target.value)} required /></td><td className="ps-taxonomy-update-note">{activeTaxonomyMeta?.updateNote}</td></tr>
                                            <tr><td className="text-right" /><td colSpan="2"><div className="ps-taxonomy-functions"><button type="submit" className="mr15 ps-link-button" disabled={taxonomyForm.processing}><i className="fa fa-save" /> Cập nhật</button><button type="button" className="mr15 ps-link-button" onClick={resetTaxonomy}><i className="fa fa-refresh" /> Làm lại</button><span className="mr15"><input id="ps_taxonomy_clear" type="checkbox" checked={taxonomyForm.data.clear_after_save} onChange={(event) => taxonomyForm.setData('clear_after_save', event.target.checked)} /><label htmlFor="ps_taxonomy_clear">Xóa form nhập liệu</label></span><input id="ps_taxonomy_support" type="checkbox" checked={taxonomyForm.data.support_update} onChange={(event) => taxonomyForm.setData('support_update', event.target.checked)} /><label htmlFor="ps_taxonomy_support">Hỗ trợ cập nhật</label></div></td></tr>
                                        </tbody>
                                    </table>
                                    {Object.keys(taxonomyForm.errors).length > 0 && <div className="alert alert-danger">{Object.values(taxonomyForm.errors).join(' · ')}</div>}
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </DialogShell>
            <ConfirmActionDialog
                open={Boolean(confirmAction)}
                title={confirmAction?.title}
                description={confirmAction?.description}
                onCancel={() => setConfirmAction(null)}
                onConfirm={() => {
                    const action = confirmAction?.onConfirm;
                    setConfirmAction(null);
                    action?.();
                }}
            />
        </AppLayout>
    );
}
