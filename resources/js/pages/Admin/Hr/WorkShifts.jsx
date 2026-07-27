import { Head, router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';

import { PageHeader } from '@/components/layout/PageHeader';
import { PushsalePageShell } from '@/components/layout/PushsalePageShell';
import AppLayout from '@/layouts/AppLayout';

const SHIFT_NAMES = ['Ca 1', 'Ca 2', 'Ca 3'];

function hourFromRow(row) {
    const raw = String(row?.from_hour ?? row?.fromHour ?? '0');
    const match = raw.match(/(\d{1,2})/);
    return match ? Number(match[1]) : 0;
}

function toHourFromRow(row) {
    const raw = String(row?.to_hour ?? row?.toHour ?? '0');
    const match = raw.match(/(\d{1,2})/);
    return match ? Number(match[1]) : 0;
}

function initialShifts(rows = []) {
    return SHIFT_NAMES.map((name) => {
        const row = rows.find((item) => String(item.name ?? '').trim().toLocaleLowerCase('vi') === name.toLocaleLowerCase('vi'));
        return {
            name,
            from_hour: row ? hourFromRow(row) : 0,
            to_hour: row ? toHourFromRow(row) : 0,
        };
    });
}

function validateShifts(shifts) {
    const occupied = {};
    for (const shift of shifts) {
        const from = Number(shift.from_hour);
        const to = Number(shift.to_hour);
        if (!Number.isInteger(from) || !Number.isInteger(to) || from < 0 || from > 24 || to < 0 || to > 24) {
            return 'Giờ bắt đầu/kết thúc của ba ca phải là số nguyên trong khoảng 0–24.';
        }
        if (from === 0 && to === 0) continue;
        if (from === to) {
            return `${shift.name} có giờ bắt đầu và kết thúc trùng nhau. Để tắt ca, để cả hai ô = 0.`;
        }
        const hours = to > from
            ? Array.from({ length: to - from }, (_, index) => from + index)
            : [...Array.from({ length: 24 - from }, (_, index) => from + index), ...Array.from({ length: to }, (_, index) => index)];
        for (const hour of hours) {
            if (occupied[hour]) {
                return `${shift.name} bị trùng khung giờ với ${occupied[hour]}.`;
            }
            occupied[hour] = shift.name;
        }
    }
    return '';
}

export default function WorkShifts({ rows = [], routeUrl = '/admin/hr/work-shifts' }) {
    const page = usePage();
    const [shifts, setShifts] = useState(() => initialShifts(rows));
    const [error, setError] = useState('');
    const [saving, setSaving] = useState(false);

    const csrf = useMemo(
        () => page.props?.csrf_token
            || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            || '',
        [page.props?.csrf_token],
    );

    const setHour = (index, key, value) => {
        const next = value === '' ? '' : Number(value);
        setShifts((current) => current.map((shift, shiftIndex) => (
            shiftIndex === index ? { ...shift, [key]: next } : shift
        )));
        if (error) setError('');
    };

    const submit = async (event) => {
        event.preventDefault();
        const payload = shifts.map((shift) => ({
            name: shift.name,
            from_hour: Number(shift.from_hour) || 0,
            to_hour: Number(shift.to_hour) || 0,
        }));
        const clientError = validateShifts(payload);
        if (clientError) {
            setError(clientError);
            toast.error(clientError);
            return;
        }

        setSaving(true);
        setError('');
        try {
            const response = await fetch(`${routeUrl}/schedule`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ shifts: payload }),
            });
            const body = await response.json().catch(() => ({}));
            if (!response.ok) {
                const message = body.message
                    || Object.values(body.errors ?? {}).flat().join(' ')
                    || 'Không thể cập nhật ca làm việc.';
                throw new Error(message);
            }
            toast.success(body.message || 'Đã cập nhật ba ca làm việc.');
            router.reload({ preserveScroll: true, only: ['rows'] });
        } catch (exception) {
            const message = exception.message || 'Không thể cập nhật ca làm việc.';
            setError(message);
            toast.error(message);
        } finally {
            setSaving(false);
        }
    };

    return (
        <AppLayout>
            <Head title="Ca làm việc" />
            <PushsalePageShell title="Ca làm việc" pageCode="1.2.3" className="ps-work-shifts-page" collapsible={false}>
                {error ? (
                    <div className="pushsale-error-banner ps-work-shifts-error">
                        <i className="fa fa-exclamation-triangle" /> {error}
                    </div>
                ) : null}

                <form className="ps-work-shifts-form" onSubmit={submit} noValidate>
                    {shifts.map((shift, index) => (
                        <div key={shift.name} className="ps-work-shifts-row">
                            <label className="ps-work-shifts-label">{shift.name}(h):</label>
                            <input
                                className="form-control"
                                type="number"
                                min="0"
                                max="24"
                                step="1"
                                value={shift.from_hour}
                                onChange={(event) => setHour(index, 'from_hour', event.target.value)}
                                aria-label={`${shift.name} từ giờ`}
                            />
                            <input
                                className="form-control"
                                type="number"
                                min="0"
                                max="24"
                                step="1"
                                value={shift.to_hour}
                                onChange={(event) => setHour(index, 'to_hour', event.target.value)}
                                aria-label={`${shift.name} đến giờ`}
                            />
                        </div>
                    ))}

                    <div className="ps-work-shifts-actions">
                        <button type="submit" className="btn btn-sm btn-primary" disabled={saving}>
                            <i className={`fa ${saving ? 'fa-spinner fa-spin' : 'fa-save'}`} /> {saving ? 'Đang cập nhật…' : 'Cập nhật'}
                        </button>
                    </div>
                </form>

                <div className="ps-work-shifts-help">
                    <strong>* Ca làm việc:</strong>
                    <ul>
                        <li>Được tính theo khoảng thời gian trong 0-24h trong mỗi ngày</li>
                        <li>Khoảng thời gian làm việc của ca này không được chứa một phần khoảng thời gian làm việc của ca khác</li>
                        <li>Để tắt một ca, để cả hai ô giờ = 0</li>
                    </ul>
                </div>
            </PushsalePageShell>
        </AppLayout>
    );
}
