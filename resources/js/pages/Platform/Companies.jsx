import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Copy } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

import AppLayout from '@/layouts/AppLayout';
import PushsalePageShell from '@/components/layout/PushsalePageShell';
import { TableEmptyRow } from '@/components/reports/TableEmpty';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { FieldError, RequiredMark } from '@/components/ui/field-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useRoleLabel } from '@/hooks/use-labels';
import { useTableSort } from '@/hooks/use-table-sort';
import { formatNumber } from '@/lib/format';
import { useT } from '@/providers/I18nProvider';

function CopyBtn({ value, t }) {
    const [copied, setCopied] = useState(false);

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(value);
            setCopied(true);
            setTimeout(() => setCopied(false), 1500);
        } catch {
            /* ignore */
        }
    };

    return (
        <Button type="button" variant="ghost" size="icon" className="size-7 shrink-0" onClick={copy} title={t('common.copy')}>
            <Copy className="size-3.5" />
            {copied ? <span className="sr-only">{t('common.copied')}</span> : null}
        </Button>
    );
}

function ProvisionRoleRow({ row, t }) {
    const roleLabel = useRoleLabel(row.role);

    return (
        <tr className="border-t">
            <td className="py-2 pr-2">{roleLabel}</td>
            <td className="py-2">
                <code className="break-all">{row.email}</code>
            </td>
            <td className="py-2">
                <CopyBtn value={row.email} t={t} />
            </td>
        </tr>
    );
}

