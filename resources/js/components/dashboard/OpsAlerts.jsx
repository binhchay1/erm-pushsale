import { AlertCircle, AlertTriangle, CheckCircle2, Info } from 'lucide-react';

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';

const variants = {
    danger: {
        icon: AlertCircle,
        className: 'border-red-200 bg-red-50/60 text-red-700 dark:border-red-900/60 dark:bg-red-950/20 dark:text-red-300',
    },
    warning: {
        icon: AlertTriangle,
        className: 'border-amber-200 bg-amber-50/60 text-amber-700 dark:border-amber-900/60 dark:bg-amber-950/20 dark:text-amber-300',
    },
    info: {
        icon: Info,
        className: 'border-blue-200 bg-blue-50/60 text-blue-700 dark:border-blue-900/60 dark:bg-blue-950/20 dark:text-blue-300',
    },
    success: {
        icon: CheckCircle2,
        className: 'border-emerald-200 bg-emerald-50/60 text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/20 dark:text-emerald-300',
    },
};

export function OpsAlerts({ alerts }) {
    const data = alerts ?? [];

    return (
        <Card>
            <CardHeader>
                <CardTitle>Cảnh báo vận hành</CardTitle>
                <CardDescription>Ưu tiên xử lý các điểm nghẽn ảnh hưởng doanh thu.</CardDescription>
            </CardHeader>
            <CardContent>
                {data.length === 0 ? (
                    <div className="flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50/60 p-4 text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/20 dark:text-emerald-300">
                        <CheckCircle2 className="size-5" />
                        <div>
                            <p className="text-sm font-semibold">Không có cảnh báo nghiêm trọng</p>
                            <p className="text-xs opacity-80">Hệ thống vận hành đang ổn định.</p>
                        </div>
                    </div>
                ) : (
                    <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                        {data.map((alert) => {
                            const variant = variants[alert.type] ?? variants.info;
                            const Icon = variant.icon;

                            return (
                                <div
                                    key={`${alert.type}-${alert.title}`}
                                    className={cn('rounded-xl border p-4', variant.className)}
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <Icon className="size-5 shrink-0" />
                                        <span className="text-2xl font-bold tabular-nums">
                                            {formatNumber(alert.value)}
                                        </span>
                                    </div>
                                    <div className="mt-3">
                                        <p className="text-sm font-semibold">{alert.title}</p>
                                        <p className="mt-1 text-xs opacity-80">{alert.description}</p>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
