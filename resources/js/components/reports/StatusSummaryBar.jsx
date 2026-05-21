import { cn } from '@/lib/utils';

const styles = {
    waitingDelivery: 'bg-blue-600 text-white',
    cancelWaybill: 'bg-blue-900 text-white',
    delivering: 'bg-amber-500 text-white',
    delivered: 'bg-emerald-600 text-white',
    paid: 'bg-emerald-400 text-emerald-950',
    returned: 'bg-red-600 text-white',
};

const labels = {
    waitingDelivery: 'CHỜ GIAO',
    cancelWaybill: 'HỦY VẬN ĐƠN',
    delivering: 'ĐANG GIAO',
    delivered: 'ĐÃ GIAO',
    paid: 'ĐÃ THANH TOÁN',
    returned: 'ĐÃ HOÀN',
};

export function StatusSummaryBar({ summary }) {
    return (
        <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
            {Object.entries(labels).map(([key, label]) => (
                <div
                    key={key}
                    className={cn(
                        'rounded-lg px-4 py-3 text-center text-sm font-semibold shadow-sm',
                        styles[key]
                    )}
                >
                    <div>{label}</div>
                    <div className="text-2xl font-bold tabular-nums">{summary?.[key] ?? 0}</div>
                </div>
            ))}
        </div>
    );
}