export default function PlatformCompanies({ companies = [], stats = {}, filters = {}, emailDomain = 'saleops.local' }) {
    const t = useT();
    const { flash } = usePage().props;
    const [provisionOpen, setProvisionOpen] = useState(false);
    const [provisioned, setProvisioned] = useState(null);
    const [editOpen, setEditOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [editData, setEditData] = useState({ name: '', plan: '', max_users: '', expires_at: '' });
    const [editFile, setEditFile] = useState(null);
    const [removeTemplate, setRemoveTemplate] = useState(false);
    const [editErrors, setEditErrors] = useState({});
    const [editProcessing, setEditProcessing] = useState(false);
    const editFileRef = useRef(null);
    const { sortedRows, sort, toggleSort } = useTableSort(companies, {
        defaultKey: 'name',
        accessors: {
            owner_name: (c) => c.owner?.name ?? '',
        },
    });

    const searchForm = useForm({ search: filters.search ?? '' });
    const createForm = useForm({
        name: '',
        slug: '',
        owner_name: '',
        owner_email: '',
        owner_password: '',
        contact_email: '',
        contact_phone: '',
    });

    useEffect(() => {
        if (flash?.provisioned) {
            setProvisioned(flash.provisioned);
            setProvisionOpen(true);
        }
    }, [flash?.provisioned]);

    const search = (e) => {
        e.preventDefault();
        router.get('/platform/companies', { search: searchForm.data.search }, { preserveState: true, replace: true });
    };

    const create = (e) => {
        e.preventDefault();
        createForm.post('/platform/companies', {
            preserveScroll: true,
            onSuccess: () => createForm.reset(),
        });
    };

    const toggle = (company) => {
        if (company.is_internal) return;
        router.post(`/platform/companies/${company.id}/toggle`, {}, { preserveScroll: true });
    };

    const openEdit = (company) => {
        setEditing(company);
        setEditData({
            name: company.name ?? '',
            plan: company.plan ?? '',
            max_users: company.max_users ?? '',
            expires_at: company.expires_at ?? '',
        });
        setEditFile(null);
        setRemoveTemplate(false);
        setEditErrors({});
        if (editFileRef.current) editFileRef.current.value = '';
        setEditOpen(true);
    };

    const submitEdit = (e) => {
        e.preventDefault();
        setEditProcessing(true);
        setEditErrors({});

        const formData = new FormData();
        formData.append('_method', 'PUT');
        formData.append('name', editData.name);
        formData.append('plan', editData.plan);
        if (editData.max_users !== '') formData.append('max_users', String(editData.max_users));
        if (editData.expires_at) formData.append('expires_at', editData.expires_at);
        if (editFile) formData.append('lead_import_template', editFile);
        if (removeTemplate) formData.append('remove_lead_import_template', '1');

        router.post(`/platform/companies/${editing.id}`, formData, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                setEditOpen(false);
                setEditing(null);
            },
            onError: (errs) => setEditErrors(errs),
            onFinish: () => setEditProcessing(false),
        });
    };

    const sortMark = (key) => {
        if (sort?.key !== key) return '';
        return sort.dir === 'asc' ? ' ↑' : ' ↓';
    };

    return (
        <AppLayout activeMenuCode="10.2">
            <Head title={t('pages.platform.title')} />

            <PushsalePageShell
                title={t('pages.platform.title')}
                subtitle={t('pages.platform.desc')}
                pageCode="10.2"
                className="ps-adminlte-page ps-platform-companies-page"
                headerClassName="ps-platform-companies-header"
                data-page-code="10.2"
                filters={(
                    <form id="ps-platform-companies-filters" className="ps-platform-companies-filters" onSubmit={search}>
                        <input
                            className="form-control"
                            value={searchForm.data.search}
                            onChange={(e) => searchForm.setData('search', e.target.value)}
                            placeholder={t('pages.platform.search_ph')}
                        />
                        <button className="btn btn-sm btn-primary" type="submit">
                            <i className="fa fa-search" /> {t('pages.platform.search_btn')}
                        </button>
                    </form>
                )}
                collapsible={false}
            >

                <div className="ps-platform-companies-stats">
                    <div className="ps-platform-stat">
                        <span className="ps-platform-stat__icon"><i className="fa fa-building-o" aria-hidden="true" /></span>
                        <span className="ps-platform-stat__label">{t('pages.platform.stat_total')}</span>
                        <strong className="ps-platform-stat__value">{formatNumber(stats.total ?? 0)}</strong>
                    </div>
                    <div className="ps-platform-stat">
                        <span className="ps-platform-stat__icon"><i className="fa fa-check-circle" aria-hidden="true" /></span>
                        <span className="ps-platform-stat__label">{t('pages.platform.stat_active')}</span>
                        <strong className="ps-platform-stat__value">{formatNumber(stats.active ?? 0)}</strong>
                    </div>
                    <div className="ps-platform-stat">
                        <span className="ps-platform-stat__icon"><i className="fa fa-users" aria-hidden="true" /></span>
                        <span className="ps-platform-stat__label">{t('pages.platform.stat_users')}</span>
                        <strong className="ps-platform-stat__value">{formatNumber(stats.users ?? 0)}</strong>
                    </div>
                </div>

                <div className="box box-solid ps-platform-create-box">
                    <div className="box-header with-border">
                        <h3 className="box-title">
                            <i className="fa fa-star text-yellow" /> {t('pages.platform.create_title')}
                        </h3>
                    </div>
                    <div className="box-body">
                        <div className="ps-platform-create-intro">
                            <p className="ps-platform-create-desc">{t('pages.platform.create_desc')}</p>
                        </div>
                        <form onSubmit={create} className="ps-platform-create-form">
                            <div className="ps-platform-create-section">
                                <div className="ps-platform-create-section__head">
                                    <span className="ps-platform-create-section__icon"><i className="fa fa-building" aria-hidden="true" /></span>
                                    <div>
                                        <h4>{t('pages.platform.company_section_title')}</h4>
                                        <p>{t('pages.platform.company_section_desc')}</p>
                                    </div>
                                </div>
                                <div className="ps-platform-create-section__fields">
                                    <div className="ps-platform-create-field">
                                        <label htmlFor="name">
                                            {t('pages.platform.field_name')}
                                            <RequiredMark />
                                        </label>
                                        <input
                                            id="name"
                                            className="form-control"
                                            value={createForm.data.name}
                                            aria-invalid={!!createForm.errors.name}
                                            onChange={(e) => createForm.setData('name', e.target.value)}
                                            required
                                        />
                                        <FieldError message={createForm.errors.name} />
                                    </div>
                                    <div className="ps-platform-create-field">
                                        <label htmlFor="slug">{t('pages.platform.field_slug')}</label>
                                        <input
                                            id="slug"
                                            className="form-control"
                                            value={createForm.data.slug}
                                            onChange={(e) => createForm.setData('slug', e.target.value)}
                                            placeholder="abc-corp"
                                        />
                                        <p className="help-block">
                                            {t('pages.platform.field_slug_hint').replace('{role}', 'sales').replace('{slug}', 'abc-corp').replace('saleops.local', emailDomain)}
                                        </p>
                                        {createForm.errors.slug ? <p className="text-danger">{createForm.errors.slug}</p> : null}
                                    </div>
                                </div>
                            </div>

                            <div className="ps-platform-create-section">
                                <div className="ps-platform-create-section__head">
                                    <span className="ps-platform-create-section__icon"><i className="fa fa-user-circle" aria-hidden="true" /></span>
                                    <div>
                                        <h4>{t('pages.platform.owner_section_title')}</h4>
                                        <p>{t('pages.platform.owner_section_desc')}</p>
                                    </div>
                                </div>
                                <div className="ps-platform-create-section__fields">
                                    <div className="ps-platform-create-field">
                                        <label htmlFor="owner_name">
                                            {t('pages.platform.field_owner_name')}
                                            <RequiredMark />
                                        </label>
                                        <input
                                            id="owner_name"
                                            className="form-control"
                                            value={createForm.data.owner_name}
                                            aria-invalid={!!createForm.errors.owner_name}
                                            onChange={(e) => createForm.setData('owner_name', e.target.value)}
                                            required
                                        />
                                        <FieldError message={createForm.errors.owner_name} />
                                    </div>
                                    <div className="ps-platform-create-field">
                                        <label htmlFor="owner_email">{t('pages.platform.field_owner_email')}</label>
                                        <input
                                            id="owner_email"
                                            type="email"
                                            className="form-control"
                                            value={createForm.data.owner_email}
                                            onChange={(e) => createForm.setData('owner_email', e.target.value)}
                                        />
                                        <p className="help-block">{t('pages.platform.field_owner_email_hint')}</p>
                                        {createForm.errors.owner_email ? <p className="text-danger">{createForm.errors.owner_email}</p> : null}
                                    </div>
                                    <div className="ps-platform-create-field">
                                        <label htmlFor="owner_password">{t('pages.platform.field_owner_password')}</label>
                                        <input
                                            id="owner_password"
                                            type="text"
                                            className="form-control"
                                            value={createForm.data.owner_password}
                                            onChange={(e) => createForm.setData('owner_password', e.target.value)}
                                            placeholder="password"
                                        />
                                    </div>
                                </div>
                            </div>

                            <div className="ps-platform-create-actions">
                                <button type="submit" className="btn btn-sm btn-primary" disabled={createForm.processing}>
                                    <i className={`fa ${createForm.processing ? 'fa-spinner fa-spin' : 'fa-plus'}`} />{' '}
                                    {createForm.processing ? t('pages.platform.creating') : t('pages.platform.create_btn')}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div className="box box-solid ps-platform-table-box">
                    <div className="box-header with-border">
                        <h3 className="box-title">
                            <i className="fa fa-list" aria-hidden="true" /> {t('pages.platform.table_title')}
                        </h3>
                    </div>
                    <div className="ps-table-scroll ps-platform-companies-table-wrap">
                        <table className="table table-bordered ps-source-table ps-platform-companies-table">
                        <thead>
                            <tr>
                                <th>
                                    <button type="button" className="ps-sort-btn" onClick={() => toggleSort('name')}>
                                        {t('pages.platform.col_company')}{sortMark('name')}
                                    </button>
                                </th>
                                <th>
                                    <button type="button" className="ps-sort-btn" onClick={() => toggleSort('owner_name')}>
                                        {t('pages.platform.col_owner')}{sortMark('owner_name')}
                                    </button>
                                </th>
                                <th>
                                    <button type="button" className="ps-sort-btn" onClick={() => toggleSort('users_count')}>
                                        {t('pages.platform.col_users')}{sortMark('users_count')}
                                    </button>
                                </th>
                                <th>
                                    <button type="button" className="ps-sort-btn" onClick={() => toggleSort('plan')}>
                                        {t('pages.platform.col_plan')}{sortMark('plan')}
                                    </button>
                                </th>
                                <th>
                                    <button type="button" className="ps-sort-btn" onClick={() => toggleSort('is_active')}>
                                        {t('pages.platform.col_status')}{sortMark('is_active')}
                                    </button>
                                </th>
                                <th>
                                    <button type="button" className="ps-sort-btn" onClick={() => toggleSort('expires_at')}>
                                        {t('pages.platform.col_expires')}{sortMark('expires_at')}
                                    </button>
                                </th>
                                <th>{t('pages.platform.col_actions')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {sortedRows.length ? (
                                sortedRows.map((c) => (
                                    <tr key={c.id}>
                                        <td className="ps-platform-company-cell">
                                            <strong>{c.name}</strong>
                                            {c.is_internal ? (
                                                <span className="label label-default ps-platform-internal-badge">{t('pages.platform.internal_badge')}</span>
                                            ) : null}
                                            <div className="text-muted">{c.slug}</div>
                                        </td>
                                        <td className="ps-platform-owner-cell">
                                            {c.owner ? (
                                                <>
                                                    <div>{c.owner.name}</div>
                                                    <div className="text-muted">{c.owner.email}</div>
                                                </>
                                            ) : (
                                                <span className="text-muted">{t('pages.platform.no_owner')}</span>
                                            )}
                                        </td>
                                        <td className="text-center ps-platform-number-cell"><span className="ps-platform-number-badge">{formatNumber(c.users_count)}</span></td>
                                        <td className="text-center text-capitalize ps-platform-plan-cell">{c.plan}</td>
                                        <td className="text-center">
                                            <span className={`label ${c.is_active ? 'label-success' : 'label-danger'}`}>
                                                {c.is_active ? t('pages.platform.status_active') : t('pages.platform.status_suspended')}
                                            </span>
                                        </td>
                                        <td className="text-center text-muted ps-platform-expiry-cell">{c.expires_at ?? t('pages.platform.no_expiry')}</td>
                                        <td className="ps-platform-actions">
                                            <button type="button" className="btn btn-xs btn-default" onClick={() => openEdit(c)}>
                                                <i className="fa fa-pencil" aria-hidden="true" /> {t('pages.platform.edit_btn')}
                                            </button>
                                            <Link href={`/platform/companies/${c.id}/admins`} className="btn btn-xs btn-default">
                                                <i className="fa fa-user-plus" aria-hidden="true" /> {t('pages.platform.manage_admins')}
                                            </Link>
                                            <Link href={`/platform/companies/${c.id}/accounts`} className="btn btn-xs btn-default">
                                                <i className="fa fa-id-card-o" aria-hidden="true" /> {t('pages.platform.view_accounts')}
                                            </Link>
                                            {!c.is_internal ? (
                                                <button
                                                    type="button"
                                                    className={`btn btn-xs ${c.status === 'active' ? 'btn-warning' : 'btn-success'}`}
                                                    onClick={() => toggle(c)}
                                                >
                                                    {c.status === 'active' ? t('pages.platform.suspend') : t('pages.platform.activate')}
                                                </button>
                                            ) : null}
                                        </td>
                                    </tr>
                                ))
                            ) : (
                                <TableEmptyRow colSpan={7} message={t('pages.platform.empty')} className="text-center ps-empty" />
                            )}
                        </tbody>
                        </table>
                    </div>
                </div>
            </PushsalePageShell>

            <Dialog open={provisionOpen} onOpenChange={setProvisionOpen}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{t('pages.platform.provision_title')}</DialogTitle>
                        <DialogDescription>{t('pages.platform.provision_desc')}</DialogDescription>
                    </DialogHeader>
                    {provisioned && (
                        <div className="space-y-4 text-sm">
                            <div className="rounded-lg border bg-muted/30 p-3">
                                <p className="font-medium">{provisioned.company_name}</p>
                                <p className="text-xs text-muted-foreground">{provisioned.company_slug}</p>
                                <div className="mt-2 flex items-center gap-1">
                                    <code className="flex-1 truncate text-xs">{provisioned.owner_email}</code>
                                    <CopyBtn value={provisioned.owner_email} t={t} />
                                </div>
                                <p className="mt-1 text-xs text-muted-foreground">{provisioned.owner_name}</p>
                                <p className="mt-2 text-xs">
                                    {t('pages.platform.provision_password')}:{' '}
                                    <code className="rounded bg-muted px-1">{provisioned.default_password}</code>
                                </p>
                            </div>
                            <table className="w-full text-xs">
                                <thead>
                                    <tr className="text-muted-foreground">
                                        <th className="pb-2 text-left font-medium">{t('pages.platform.provision_role')}</th>
                                        <th className="pb-2 text-left font-medium">{t('pages.platform.provision_email')}</th>
                                        <th className="w-8" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {(provisioned.suggested_accounts ?? []).map((row) => (
                                        <ProvisionRoleRow key={row.role} row={row} t={t} />
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </DialogContent>
            </Dialog>

            <Dialog open={editOpen} onOpenChange={setEditOpen}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{t('pages.platform.edit_title')}</DialogTitle>
                        <DialogDescription>{t('pages.platform.edit_desc')}</DialogDescription>
                    </DialogHeader>
                    {editing && (
                        <form onSubmit={submitEdit} className="space-y-4">
                            <div className="space-y-1.5">
                                <Label htmlFor="edit_name">{t('pages.platform.field_name')}</Label>
                                <Input
                                    id="edit_name"
                                    value={editData.name}
                                    onChange={(e) => setEditData((d) => ({ ...d, name: e.target.value }))}
                                    required
                                />
                                <FieldError message={editErrors.name} />
                            </div>
                            <div className="grid gap-3 sm:grid-cols-2">
                                <div className="space-y-1.5">
                                    <Label htmlFor="edit_plan">{t('pages.platform.col_plan')}</Label>
                                    <Input
                                        id="edit_plan"
                                        value={editData.plan}
                                        onChange={(e) => setEditData((d) => ({ ...d, plan: e.target.value }))}
                                    />
                                </div>
                                <div className="space-y-1.5">
                                    <Label htmlFor="edit_max_users">{t('pages.platform.col_users')}</Label>
                                    <Input
                                        id="edit_max_users"
                                        type="number"
                                        min={1}
                                        value={editData.max_users}
                                        onChange={(e) => setEditData((d) => ({ ...d, max_users: e.target.value }))}
                                    />
                                </div>
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="edit_expires">{t('pages.platform.col_expires')}</Label>
                                <Input
                                    id="edit_expires"
                                    type="date"
                                    value={editData.expires_at ?? ''}
                                    onChange={(e) => setEditData((d) => ({ ...d, expires_at: e.target.value }))}
                                />
                            </div>

                            <div className="space-y-2 rounded-lg border bg-muted/20 p-3">
                                <p className="text-sm font-medium">{t('pages.platform.template_section_title')}</p>
                                <p className="text-xs text-muted-foreground">{t('pages.platform.template_section_desc')}</p>
                                {editing.has_lead_import_template && editing.lead_import_template_name ? (
                                    <p className="text-xs">
                                        {t('pages.platform.template_current')}:{' '}
                                        <span className="font-medium">{editing.lead_import_template_name}</span>
                                    </p>
                                ) : (
                                    <p className="text-xs text-muted-foreground">{t('pages.platform.template_none')}</p>
                                )}
                                <input
                                    ref={editFileRef}
                                    type="file"
                                    accept=".csv,.txt,.xls,.xlsx"
                                    className="input-soft w-full px-2 py-1.5 text-sm"
                                    onChange={(e) => {
                                        setEditFile(e.target.files?.[0] ?? null);
                                        setRemoveTemplate(false);
                                    }}
                                />
                                {editing.has_lead_import_template ? (
                                    <label className="flex items-center gap-2 text-xs">
                                        <input
                                            type="checkbox"
                                            checked={removeTemplate}
                                            onChange={(e) => {
                                                setRemoveTemplate(e.target.checked);
                                                if (e.target.checked) setEditFile(null);
                                            }}
                                        />
                                        {t('pages.platform.template_remove')}
                                    </label>
                                ) : null}
                                <FieldError message={editErrors.lead_import_template} />
                            </div>

                            <div className="flex justify-end gap-2">
                                <Button type="button" variant="outline" onClick={() => setEditOpen(false)}>
                                    {t('common.cancel')}
                                </Button>
                                <Button type="submit" disabled={editProcessing}>
                                    {editProcessing ? t('common.saving') : t('common.save')}
                                </Button>
                            </div>
                        </form>
                    )}
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
