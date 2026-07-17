import { Head, Link } from '@inertiajs/react';

import AppLayout from '@/layouts/AppLayout';
import { PageHeader } from '@/components/layout/PageHeader';
import { Button } from '@/components/ui/button';

function JsonBlock({ value }) {
    return (
        <pre className="ps-activity-json">
            {JSON.stringify(value ?? {}, null, 2)}
        </pre>
    );
}

export default function ActivityLogShow({ log }) {
    const rows = [
        ['Thời gian', log?.created_at ?? '—'],
        ['Hành động', log?.action_label ?? log?.action ?? '—'],
        ['Tóm tắt', log?.summary ?? '—'],
        ['Đối tượng', log?.subject_label ?? '—'],
        ['Người thực hiện', log?.actor_name ?? '—'],
        ['Email', log?.actor_email ?? log?.actor?.email ?? '—'],
        ['IP', log?.ip_address ?? '—'],
        ['User agent', log?.user_agent ?? '—'],
    ];

    return (
        <AppLayout>
            <Head title="Chi tiết lịch sử thao tác" />
            <div className="ps-feature-page ps-activity-show-page">
                <PageHeader
                    title="Chi tiết lịch sử thao tác"
                    actions={(
                        <Link href="/admin/activity-logs">
                            <Button type="button" variant="outline">Quay lại</Button>
                        </Link>
                    )}
                />

                <div className="ps-activity-detail-card">
                    <table className="ps-table ps-activity-detail-table">
                        <tbody>
                            {rows.map(([label, value]) => (
                                <tr key={label}>
                                    <th>{label}</th>
                                    <td>{value}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <div className="ps-activity-detail-card">
                    <div className="ps-card-title">Dữ liệu chi tiết</div>
                    <JsonBlock value={log?.properties} />
                </div>
            </div>
        </AppLayout>
    );
}
