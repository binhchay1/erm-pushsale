import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Building2, Copy, Search, UserPlus } from 'lucide-react';
import { useEffect, useState } from 'react';

import AppLayout from '@/layouts/AppLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { StatusBadge } from '@/components/ui/status-badge';
import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import { useRoleLabel } from '@/hooks/use-labels';
import { useTableSort } from '@/hooks/use-table-sort';
import { formatNumber } from '@/lib/format';
import { useT } from '@/providers/I18nProvider';

function StatCard({ label, value }) {
    return (
        <Card>
            <CardContent className="p-4">
                <p className="text-xs font-medium text-muted-foreground">{label}</p>
                <p className="mt-1 text-2xl font-bold tracking-tight">{value}</p>
            </CardContent>
        </Card>
    );
}

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

    return (
        <AppLayout>
            <Head title={t('pages.platform.title')} />

            <div className="space-y-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="flex items-center gap-3">
                        <div className="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <Building2 className="size-5" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight">{t('pages.platform.title')}</h1>
                            <p className="text-sm text-muted-foreground">{t('pages.platform.desc')}</p>
                        </div>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <StatCard label={t('pages.platform.stat_total')} value={formatNumber(stats.total ?? 0)} />
                    <StatCard label={t('pages.platform.stat_active')} value={formatNumber(stats.active ?? 0)} />
                    <StatCard label={t('pages.platform.stat_users')} value={formatNumber(stats.users ?? 0)} />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <UserPlus className="size-4" /> {t('pages.platform.create_title')}
                        </CardTitle>
                        <CardDescription>{t('pages.platform.create_desc')}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={create} className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <div className="space-y-1.5 sm:col-span-2 lg:col-span-1">
                                <Label htmlFor="name">{t('pages.platform.field_name')}</Label>
                                <Input id="name" value={createForm.data.name} onChange={(e) => createForm.setData('name', e.target.value)} required />
                                {createForm.errors.name && <p className="text-xs text-destructive">{createForm.errors.name}</p>}
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="slug">{t('pages.platform.field_slug')}</Label>
                                <Input id="slug" value={createForm.data.slug} onChange={(e) => createForm.setData('slug', e.target.value)} placeholder="abc-corp" />
                                <p className="text-xs text-muted-foreground">
                                    {t('pages.platform.field_slug_hint').replace('{role}', 'sales').replace('{slug}', 'abc-corp').replace('saleops.local', emailDomain)}
                                </p>
                                {createForm.errors.slug && <p className="text-xs text-destructive">{createForm.errors.slug}</p>}
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="owner_name">{t('pages.platform.field_owner_name')}</Label>
                                <Input id="owner_name" value={createForm.data.owner_name} onChange={(e) => createForm.setData('owner_name', e.target.value)} required />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="owner_email">{t('pages.platform.field_owner_email')}</Label>
                                <Input id="owner_email" type="email" value={createForm.data.owner_email} onChange={(e) => createForm.setData('owner_email', e.target.value)} />
                                <p className="text-xs text-muted-foreground">{t('pages.platform.field_owner_email_hint')}</p>
                                {createForm.errors.owner_email && <p className="text-xs text-destructive">{createForm.errors.owner_email}</p>}
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="owner_password">{t('pages.platform.field_owner_password')}</Label>
                                <Input id="owner_password" type="text" value={createForm.data.owner_password} onChange={(e) => createForm.setData('owner_password', e.target.value)} placeholder="password" />
                            </div>
                            <div className="flex items-end sm:col-span-2 lg:col-span-3">
                                <Button type="submit" disabled={createForm.processing}>
                                    {createForm.processing ? t('pages.platform.creating') : t('pages.platform.create_btn')}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <form onSubmit={search} className="flex max-w-sm items-center gap-2">
                    <Input
                        value={searchForm.data.search}
                        onChange={(e) => searchForm.setData('search', e.target.value)}
                        placeholder={t('pages.platform.search_ph')}
                    />
                    <Button type="submit" variant="outline" size="icon">
                        <Search className="size-4" />
                    </Button>
                </form>

                <Card>
                    <CardContent className="overflow-x-auto p-0">
                        <ScrollDataTable className="rounded-none border-0 shadow-none">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr>
                                        <Th sortable sortKey="name" sort={sort} onSort={toggleSort}>{t('pages.platform.col_company')}</Th>
                                        <Th sortable sortKey="owner_name" sort={sort} onSort={toggleSort}>{t('pages.platform.col_owner')}</Th>
                                        <Th sortable sortKey="users_count" sort={sort} onSort={toggleSort} className="text-right">{t('pages.platform.col_users')}</Th>
                                        <Th sortable sortKey="plan" sort={sort} onSort={toggleSort}>{t('pages.platform.col_plan')}</Th>
                                        <Th sortable sortKey="is_active" sort={sort} onSort={toggleSort}>{t('pages.platform.col_status')}</Th>
                                        <Th sortable sortKey="expires_at" sort={sort} onSort={toggleSort}>{t('pages.platform.col_expires')}</Th>
                                        <Th className="text-right">{t('pages.platform.col_actions')}</Th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {sortedRows.length ? (
                                        sortedRows.map((c) => (
                                            <tr key={c.id} className="border-b last:border-0 hover:bg-muted/20">
                                                <Td className="px-4 py-3">
                                                    <div className="flex items-center gap-2">
                                                        <span className="font-medium">{c.name}</span>
                                                        {c.is_internal ? (
                                                            <StatusBadge tone="muted">{t('pages.platform.internal_badge')}</StatusBadge>
                                                        ) : null}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">{c.slug}</div>
                                                </Td>
                                                <Td className="px-4 py-3">
                                                    {c.owner ? (
                                                        <div>
                                                            <div>{c.owner.name}</div>
                                                            <div className="text-xs text-muted-foreground">{c.owner.email}</div>
                                                        </div>
                                                    ) : (
                                                        <span className="text-muted-foreground">{t('pages.platform.no_owner')}</span>
                                                    )}
                                                </Td>
                                                <Td className="px-4 py-3 text-right">{formatNumber(c.users_count)}</Td>
                                                <Td className="px-4 py-3 capitalize">{c.plan}</Td>
                                                <Td className="px-4 py-3">
                                                    <StatusBadge tone={c.is_active ? 'success' : 'danger'}>
                                                        {c.is_active ? t('pages.platform.status_active') : t('pages.platform.status_suspended')}
                                                    </StatusBadge>
                                                </Td>
                                                <Td className="px-4 py-3 text-muted-foreground">{c.expires_at ?? t('pages.platform.no_expiry')}</Td>
                                                <Td className="px-4 py-3 text-right">
                                                    <div className="flex justify-end gap-1">
                                                        <Button size="sm" variant="outline" asChild>
                                                            <Link href={`/platform/companies/${c.id}/accounts`}>{t('pages.platform.view_accounts')}</Link>
                                                        </Button>
                                                        {!c.is_internal ? (
                                                            <Button
                                                                size="sm"
                                                                variant={c.status === 'active' ? 'outline' : 'default'}
                                                                onClick={() => toggle(c)}
                                                            >
                                                                {c.status === 'active' ? t('pages.platform.suspend') : t('pages.platform.activate')}
                                                            </Button>
                                                        ) : null}
                                                    </div>
                                                </Td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <Td colSpan={7} className="px-4 py-10 text-center text-muted-foreground">
                                                {t('pages.platform.empty')}
                                            </Td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </ScrollDataTable>
                    </CardContent>
                </Card>
            </div>

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
        </AppLayout>
    );
}
