import { Head, Link } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';

import { DeleteRowButton } from '@/components/ui/delete-row-button';
import { PageHeader } from '@/components/layout/PageHeader';
import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout';

export default function UsersIndex({ users }) {
    return (
        <AppLayout>
            <Head title="Nhân viên" />

            <div className="space-y-6">
                <PageHeader
                    title="Nhân viên"
                    description="Quản lý tài khoản và phân quyền vai trò trong hệ thống."
                    actions={
                        <Button asChild>
                            <Link href="/admin/users/create">
                                <Plus className="size-4" />
                                Thêm nhân viên
                            </Link>
                        </Button>
                    }
                />

                <ScrollDataTable>
                    <table className="w-full min-w-[900px] border-collapse text-xs">
                        <thead>
                            <tr>
                                <Th>Họ tên</Th>
                                <Th>Email</Th>
                                <Th>Vai trò</Th>
                                <Th>Team</Th>
                                <Th>Quản lý</Th>
                                <Th>Thao tác</Th>
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
                                                    confirmMessage={`Xóa nhân viên "${row.name}"?`}
                                                />
                                            </div>
                                        </Td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <Td colSpan={6} className="py-8 text-center text-muted-foreground">
                                        Chưa có nhân viên
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
