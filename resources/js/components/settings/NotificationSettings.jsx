import { Switch } from '@/components/ui/switch';
import { Label } from '@/components/ui/label';

const items = [
    {
        key: 'new_lead',
        label: 'Lead mới',
        description: 'Thông báo khi có khách đổ từ ads / landing',
    },
    {
        key: 'landing_approval',
        label: 'Duyệt Landing / Ladipage',
        description: 'Admin: cần duyệt kết nối · Marketing: đã được duyệt',
    },
    {
        key: 'order_update',
        label: 'Cập nhật đơn hàng',
        description: 'Trạng thái chốt, hủy, thay đổi giá trị đơn',
    },
    {
        key: 'reminder',
        label: 'Nhắc gọi lại',
        description: 'Lịch hẹn telesale đến giờ',
    },
    {
        key: 'delivery_issue',
        label: 'Đơn giao lỗi',
        description: 'Hoàn / delay từ hãng vận chuyển',
    },
    {
        key: 'kpi_alert',
        label: 'Cảnh báo KPI',
        description: 'Chạm ngưỡng doanh số hoặc tỷ lệ chuyển đổi',
    },
    {
        key: 'sound',
        label: 'Âm thanh',
        description: 'Phát tiếng khi có thông báo desktop',
    },
    {
        key: 'desktop',
        label: 'Toast trên màn hình',
        description: 'Hiện popup khi số liệu real-time cập nhật',
    },
    {
        key: 'email_digest',
        label: 'Email tổng hợp ngày',
        description: 'Gửi báo cáo cuối ngày (sắp có)',
    },
];

export function NotificationSettings({ value, onChange }) {
    const toggle = (key, checked) => {
        onChange({ ...value, [key]: checked });
    };

    return (
        <div className="divide-y divide-border rounded-xl border border-border">
            {items.map((item) => (
                <div
                    key={item.key}
                    className="flex items-center justify-between gap-4 px-4 py-3.5"
                >
                    <div className="min-w-0 flex-1">
                        <Label className="text-sm font-medium">{item.label}</Label>
                        <p className="text-xs text-muted-foreground">{item.description}</p>
                    </div>
                    <Switch
                        checked={!!value[item.key]}
                        onCheckedChange={(checked) => toggle(item.key, checked)}
                    />
                </div>
            ))}
        </div>
    );
}
