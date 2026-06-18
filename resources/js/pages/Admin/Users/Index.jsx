import { Head, Link } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';

import { DeleteRowButton } from '@/components/ui/delete-row-button';
import { PageHeader } from '@/components/layout/PageHeader';
import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout';
import { useT } from '@/providers/I18nProvider';

export default function UsersIndex({ users }) {
    const t = useT();

    return (
        <AppLayout>
            <Head title={t('pages.users.title')} />

            <div className="space-y-6">
                <PageHeader
                    title={t('pages.users.title')}
                    description={t('pages.users.desc_index')}
                    actions={
                        <Button asChild>
                            <Link href="/admin/users/create">
                                <Plus className="size-4" />
                                {t('pages.users.create')}
                            </Link>
                        </Button>
                    }
                />

                <ScrollDataTable>
                    <table className="w-full min-w-[900px] border-collapse text-xs">
                        <thead>
                            <tr>
                                <Th>{t('pages.users.col_name')}</Th>
                                <Th>{t('pages.users.email')}</Th>
                                <Th>{t('pages.users.col_role')}</Th>
                                <Th>{t('pages.users.col_team')}</Th>
                                <Th>{t('pages.users.col_level')}</Th>
                                <Th>{t('pages.users.col_manager')}</Th>
                                <Th>{t('pages.actions')}</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {users.length ? (
                                users.map((row) => (
                                    <tr key={row.id} className="hover:bg-muted/30">
                                        <Td className="font-medium">{row.name}</Td>
                                        <Td>{row.email}</Td>
                                        <Td>{row.role_label}</Td>
                                        <Td>{row.team_name ?? '—'}</Td>
                                        <Td>{row.org_level_label ?? '—'}</Td>
                                        <Td>{row.manager_name ?? '—'}</Td>
                                        <Td>
                                            <div className="flex gap-1">
                                                <Button variant="outline" size="icon-sm" asChild>
                                                    <Link href={`/admin/users/${row.id}/edit`}>
                                                        <Pencil className="size-4" />
                                                    </Link>
                                                </Button>
                                                <DeleteRowButton
                                                    url={`/admin/users/${row.id}`}
                                                    label={row.name}
                                                    confirmMessage={t('pages.users.delete_confirm', { name: row.name })}
                                                />
                                            </div>
                                        </Td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <Td colSpan={7} className="py-8 text-center text-muted-foreground">
                                        {t('pages.users.empty')}
                                    </Td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </ScrollDataTable>
            </div>
        </AppLayout>
    );
}
