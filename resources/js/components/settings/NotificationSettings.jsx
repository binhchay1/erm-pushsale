import { Switch } from '@/components/ui/switch';
import { Label } from '@/components/ui/label';
import { useT } from '@/providers/I18nProvider';

const NOTIFICATION_KEYS = [
    'new_lead',
    'landing_approval',
    'order_update',
    'reminder',
    'delivery_issue',
    'kpi_alert',
    'sound',
    'desktop',
    'email_digest',
];

export function NotificationSettings({ value, onChange }) {
    const t = useT();

    const toggle = (key, checked) => {
        onChange({ ...value, [key]: checked });
    };

    return (
        <div className="divide-y divide-border rounded-xl border border-border">
            {NOTIFICATION_KEYS.map((key) => (
                <div
                    key={key}
                    className="flex items-center justify-between gap-4 px-4 py-3.5"
                >
                    <div className="min-w-0 flex-1">
                        <Label className="text-sm font-medium">{t(`notifications.${key}.label`)}</Label>
                        <p className="text-xs text-muted-foreground">{t(`notifications.${key}.description`)}</p>
                    </div>
                    <Switch
                        checked={!!value[key]}
                        onCheckedChange={(checked) => toggle(key, checked)}
                    />
                </div>
            ))}
        </div>
    );
}
