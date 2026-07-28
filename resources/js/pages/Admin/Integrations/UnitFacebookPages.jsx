import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

import AppLayout from '@/layouts/AppLayout';
import { PageHeader } from '@/components/layout/PageHeader';
import { PushsalePagination } from '@/components/pagination/PushsalePagination';

function optionLabel(option) {
    return option?.label ?? option?.name ?? option?.email ?? String(option?.id ?? '');
}

function currentQuery() {
    if (typeof window === 'undefined') return {};
    return Object.fromEntries(new URLSearchParams(window.location.search).entries());
}

function blankForm() {
    return {
        page_id: '',
        page_name: '',
        creator_name: '',
        marketer_user_id: '',
        is_active: true,
    };
}

function rowForm(row) {
    const form = row?._form ?? {};
    return {
        page_id: String(form.page_id ?? ''),
        page_name: String(form.page_name ?? ''),
        creator_name: String(form.creator_name ?? ''),
        marketer_user_id: form.marketer_user_id ? String(form.marketer_user_id) : '',
        is_active: form.is_active !== false,
    };
}

export default function Page({
    schema,
    rows = [],
    pagination,
    filterOptions = {},
    routeUrl,
    pageRuntimeError = null,
}) {
    const flash = usePage().props.flash ?? {};
    const [keyword, setKeyword] = useState(() => currentQuery().search ?? '');
    const [selectedId, setSelectedId] = useState(null);
    const [form, setForm] = useState(blankForm);
    const [saving, setSaving] = useState(false);

    const marketers = useMemo(() => filterOptions.marketers ?? [], [filterOptions.marketers]);
    const selected = useMemo(
        () => rows.find((row) => String(row._record_id) === String(selectedId)) ?? null,
        [rows, selectedId],
    );

    useEffect(() => {
        if (flash.success) toast.success(flash.success);
        if (flash.error) toast.error(flash.error);
    }, [flash.success, flash.error]);

    useEffect(() => {
        if (pageRuntimeError) toast.error(pageRuntimeError);
    }, [pageRuntimeError]);

    const updateField = (key, value) => setForm((current) => ({ ...current, [key]: value }));

    const selectRow = (row) => {
        setSelectedId(row?._record_id ?? null);
        setForm(rowForm(row));
    };

    const clearForm = () => {
        setSelectedId(null);
        setForm(blankForm());
    };

    const submitSearch = (event) => {
        event?.preventDefault?.();
        router.get(routeUrl, { ...currentQuery(), search: keyword, page: 1 }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const save = () => {
        if (!form.page_id.trim() || !form.page_name.trim()) {
            toast.error('Nhập PageID và tên Fanpage.');
            return;
        }
        if (!form.marketer_user_id) {
            toast.error('Chọn tài khoản Marketing phụ trách Fanpage.');
            return;
        }

        const payload = {
            page_id: form.page_id.trim(),
            page_name: form.page_name.trim(),
            creator_name: form.creator_name.trim() || null,
            marketer_user_id: Number(form.marketer_user_id),
            is_active: Boolean(form.is_active),
        };

        setSaving(true);
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(selectedId ? 'Đã cập nhật cấu hình Fanpage.' : 'Đã thêm cấu hình Fanpage.');
                if (!selectedId) clearForm();
            },
            onError: (errors) => {
                const first = Object.values(errors || {})[0];
                toast.error(first || 'Không lưu được cấu hình. Kiểm tra dữ liệu hoặc quyền truy cập.');
            },
            onFinish: () => setSaving(false),
        };

        if (selectedId) {
            router.patch(`${routeUrl}/records/${selectedId}`, { payload }, options);
            return;
        }

        router.post(`${routeUrl}/records`, { payload }, options);
    };

    return (
        <AppLayout activeMenuCode="1.11">
            <Head title={schema?.title ?? 'Cấu hình Facebook của đơn vị'} />
            <PageHeader title={schema?.title ?? 'Cấu hình Facebook của đơn vị'} pageCode="1.11" collapsible={false} />

            <div className="ps-facebook-unit-page" data-page-code="1.11">
                <div className="ps-facebook-unit-panel">
                    <form className="ps-facebook-unit-search" onSubmit={submitSearch}>
                        <input
                            type="text"
                            className="form-control text-center"
                            value={keyword}
                            placeholder="Tìm PageID, Tên Fanpage"
                            onChange={(event) => setKeyword(event.target.value)}
                        />
                        <button type="submit" className="btn btn-sm btn-primary">
                            <i className="fa fa-search" aria-hidden="true" /> Tìm kiếm
                        </button>
                    </form>

                    <div className="ps-facebook-unit-divider" />

                    <div className="ps-facebook-unit-body">
                        <div className="ps-facebook-unit-list">
                            <div className="ps-facebook-table-scroll">
                                <table className="table table-bordered ps-facebook-table">
                                    <thead>
                                        <tr>
                                            <th className="text-left no-wrap">Fanpage</th>
                                            <th className="text-left no-wrap">FB Creator</th>
                                            <th className="text-left no-wrap">Pushsale User</th>
                                            <th className="text-center no-wrap">Cập nhật</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {rows.length ? rows.map((row) => (
                                            <tr
                                                key={row._record_id ?? row.index}
                                                className={String(selectedId) === String(row._record_id) ? 'is-selected' : ''}
                                                onClick={() => selectRow(row)}
                                            >
                                                <td className="ps-facebook-fanpage">
                                                    {(row.fanpage ?? '').split('\n').map((line, index) => (
                                                        <span key={index}>{line || '\u00a0'}</span>
                                                    ))}
                                                </td>
                                                <td>{row.fb_creator || '—'}</td>
                                                <td>{row.pushsale_user || '—'}</td>
                                                <td className="text-center ps-facebook-row-actions">
                                                    <button
                                                        type="button"
                                                        className="btn btn-xs btn-primary"
                                                        title="Cập nhật"
                                                        onClick={(event) => {
                                                            event.stopPropagation();
                                                            selectRow(row);
                                                        }}
                                                    >
                                                        <i className="fa fa-pencil" aria-hidden="true" />
                                                    </button>
                                                </td>
                                            </tr>
                                        )) : (
                                            <tr>
                                                <td colSpan={4} className="text-center ps-facebook-empty">
                                                    Không có dữ liệu. Điền form bên phải rồi bấm Cập nhật để thêm Fanpage.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                            <div className="ps-facebook-pagination-wrap">
                                <PushsalePagination meta={pagination} routeUrl={routeUrl} filters={currentQuery()} itemLabel="fanpage" />
                            </div>
                        </div>

                        <div className="ps-facebook-unit-editor">
                            <table className="table table-bordered table-line ps-facebook-editor-table">
                                <tbody>
                                    <tr>
                                        <th colSpan={2}>{selectedId ? 'Cập nhật' : 'Thêm mới'}</th>
                                    </tr>
                                    <tr>
                                        <td className="text-right ps-facebook-editor-label">Id:</td>
                                        <td>{selectedId ?? '—'}</td>
                                    </tr>
                                    <tr>
                                        <td className="text-right ps-facebook-editor-label">PageID <span className="text-red">(*)</span>:</td>
                                        <td>
                                            <input
                                                type="text"
                                                className="form-control"
                                                value={form.page_id}
                                                onChange={(event) => updateField('page_id', event.target.value)}
                                                placeholder="VD: 102938475610001"
                                            />
                                        </td>
                                    </tr>
                                    <tr>
                                        <td className="text-right ps-facebook-editor-label">Fanpage <span className="text-red">(*)</span>:</td>
                                        <td>
                                            <input
                                                type="text"
                                                className="form-control"
                                                value={form.page_name}
                                                onChange={(event) => updateField('page_name', event.target.value)}
                                                placeholder="Tên Fanpage"
                                            />
                                        </td>
                                    </tr>
                                    <tr>
                                        <td className="text-right ps-facebook-editor-label">Creator:</td>
                                        <td>
                                            <input
                                                type="text"
                                                className="form-control"
                                                value={form.creator_name}
                                                onChange={(event) => updateField('creator_name', event.target.value)}
                                                placeholder="FB Creator"
                                            />
                                        </td>
                                    </tr>
                                    <tr>
                                        <td className="text-right ps-facebook-editor-label">Marketing <span className="text-red">(*)</span>:</td>
                                        <td>
                                            <select
                                                className="form-control"
                                                value={form.marketer_user_id}
                                                onChange={(event) => updateField('marketer_user_id', event.target.value)}
                                            >
                                                <option value="">--Chọn marketing--</option>
                                                {marketers.map((option) => (
                                                    <option key={option.id} value={option.id}>{optionLabel(option)}</option>
                                                ))}
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td className="text-right ps-facebook-editor-label">Trạng thái:</td>
                                        <td>
                                            <label className="ps-facebook-check">
                                                <input
                                                    type="checkbox"
                                                    checked={Boolean(form.is_active)}
                                                    onChange={(event) => updateField('is_active', event.target.checked)}
                                                />
                                                <span>Đang sử dụng</span>
                                            </label>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td />
                                        <td className="ps-facebook-editor-actions">
                                            <button type="button" className="btn btn-sm btn-primary" onClick={save} disabled={saving}>
                                                <i className={`fa ${saving ? 'fa-spinner fa-spin' : 'fa-save'}`} aria-hidden="true" />
                                                {selectedId ? 'Cập nhật' : 'Thêm mới'}
                                            </button>
                                            <button type="button" className="btn btn-sm btn-default" onClick={clearForm} disabled={saving}>
                                                <i className="fa fa-refresh" aria-hidden="true" /> Làm lại
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <div className="ps-facebook-business-note">
                                Cấu hình này map PageID Facebook với tài khoản Marketing. Lead webhook Facebook mang PageID sẽ gắn đúng marketer để chia số và tính báo cáo Marketing.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
