import { Head, Link } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';

import { DeleteRowButton } from '@/components/ui/delete-row-button';
import { PageHeader } from '@/components/layout/PageHeader';
import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import { Button } from '@/components/ui/button';
import { useRoleLabel, useOrgLevelLabel } from '@/hooks/use-labels';
import { useTableSort } from '@/hooks/use-table-sort';
import AppLayout from '@/layouts/AppLayout';
import { useT } from '@/providers/I18nProvider';

export default function UsersIndex({ users }) {
    const t = useT();
    const { sortedRows, sort, toggleSort } = useTableSort(users, { defaultKey: 'name' });

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
                    <table className="w-full min-w-[980px] border-collapse text-xs">
                        <thead>
                            <tr>
                                <Th sortable sortKey="name" sort={sort} onSort={toggleSort}>{t('pages.users.col_name')}</Th>
                                <Th sortable sortKey="email" sort={sort} onSort={toggleSort}>{t('pages.users.email')}</Th>
                                <Th sortable sortKey="role" sort={sort} onSort={toggleSort}>{t('pages.users.col_role')}</Th>
                                <Th sortable sortKey="team_name" sort={sort} onSort={toggleSort}>{t('pages.users.col_team')}</Th>
                                <Th sortable sortKey="org_level" sort={sort} onSort={toggleSort}>{t('pages.users.col_level')}</Th>
                                <Th sortable sortKey="manager_name" sort={sort} onSort={toggleSort}>{t('pages.users.col_manager')}</Th>
                                <Th sortable sortKey="creator_name" sort={sort} onSort={toggleSort}>{t('pages.users.col_creator')}</Th>
                                <Th>{t('pages.actions')}</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {sortedRows.length ? (
                                sortedRows.map((row) => (
                                    <UserRow key={row.id} row={row} t={t} />
                                ))
                            ) : (
                                <tr>
                                    <Td colSpan={8} className="py-8 text-center text-muted-foreground">
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

function UserRow({ row, t }) {
    const roleLabel = useRoleLabel(row.role);
    const orgLevelLabel = useOrgLevelLabel(row.org_level);

    return (
        <tr className="hover:bg-muted/30">
            <Td className="font-medium">{row.name}</Td>
            <Td>{row.email}</Td>
            <Td>{roleLabel}</Td>
            <Td>{row.team_name ?? '—'}</Td>
            <Td>{orgLevelLabel ?? '—'}</Td>
            <Td>{row.manager_name ?? '—'}</Td>
            <Td>{row.creator_name ?? '—'}</Td>
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
    );
}
