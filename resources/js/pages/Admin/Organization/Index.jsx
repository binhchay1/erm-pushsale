import { Head } from '@inertiajs/react';
import { Crown, GitBranch, Users } from 'lucide-react';

import AppLayout from '@/layouts/AppLayout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';

function money(n) {
    return new Intl.NumberFormat('vi-VN').format(n || 0);
}

export default function OrganizationIndex({ teams, rankings }) {
    return (
        <AppLayout>
            <Head title="Tổ chức & xếp hạng" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Cơ cấu tổ chức & xếp hạng nhân viên</h1>
                    <p className="text-sm text-muted-foreground">
                        Quản lý phân nhánh nhóm theo trưởng nhóm và theo dõi hiệu suất từng bộ phận.
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <GitBranch className="size-5" />
                            Cây nhóm nội bộ
                        </CardTitle>
                        <CardDescription>
                            Nhánh team theo chức năng: Marketing, Sale, Kho, Chia số, Kế toán
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ScrollDataTable>
                            <table className="w-full border-collapse text-xs">
                                <thead>
                                    <tr>
                                        <Th>Nhóm</Th>
                                        <Th>Loại nhóm</Th>
                                        <Th>Nhóm cha</Th>
                                        <Th>Trưởng nhóm</Th>
                                        <Th>Số nhân sự</Th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {teams.length ? (
                                        teams.map((team) => (
                                            <tr key={team.id} className="hover:bg-muted/30">
                                                <Td className="font-medium">{team.name}</Td>
                                                <Td>{team.type_label}</Td>
                                                <Td>{team.parent?.name ?? '—'}</Td>
                                                <Td>
                                                    {team.leader?.name ? (
                                                        <span className="inline-flex items-center gap-1">
                                                            <Crown className="size-3 text-amber-600" />
                                                            {team.leader.name}
                                                        </span>
                                                    ) : (
                                                        '—'
                                                    )}
                                                </Td>
                                                <Td>{team.members_count}</Td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <Td colSpan={5} className="py-8 text-center text-muted-foreground">
                                                Chưa có dữ liệu nhóm
                                            </Td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </ScrollDataTable>
                    </CardContent>
                </Card>

                {rankings.map((group) => (
                    <Card key={group.role}>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Users className="size-5" />
                                Xếp hạng: {group.role_label}
                            </CardTitle>
                            <CardDescription>
                                Sắp theo doanh thu/đơn hàng để so sánh hiệu suất trong cùng nhóm
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ScrollDataTable>
                                <table className="w-full border-collapse text-xs">
                                    <thead>
                                        <tr>
                                            <Th>#</Th>
                                            <Th>Nhân viên</Th>
                                            <Th>Nhóm</Th>
                                            <Th>Quản lý trực tiếp</Th>
                                            <Th>Đơn tổng</Th>
                                            <Th>Đơn giao thành công</Th>
                                            <Th>Doanh thu</Th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {group.items.length ? (
                                            group.items.map((row, idx) => (
                                                <tr key={row.id} className="hover:bg-muted/30">
                                                    <Td>{idx + 1}</Td>
                                                    <Td className="font-medium">
                                                        {row.name}{' '}
                                                        {row.is_team_leader && (
                                                            <span className="rounded bg-primary/10 px-1 text-[10px] text-primary">
                                                                LEADER
                                                            </span>
                                                        )}
                                                    </Td>
                                                    <Td>{row.team ?? '—'}</Td>
                                                    <Td>{row.manager ?? '—'}</Td>
                                                    <Td>{row.total_orders}</Td>
                                                    <Td>{row.delivered_orders}</Td>
                                                    <Td>{money(row.revenue)}</Td>
                                                </tr>
                                            ))
                                        ) : (
                                            <tr>
                                                <Td colSpan={7} className="py-6 text-center text-muted-foreground">
                                                    Chưa có dữ liệu
                                                </Td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </ScrollDataTable>
                        </CardContent>
                    </Card>
                ))}
            </div>
        </AppLayout>
    );
}
