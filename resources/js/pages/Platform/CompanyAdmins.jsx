import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, UserPlus } from 'lucide-react';

import AppLayout from '@/layouts/AppLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { DeleteRowButton } from '@/components/ui/delete-row-button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { StatusBadge } from '@/components/ui/status-badge';
import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import { useT } from '@/providers/I18nProvider';

export default function CompanyAdmins({
    company,
    admins = [],
    suggested_email = '',
    default_password = 'password',
}) {
    const t = useT();

    const createForm = useForm({ name: '', email: '', password: '' });

    const create = (e) => {
        e.preventDefault();
        createForm.post(`/platform/companies/${company.id}/admins`, {
            preserveScroll: true,
            onSuccess: () => createForm.reset(),
        });
    };

    return (
        <AppLayout>
            <Head title={t('pages.platform.admins_title')} />

            <div className="mx-auto max-w-3xl space-y-6">
                <Button variant="ghost" size="sm" asChild>
                    <Link href="/platform/companies">
                        <ArrowLeft className="mr-1 size-4" /> {t('common.back')}
                    </Link>
                </Button>

                <Card>
                    <CardHeader>
                        <div className="flex flex-wrap items-center gap-2">
                            <CardTitle>{t('pages.platform.admins_title')}</CardTitle>
                            {company.is_internal ? (
                                <StatusBadge tone="muted">{t('pages.platform.internal_badge')}</StatusBadge>
                            ) : null}
                        </div>
                        <CardDescription>{t('pages.platform.admins_desc')}</CardDescription>
                        <p className="text-sm font-medium">{company.name}</p>
                        <p className="text-xs text-muted-foreground">{company.slug}</p>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        <form onSubmit={create} className="grid gap-4 sm:grid-cols-3">
                            <div className="space-y-1.5">
                                <Label htmlFor="name">{t('pages.platform.admin_name')}</Label>
                                <Input
                                    id="name"
                                    value={createForm.data.name}
                                    onChange={(e) => createForm.setData('name', e.target.value)}
                                    required
                                />
                                {createForm.errors.name && (
                                    <p className="text-xs text-destructive">{createForm.errors.name}</p>
                                )}
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="email">{t('pages.platform.admin_email')}</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    value={createForm.data.email}
                                    onChange={(e) => createForm.setData('email', e.target.value)}
                                    placeholder={suggested_email}
                                />
                                {createForm.errors.email && (
                                    <p className="text-xs text-destructive">{createForm.errors.email}</p>
                                )}
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="password">{t('pages.platform.admin_password')}</Label>
                                <Input
                                    id="password"
                                    type="text"
                                    value={createForm.data.password}
                                    onChange={(e) => createForm.setData('password', e.target.value)}
                                    placeholder={default_password}
                                />
                            </div>
                            <div className="sm:col-span-3">
                                <Button type="submit" disabled={createForm.processing}>
                                    <UserPlus className="size-4" />
                                    {createForm.processing
                                        ? t('pages.platform.creating')
                                        : t('pages.platform.admin_create_btn')}
                                </Button>
                            </div>
                        </form>

                        <ScrollDataTable>
                            <table className="w-full text-sm">
                                <thead>
                                    <tr>
                                        <Th>{t('pages.platform.admin_name')}</Th>
                                        <Th>{t('pages.platform.admin_email')}</Th>
                                        <Th>{t('pages.platform.col_status')}</Th>
                                        <Th className="text-right">{t('pages.platform.col_actions')}</Th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {admins.length ? (
                                        admins.map((a) => (
                                            <AdminRow key={a.id} company={company} admin={a} t={t} />
                                        ))
                                    ) : (
                                        <tr>
                                            <Td colSpan={4} className="py-8 text-center text-muted-foreground">
                                                {t('pages.platform.admins_empty')}
                                            </Td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </ScrollDataTable>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

function AdminRow({ company, admin, t }) {
    const editForm = useForm({ name: admin.name, password: '' });

    const save = () => {
        editForm.put(`/platform/companies/${company.id}/admins/${admin.id}`, { preserveScroll: true });
    };

    return (
        <tr className="hover:bg-muted/30">
            <Td>
                <Input
                    className="h-8"
                    value={editForm.data.name}
                    onChange={(e) => editForm.setData('name', e.target.value)}
                />
            </Td>
            <Td>
                <code className="text-xs">{admin.email}</code>
            </Td>
            <Td>
                {admin.is_owner ? (
                    <StatusBadge tone="success">{t('pages.platform.owner_badge')}</StatusBadge>
                ) : (
                    <StatusBadge tone="muted">{t('pages.platform.admin_badge')}</StatusBadge>
                )}
            </Td>
            <Td className="text-right">
                <div className="flex items-center justify-end gap-1">
                    <Input
                        className="h-8 w-32"
                        type="text"
                        placeholder={t('pages.platform.admin_reset_password')}
                        value={editForm.data.password}
                        onChange={(e) => editForm.setData('password', e.target.value)}
                    />
                    <Button size="sm" variant="outline" onClick={save} disabled={editForm.processing}>
                        {t('pages.users.save')}
                    </Button>
                    {!admin.is_owner ? (
                        <DeleteRowButton
                            url={`/platform/companies/${company.id}/admins/${admin.id}`}
                            label={admin.name}
                            confirmMessage={t('pages.platform.admin_delete_confirm', { name: admin.name })}
                        />
                    ) : null}
                </div>
            </Td>
        </tr>
    );
}
