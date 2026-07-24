import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

import AppLayout from '@/layouts/AppLayout';
import { PushsalePagination } from '@/components/pagination/PushsalePagination';

function optionLabel(option) {
    return option?.label ?? option?.name ?? option?.email ?? String(option?.id ?? '');
}

function currentQuery() {
    if (typeof window === 'undefined') return {};
    return Object.fromEntries(new URLSearchParams(window.location.search).entries());
}

function rowForm(row) {
    return row?._form ?? {
        page_id: '',
        page_name: '',
        creator_name: '',
        marketer_user_id: '',
        is_active: true,
    };
}

export default function Page({ schema, rows = [], pagination, filterOptions = {}, routeUrl }) {
    const [keyword, setKeyword] = useState(() => currentQuery().search ?? '');
    const [selected, setSelected] = useState(() => rows[0] ?? null);
    const [form, setForm] = useState(() => rowForm(rows[0] ?? null));
    const [saving, setSaving] = useState(false);
    const [message, setMessage] = useState('');

    const marketers = useMemo(() => filterOptions.marketers ?? [], [filterOptions.marketers]);

    const selectRow = (row) => {
        setSelected(row);
        setForm(rowForm(row));
        setMessage('');
    };

    const submitSearch = (event) => {
        event?.preventDefault?.();
        router.get(routeUrl, { ...currentQuery(), search: keyword, page: 1 }, { preserveState: true, preserveScroll: true });
    };

    const clearForm = () => {
        setSelected(null);
        setForm({ page_id: '', page_name: '', creator_name: '', marketer_user_id: '', is_active: true });
        setMessage('');
    };

    const save = () => {
        if (!selected?._record_id) {
            setMessage('Chọn một Fanpage ở bảng bên trái trước khi cập nhật.');
            return;
        }
        if (!form.marketer_user_id) {
            setMessage('Chọn tài khoản Marketing phụ trách Fanpage.');
            return;
        }

        setSaving(true);
        router.patch(`${routeUrl}/records/${selected._record_id}`, {
            payload: {
                page_id: form.page_id,
                page_name: form.page_name,
                creator_name: form.creator_name,
                marketer_user_id: form.marketer_user_id,
                is_active: true,
            },
        }, {
            preserveScroll: true,
            onSuccess: () => setMessage('Đã cập nhật cấu hình Fanpage.'),
            onError: () => setMessage('Không lưu được cấu hình. Kiểm tra lại dữ liệu hoặc quyền truy cập.'),
            onFinish: () => setSaving(false),
        });
    };

    return (
        <AppLayout>
            <Head title={schema?.title ?? 'Cấu hình Facebook của đơn vị'} />
            <div className="ps-facebook-unit-page">
                <div className="ps-facebook-unit-titlebar">
                    <h1>{schema?.title ?? 'Cấu hình Facebook của đơn vị'}</h1>
                </div>

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
                                                className={selected?._record_id === row._record_id ? 'is-selected' : ''}
                                                onClick={() => selectRow(row)}
                                            >
                                                <td className="ps-facebook-fanpage">
                                                    {(row.fanpage ?? '').split('\n').map((line, index) => <span key={index}>{line || '\u00a0'}</span>)}
                                                </td>
                                                <td>{row.fb_creator || '—'}</td>
                                                <td>{row.pushsale_user || '—'}</td>
                                                <td className="text-center ps-facebook-row-actions">
                                                    <button type="button" title="Cập nhật" onClick={(event) => { event.stopPropagation(); selectRow(row); }}>
                                                        <i className="fa fa-pencil-square-o" aria-hidden="true" />
                                                    </button>
                                                </td>
                                            </tr>
                                        )) : (
                                            <tr>
                                                <td colSpan={4} className="text-center ps-facebook-empty">Không có dữ liệu.</td>
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
                                        <th colSpan={2}>Cập nhật</th>
                                    </tr>
                                    <tr>
                                        <td className="text-right ps-facebook-editor-label">Id:</td>
                                        <td>{selected?._record_id ?? ''}</td>
                                    </tr>
                                    <tr>
                                        <td className="text-right ps-facebook-editor-label">Fanpage:</td>
                                        <td>
                                            <input type="text" className="form-control txt-dotted" value={form.page_id ?? ''} disabled />
                                        </td>
                                    </tr>
                                    <tr>
                                        <td className="text-right ps-facebook-editor-label">Creator:</td>
                                        <td>
                                            <input type="text" className="form-control txt-dotted" value={form.creator_name ?? ''} disabled />
                                        </td>
                                    </tr>
                                    <tr>
                                        <td className="text-right ps-facebook-editor-label">Marketing <span className="text-red">(*)</span>:</td>
                                        <td>
                                            <select
                                                className="form-control"
                                                value={form.marketer_user_id ?? ''}
                                                onChange={(event) => setForm((current) => ({ ...current, marketer_user_id: event.target.value }))}
                                                disabled={!selected}
                                            >
                                                <option value="">--Chọn marketing--</option>
                                                {marketers.map((option) => (
                                                    <option key={option.id} value={option.id}>{optionLabel(option)}</option>
                                                ))}
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td />
                                        <td className="ps-facebook-editor-actions">
                                            <button type="button" onClick={save} disabled={saving || !selected}>
                                                <i className="fa fa-save" aria-hidden="true" /> Cập nhật
                                            </button>
                                            <button type="button" onClick={clearForm} disabled={saving}>
                                                <i className="fa fa-refresh" aria-hidden="true" /> Làm lại
                                            </button>
                                            {message && <span className="ps-facebook-editor-message">{message}</span>}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <div className="ps-facebook-business-note">
                                Cấu hình này dùng để map PageID Facebook với tài khoản Marketing. Lead webhook Facebook đi vào hệ thống sẽ mang PageID, từ đó gắn đúng marketer để chia số và tính báo cáo Marketing.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
