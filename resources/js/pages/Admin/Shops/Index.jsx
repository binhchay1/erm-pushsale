import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

import { PageHeader } from '@/components/layout/PageHeader';
import AppLayout from '@/layouts/AppLayout';
import { PushsaleDialog } from '@/components/ui/pushsale-dialog';
import { useConfirm } from '@/hooks/use-confirm';
import { useT } from '@/providers/I18nProvider';

const emptyShop = {
    id: null,
    name: '',
    code: '',
    is_default: false,
    is_active: true,
    user_ids: [],
};

export default function ShopsIndex({ shops = [], users = [] }) {
    const t = useT();
    const { ask } = useConfirm();
    const [open, setOpen] = useState(false);
    const form = useForm({ ...emptyShop });

    const openCreate = () => {
        form.setData({ ...emptyShop });
        form.clearErrors();
        setOpen(true);
    };

    const openEdit = (shop) => {
        form.setData({
            id: shop.id,
            name: shop.name ?? '',
            code: shop.code ?? '',
            is_default: Boolean(shop.is_default),
            is_active: Boolean(shop.is_active),
            user_ids: [],
        });
        form.clearErrors();
        setOpen(true);
    };

    const submit = (event) => {
        event.preventDefault();
        if (form.data.id) {
            form.put(`/admin/shops/${form.data.id}`, {
                preserveScroll: true,
                onSuccess: () => setOpen(false),
            });
            return;
        }
        form.post('/admin/shops', {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    const remove = async (shop) => {
        const ok = await ask({
            title: t('shops.delete_confirm_title'),
            message: t('shops.delete_confirm_body', { name: shop.name }),
            variant: 'destructive',
        });
        if (!ok) return;
        router.delete(`/admin/shops/${shop.id}`, { preserveScroll: true });
    };

    return (
        <AppLayout>
            <Head title={t('shops.index_title')} />
            <section className="ps-adminlte-page ps-shops-page" data-page-code="1.1.3">
                <PageHeader
                    title={t('shops.index_title')}
                    subtitle={t('shops.index_subtitle')}
                    pageCode="1.1.3"
                    actions={(
                        <>
                            <a className="btn btn-default btn-sm" href="/admin/shops/overview">{t('shops.overview_link')}</a>
                            <button type="button" className="btn btn-primary btn-sm" onClick={openCreate}>
                                <i className="fa fa-plus" aria-hidden="true" /> {t('shops.add')}
                            </button>
                        </>
                    )}
                />
                <div className="table-responsive">
                    <table className="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>{t('shops.col_name')}</th>
                                <th>{t('shops.col_code')}</th>
                                <th>{t('shops.col_default')}</th>
                                <th>{t('shops.col_active')}</th>
                                <th>{t('shops.col_users')}</th>
                                <th>{t('shops.col_actions')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {shops.length === 0 && (
                                <tr>
                                    <td colSpan={6}>{t('shops.empty')}</td>
                                </tr>
                            )}
                            {shops.map((shop) => (
                                <tr key={shop.id}>
                                    <td>{shop.name}</td>
                                    <td>{shop.code}</td>
                                    <td>{shop.is_default ? t('common.yes') : t('common.no')}</td>
                                    <td>{shop.is_active ? t('common.yes') : t('common.no')}</td>
                                    <td>{shop.users_count ?? 0}</td>
                                    <td className="text-nowrap">
                                        <button type="button" className="btn btn-default btn-xs" onClick={() => openEdit(shop)}>
                                            {t('common.edit')}
                                        </button>
                                        {' '}
                                        {!shop.is_default && (
                                            <button type="button" className="btn btn-danger btn-xs" onClick={() => remove(shop)}>
                                                {t('common.delete')}
                                            </button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </section>

            <PushsaleDialog
                open={open}
                onOpenChange={setOpen}
                title={form.data.id ? t('shops.edit_title') : t('shops.create_title')}
            >
                <form onSubmit={submit} className="ps-shop-form">
                    <label>
                        <span>{t('shops.field_name')}</span>
                        <input
                            className="form-control"
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                        />
                        {form.errors.name && <em className="text-danger">{form.errors.name}</em>}
                    </label>
                    <label>
                        <span>{t('shops.field_code')}</span>
                        <input
                            className="form-control"
                            value={form.data.code}
                            onChange={(e) => form.setData('code', e.target.value)}
                            placeholder={t('shops.field_code_hint')}
                        />
                        {form.errors.code && <em className="text-danger">{form.errors.code}</em>}
                    </label>
                    <label className="ps-checkbox">
                        <input
                            type="checkbox"
                            checked={Boolean(form.data.is_default)}
                            onChange={(e) => form.setData('is_default', e.target.checked)}
                        />
                        <span>{t('shops.field_default')}</span>
                    </label>
                    <label className="ps-checkbox">
                        <input
                            type="checkbox"
                            checked={Boolean(form.data.is_active)}
                            onChange={(e) => form.setData('is_active', e.target.checked)}
                        />
                        <span>{t('shops.field_active')}</span>
                    </label>
                    <label>
                        <span>{t('shops.field_users')}</span>
                        <select
                            className="form-control"
                            multiple
                            value={(form.data.user_ids ?? []).map(String)}
                            onChange={(e) => {
                                const ids = Array.from(e.target.selectedOptions).map((o) => Number(o.value));
                                form.setData('user_ids', ids);
                            }}
                        >
                            {users.map((u) => (
                                <option key={u.id} value={u.id}>{u.name} ({u.email})</option>
                            ))}
                        </select>
                    </label>
                    <div className="ps-dialog-actions">
                        <button type="button" className="btn btn-default" onClick={() => setOpen(false)}>{t('common.cancel')}</button>
                        <button type="submit" className="btn btn-primary" disabled={form.processing}>{t('common.save')}</button>
                    </div>
                </form>
            </PushsaleDialog>
        </AppLayout>
    );
}
