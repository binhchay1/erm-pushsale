import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

import { PushsaleSearchButton } from '@/components/actions/PushsaleSearchButton';
import { PageHeader } from '@/components/layout/PageHeader';
import { PushsalePagination } from '@/components/pagination/PushsalePagination';
import { PushsaleDialog } from '@/components/ui/pushsale-dialog';
import AppLayout from '@/layouts/AppLayout';
import { useI18n, useT } from '@/providers/I18nProvider';

const emptyForm = { phone: '', reason: '', order_id: '', creation_type: 'manual' };
const currentFilters = () => Object.fromEntries(new URLSearchParams(window.location.search).entries());

function formatDate(value, locale) {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return new Intl.DateTimeFormat(locale === 'en' ? 'en-GB' : 'vi-VN', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
}

function creationTypeLabel(value, t) {
    if (value === 'warehouse') return t('pages.phone_blacklist.type_warehouse');
    if (value === 'automatic') return t('pages.phone_blacklist.type_automatic');
    if (value === 'manual') return t('pages.phone_blacklist.type_manual');
    return value || '';
}

export default function PhoneBlacklist({
    schema,
    rows = [],
    pagination,
    routeUrl = '/admin/security/phone-blacklist',
    filterOptions = {},
}) {
    const t = useT();
    const { locale } = useI18n();
    const params = new URLSearchParams(window.location.search);
    const [keyword, setKeyword] = useState(params.get('search') ?? '');
    const [editorOpen, setEditorOpen] = useState(false);
    const [guideOpen, setGuideOpen] = useState(false);
    const [editingId, setEditingId] = useState(null);
    const form = useForm(emptyForm);

    const title = t('pages.phone_blacklist.title');

    const search = (event) => {
        event?.preventDefault?.();
        router.get(routeUrl, keyword.trim() ? { search: keyword.trim() } : {}, { replace: true, preserveState: true });
    };

    const openCreate = () => {
        setEditingId(null);
        form.setData(emptyForm);
        form.clearErrors();
        setEditorOpen(true);
    };

    const openEdit = (row) => {
        setEditingId(row._record_id);
        form.setData({
            ...emptyForm,
            ...(row._form ?? {}),
            phone: row._form?.phone ?? row.phone ?? '',
            reason: row._form?.reason ?? row.reason ?? '',
        });
        form.clearErrors();
        setEditorOpen(true);
    };

    const save = (event) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => setEditorOpen(false) };
        if (editingId) form.transform((data) => ({ payload: data })).put(`${routeUrl}/records/${editingId}`, options);
        else form.transform((data) => ({ payload: data })).post(`${routeUrl}/records`, options);
    };

    const destroy = (row) => {
        if (!row._record_id || !window.confirm(t('pages.phone_blacklist.delete_confirm', { phone: row.phone }))) return;
        router.delete(`${routeUrl}/records/${row._record_id}`, { preserveScroll: true });
    };

    return (
        <AppLayout>
            <Head title={title} />
            <PageHeader
                title={title}
                pageCode="1.13.1"
                className="ps-blacklist-header"
                subtitle={(
                    <button type="button" className="ps-guide-link" onClick={() => setGuideOpen(true)}>
                        {t('pages.phone_blacklist.view_guide')}
                    </button>
                )}
                actions={(
                    <>
                        <form className="ps-blacklist-search" onSubmit={search}>
                            <input
                                className="form-control"
                                value={keyword}
                                onChange={(event) => setKeyword(event.target.value)}
                            />
                            <PushsaleSearchButton type="submit" label={t('common.search')} />
                        </form>
                        <button type="button" className="btn btn-sm btn-primary ps-blacklist-add" onClick={openCreate}>
                            <i className="fa fa-plus" />
                            <span>{t('pages.phone_blacklist.add')}</span>
                        </button>
                    </>
                )}
            />
            <section className="ps-adminlte-page ps-blacklist-page" data-page-code="1.13.1">
                <div className="box-body ps-blacklist-body">
                    <div className="ps-table-scroll">
                        <table className="table table-bordered table-multi-select ps-blacklist-table">
                            <thead>
                                <tr>
                                    <th className="text-center" style={{ width: 40 }}>#</th>
                                    <th className="text-center no-wrap">{t('pages.phone_blacklist.col_phone')}</th>
                                    <th className="text-center no-wrap">{t('pages.phone_blacklist.col_reason')}</th>
                                    <th className="text-center no-wrap">{t('pages.phone_blacklist.col_order')}</th>
                                    <th className="text-center no-wrap">{t('pages.phone_blacklist.col_creation_type')}</th>
                                    <th className="text-center no-wrap" style={{ width: 150 }}>{t('pages.phone_blacklist.col_creator')}</th>
                                    <th className="text-center no-wrap" style={{ width: 150 }}>{t('pages.phone_blacklist.col_updated')}</th>
                                    <th className="text-center no-wrap ps-action-col">{t('pages.phone_blacklist.col_actions')}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.length ? rows.map((row, index) => (
                                    <tr key={row._record_id ?? row.id}>
                                        <td className="text-center">{index + (pagination?.from ?? 1)}</td>
                                        <td className="text-center"><strong>{row.phone}</strong></td>
                                        <td>{row.reason}</td>
                                        <td className="text-center">{row.order_code}</td>
                                        <td className="text-center">{creationTypeLabel(row.creation_type, t)}</td>
                                        <td className="text-center">{row.creator}</td>
                                        <td className="text-center">{formatDate(row.updated_at, locale)}</td>
                                        <td className="text-center ps-row-actions ps-row-actions-cell">
                                            <button type="button" title={t('pages.phone_blacklist.update')} onClick={() => openEdit(row)}>
                                                <i className="fa fa-pencil-square-o" />
                                            </button>
                                            <button type="button" title={t('pages.phone_blacklist.delete')} onClick={() => destroy(row)}>
                                                <i className="fa fa-trash" />
                                            </button>
                                        </td>
                                    </tr>
                                )) : (
                                    <tr>
                                        <td colSpan="8" className="text-center">{t('pages.phone_blacklist.empty')}</td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    <div className="row ps-blacklist-note-row">
                        <div className="col-sm-6 text-left">{t('pages.phone_blacklist.note')}</div>
                        <div className="col-sm-6 text-right">
                            <PushsalePagination
                                meta={pagination}
                                routeUrl={routeUrl}
                                filters={currentFilters()}
                                itemLabel={t('pages.phone_blacklist.item_label')}
                            />
                        </div>
                    </div>
                </div>
            </section>

            <PushsaleDialog
                open={editorOpen}
                onOpenChange={(open) => !open && setEditorOpen(false)}
                title={editingId ? t('pages.phone_blacklist.update_title') : t('pages.phone_blacklist.add')}
                width="600px"
                className="ps-blacklist-dialog"
            >
                <form onSubmit={save} className="ps-blacklist-form">
                    <label>
                        <span>{t('pages.phone_blacklist.phone_required')} <b>(*)</b></span>
                        <input
                            className="form-control"
                            value={form.data.phone}
                            onChange={(event) => form.setData('phone', event.target.value)}
                            required
                        />
                    </label>
                    <label>
                        <span>{t('pages.phone_blacklist.reason')}</span>
                        <textarea
                            className="form-control"
                            rows="3"
                            value={form.data.reason ?? ''}
                            onChange={(event) => form.setData('reason', event.target.value)}
                        />
                    </label>
                    <label>
                        <span>{t('pages.phone_blacklist.order')}</span>
                        <select
                            className="form-control"
                            value={form.data.order_id ?? ''}
                            onChange={(event) => form.setData('order_id', event.target.value)}
                        >
                            <option value="">{t('pages.phone_blacklist.select_order')}</option>
                            {(filterOptions.orders ?? []).map((order) => (
                                <option key={order.id} value={order.id}>{order.label}</option>
                            ))}
                        </select>
                    </label>
                    <label>
                        <span>{t('pages.phone_blacklist.creation_type')}</span>
                        <select
                            className="form-control"
                            value={form.data.creation_type}
                            onChange={(event) => form.setData('creation_type', event.target.value)}
                        >
                            <option value="manual">{t('pages.phone_blacklist.type_manual')}</option>
                            <option value="warehouse">{t('pages.phone_blacklist.type_warehouse')}</option>
                            <option value="automatic">{t('pages.phone_blacklist.type_automatic')}</option>
                        </select>
                    </label>
                    {Object.keys(form.errors).length ? (
                        <div className="alert alert-danger">{Object.values(form.errors).join(' · ')}</div>
                    ) : null}
                    <div className="ps-dialog-footer">
                        <button type="button" className="btn btn-default btn-sm" onClick={() => setEditorOpen(false)}>
                            {t('common.close')}
                        </button>
                        <button className="btn btn-primary btn-sm" disabled={form.processing}>
                            <i className="fa fa-save" /> {t('pages.phone_blacklist.update')}
                        </button>
                    </div>
                </form>
            </PushsaleDialog>

            <PushsaleDialog
                open={guideOpen}
                onOpenChange={(open) => !open && setGuideOpen(false)}
                title={t('pages.phone_blacklist.guide_title')}
                width="760px"
                className="ps-blacklist-guide-dialog"
            >
                <div className="ps-guide-note">
                    <p><b>{t('pages.phone_blacklist.guide_purpose')}</b></p>
                    <ul>
                        <li>{t('pages.phone_blacklist.guide_li_1')}</li>
                        <li>{t('pages.phone_blacklist.guide_li_2')}</li>
                        <li>{t('pages.phone_blacklist.guide_li_3')}</li>
                        <li>{t('pages.phone_blacklist.guide_li_4')}</li>
                    </ul>
                </div>
            </PushsaleDialog>
        </AppLayout>
    );
}
