import { useEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

function pad(value) {
    return String(value).padStart(2, '0');
}

function normalizeDate(value) {
    const text = String(value ?? '').trim();
    if (!text) return '';
    if (/^\d{4}-\d{2}-\d{2}/.test(text)) return text.slice(0, 10);

    const slash = text.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})/);
    if (slash) {
        return `${slash[3]}-${slash[2].padStart(2, '0')}-${slash[1].padStart(2, '0')}`;
    }

    return '';
}

function normalizeTime(value, fallback = '00:00') {
    const text = String(value ?? '').trim();
    const match = text.match(/^(\d{1,2}):(\d{2})$/);
    if (!match) return fallback;
    const hour = Math.min(23, Math.max(0, Number(match[1])));
    const minute = Math.min(59, Math.max(0, Number(match[2])));
    return `${pad(hour)}:${pad(minute)}`;
}

function extractTime(value, fallback) {
    const text = String(value ?? '').trim();
    const match = text.match(/\b(\d{1,2}):(\d{2})\b/);
    return match ? normalizeTime(`${match[1]}:${match[2]}`, fallback) : fallback;
}

function displayDate(value) {
    const normalized = normalizeDate(value);
    if (!normalized) return '';
    const [year, month, day] = normalized.split('-');
    return `${day}/${month}/${year}`;
}

function parseLocalDate(value) {
    const normalized = normalizeDate(value);
    if (!normalized) return null;
    const [year, month, day] = normalized.split('-').map(Number);
    return new Date(year, month - 1, day);
}

function toIsoDate(date) {
    if (!(date instanceof Date) || Number.isNaN(date.getTime())) return '';
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

function startOfDay(date) {
    return new Date(date.getFullYear(), date.getMonth(), date.getDate());
}

function addMonths(date, count) {
    return new Date(date.getFullYear(), date.getMonth() + count, 1);
}

function startOfWeekMonday(date) {
    const day = date.getDay();
    const offset = day === 0 ? -6 : 1 - day;
    return startOfDay(new Date(date.getFullYear(), date.getMonth(), date.getDate() + offset));
}

function endOfWeekSunday(date) {
    const start = startOfWeekMonday(date);
    return startOfDay(new Date(start.getFullYear(), start.getMonth(), start.getDate() + 6));
}

function rangeLabel(from, to, fromTime = '00:00', toTime = '23:59', withTime = true) {
    const fromLabel = displayDate(from);
    const toLabel = displayDate(to);
    if (!fromLabel && !toLabel) return '';
    if (!withTime) return `${fromLabel || '...'} - ${toLabel || '...'}`;
    return `${fromLabel || '...'} ${fromTime} - ${toLabel || '...'} ${toTime}`;
}

function compareDates(from, to) {
    const left = normalizeDate(from);
    const right = normalizeDate(to);
    if (!left || !right) return 0;
    return left.localeCompare(right);
}

function buildMonthMatrix(monthDate) {
    const first = new Date(monthDate.getFullYear(), monthDate.getMonth(), 1);
    const start = startOfWeekMonday(first);
    const weeks = [];
    let cursor = start;
    for (let week = 0; week < 6; week += 1) {
        const days = [];
        for (let day = 0; day < 7; day += 1) {
            days.push(new Date(cursor));
            cursor = new Date(cursor.getFullYear(), cursor.getMonth(), cursor.getDate() + 1);
        }
        weeks.push(days);
    }
    return weeks;
}

function presetRanges(now = new Date()) {
    const today = startOfDay(now);
    const yesterday = startOfDay(new Date(today.getFullYear(), today.getMonth(), today.getDate() - 1));
    const last7From = startOfDay(new Date(today.getFullYear(), today.getMonth(), today.getDate() - 6));
    const thisWeekFrom = startOfWeekMonday(today);
    const thisWeekTo = endOfWeekSunday(today);
    const lastWeekTo = startOfDay(new Date(thisWeekFrom.getFullYear(), thisWeekFrom.getMonth(), thisWeekFrom.getDate() - 1));
    const lastWeekFrom = startOfWeekMonday(lastWeekTo);
    const thisMonthFrom = new Date(today.getFullYear(), today.getMonth(), 1);
    const thisMonthTo = new Date(today.getFullYear(), today.getMonth() + 1, 0);
    const lastMonthFrom = new Date(today.getFullYear(), today.getMonth() - 1, 1);
    const lastMonthTo = new Date(today.getFullYear(), today.getMonth(), 0);

    return [
        { key: 'last7', label: '7 ngày vừa qua', from: last7From, to: today },
        { key: 'today', label: 'Hôm nay', from: today, to: today },
        { key: 'yesterday', label: 'Hôm qua', from: yesterday, to: yesterday },
        { key: 'thisWeek', label: 'Tuần này', from: thisWeekFrom, to: thisWeekTo },
        { key: 'lastWeek', label: 'Tuần trước', from: lastWeekFrom, to: lastWeekTo },
        { key: 'thisMonth', label: 'Tháng này', from: thisMonthFrom, to: thisMonthTo },
        { key: 'lastMonth', label: 'Tháng trước', from: lastMonthFrom, to: lastMonthTo },
        { key: 'custom', label: 'Tùy chỉnh', from: null, to: null },
    ];
}

const WEEKDAYS = ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'];
const HOURS = Array.from({ length: 24 }, (_, index) => pad(index));
const MINUTES = ['00', '15', '30', '45', '59'];

function CalendarMonth({ monthDate, from, to, hover, onPick, onPrev, onNext, showPrev, showNext }) {
    const weeks = useMemo(() => buildMonthMatrix(monthDate), [monthDate.getFullYear(), monthDate.getMonth()]);
    const fromIso = normalizeDate(from);
    const toIso = normalizeDate(to);
    const hoverIso = normalizeDate(hover);

    return (
        <div className="ps-drp-calendar">
            <div className="ps-drp-calendar-header">
                {showPrev ? <button type="button" className="ps-drp-nav" onClick={onPrev} aria-label="Tháng trước">&lt;</button> : <span className="ps-drp-nav-spacer" />}
                <strong>Tháng {monthDate.getMonth() + 1} {monthDate.getFullYear()}</strong>
                {showNext ? <button type="button" className="ps-drp-nav" onClick={onNext} aria-label="Tháng sau">&gt;</button> : <span className="ps-drp-nav-spacer" />}
            </div>
            <div className="ps-drp-weekdays">
                {WEEKDAYS.map((day) => <span key={day}>{day}</span>)}
            </div>
            <div className="ps-drp-days">
                {weeks.flat().map((day) => {
                    const iso = toIsoDate(day);
                    const inMonth = day.getMonth() === monthDate.getMonth();
                    const isStart = iso === fromIso;
                    const isEnd = iso === toIso;
                    const rangeEnd = toIso || hoverIso;
                    const inRange = fromIso && rangeEnd && iso >= fromIso && iso <= rangeEnd && fromIso !== rangeEnd;
                    return (
                        <button
                            key={iso + String(inMonth)}
                            type="button"
                            className={[
                                'ps-drp-day',
                                inMonth ? '' : 'is-outside',
                                isStart || isEnd ? 'is-endpoint' : '',
                                inRange ? 'is-in-range' : '',
                            ].filter(Boolean).join(' ')}
                            onClick={() => onPick(iso)}
                            onMouseEnter={() => onPick(iso, { hoverOnly: true })}
                        >
                            {day.getDate()}
                        </button>
                    );
                })}
            </div>
        </div>
    );
}

/**
 * Pushsale reusable date-range filter with presets, dual calendars, and time.
 * Emits date_from / date_to as YYYY-MM-DD (backend filter contract).
 */
export function DateRangeFilter({
    from,
    to,
    onChange,
    className = '',
    inputClassName = '',
    separator = '-',
    label,
    displayLabel = false,
    withTimeLabel = true,
    boxed = false,
    disabled = false,
}) {
    const rootRef = useRef(null);
    const [open, setOpen] = useState(false);
    const [draftFrom, setDraftFrom] = useState(normalizeDate(from));
    const [draftTo, setDraftTo] = useState(normalizeDate(to));
    const [fromTime, setFromTime] = useState(() => extractTime(from, '00:00'));
    const [toTime, setToTime] = useState(() => extractTime(to, '23:59'));
    const [hoverDay, setHoverDay] = useState('');
    const [pickingEnd, setPickingEnd] = useState(Boolean(normalizeDate(from) && normalizeDate(to)));
    const [activePreset, setActivePreset] = useState('custom');
    const [leftMonth, setLeftMonth] = useState(() => parseLocalDate(from) || startOfDay(new Date()));
    const presets = useMemo(() => presetRanges(), [open]);

    useEffect(() => {
        if (!open) {
            setDraftFrom(normalizeDate(from));
            setDraftTo(normalizeDate(to));
            setFromTime(extractTime(from, '00:00'));
            setToTime(extractTime(to, '23:59'));
        }
    }, [from, to, open]);

    useEffect(() => {
        if (!open) return undefined;
        const onDoc = (event) => {
            if (rootRef.current?.contains(event.target)) return;
            if (event.target?.closest?.('.ps-drp-popover')) return;
            setOpen(false);
        };
        document.addEventListener('mousedown', onDoc);
        return () => document.removeEventListener('mousedown', onDoc);
    }, [open]);

    const title = useMemo(
        () => rangeLabel(normalizeDate(from), normalizeDate(to), extractTime(from, '00:00'), extractTime(to, '23:59'), withTimeLabel),
        [from, to, withTimeLabel],
    );
    const draftTitle = rangeLabel(draftFrom, draftTo, fromTime, toTime, withTimeLabel);
    const rightMonth = addMonths(leftMonth, 1);
    const invalid = compareDates(draftFrom, draftTo) > 0;

    const applyPreset = (preset) => {
        setActivePreset(preset.key);
        if (preset.key === 'custom' || !preset.from || !preset.to) return;
        setDraftFrom(toIsoDate(preset.from));
        setDraftTo(toIsoDate(preset.to));
        setFromTime('00:00');
        setToTime('23:59');
        setPickingEnd(true);
        setLeftMonth(new Date(preset.from.getFullYear(), preset.from.getMonth(), 1));
    };

    const pickDay = (iso, { hoverOnly = false } = {}) => {
        if (hoverOnly) {
            if (pickingEnd && draftFrom && !draftTo) setHoverDay(iso);
            return;
        }
        setActivePreset('custom');
        setHoverDay('');
        if (!pickingEnd || !draftFrom || (draftFrom && draftTo)) {
            setDraftFrom(iso);
            setDraftTo('');
            setPickingEnd(true);
            return;
        }
        if (compareDates(draftFrom, iso) > 0) {
            setDraftFrom(iso);
            setDraftTo(draftFrom);
        } else {
            setDraftTo(iso);
        }
        setPickingEnd(false);
    };

    const commit = () => {
        if (!draftFrom || !draftTo || invalid) return;
        onChange?.({
            date_from: draftFrom,
            date_to: draftTo,
            time_from: fromTime,
            time_to: toTime,
        });
        setOpen(false);
    };

    const cancel = () => {
        setDraftFrom(normalizeDate(from));
        setDraftTo(normalizeDate(to));
        setOpen(false);
    };

    const popover = open && typeof document !== 'undefined' ? createPortal(
        <div className="ps-drp-popover" style={(() => {
            const rect = rootRef.current?.getBoundingClientRect();
            if (!rect) return { top: 80, left: 24 };
            const width = 720;
            const left = Math.min(Math.max(8, rect.left), window.innerWidth - width - 8);
            const top = Math.min(rect.bottom + 4, window.innerHeight - 420);
            return { top, left, width };
        })()}
        >
            <div className="ps-drp-ranges">
                <ul>
                    {presets.map((preset) => (
                        <li key={preset.key}>
                            <button
                                type="button"
                                className={activePreset === preset.key ? 'is-active' : ''}
                                onClick={() => applyPreset(preset)}
                            >
                                {preset.label}
                            </button>
                        </li>
                    ))}
                </ul>
            </div>
            <div className="ps-drp-calendars">
                <CalendarMonth
                    monthDate={leftMonth}
                    from={draftFrom}
                    to={draftTo}
                    hover={hoverDay}
                    onPick={pickDay}
                    onPrev={() => setLeftMonth((current) => addMonths(current, -1))}
                    onNext={() => setLeftMonth((current) => addMonths(current, 1))}
                    showPrev
                    showNext={false}
                />
                <CalendarMonth
                    monthDate={rightMonth}
                    from={draftFrom}
                    to={draftTo}
                    hover={hoverDay}
                    onPick={pickDay}
                    onPrev={() => setLeftMonth((current) => addMonths(current, -1))}
                    onNext={() => setLeftMonth((current) => addMonths(current, 1))}
                    showPrev={false}
                    showNext
                />
                <div className="ps-drp-times">
                    <div className="ps-drp-time-group">
                        <select value={fromTime.slice(0, 2)} onChange={(event) => setFromTime(`${event.target.value}:${fromTime.slice(3)}`)}>
                            {HOURS.map((hour) => <option key={`fh-${hour}`} value={hour}>{Number(hour)}</option>)}
                        </select>
                        <span>:</span>
                        <select value={fromTime.slice(3)} onChange={(event) => setFromTime(`${fromTime.slice(0, 2)}:${event.target.value}`)}>
                            {MINUTES.map((minute) => <option key={`fm-${minute}`} value={minute}>{minute}</option>)}
                        </select>
                    </div>
                    <div className="ps-drp-time-group">
                        <select value={toTime.slice(0, 2)} onChange={(event) => setToTime(`${event.target.value}:${toTime.slice(3)}`)}>
                            {HOURS.map((hour) => <option key={`th-${hour}`} value={hour}>{Number(hour)}</option>)}
                        </select>
                        <span>:</span>
                        <select value={toTime.slice(3)} onChange={(event) => setToTime(`${toTime.slice(0, 2)}:${event.target.value}`)}>
                            {MINUTES.map((minute) => <option key={`tm-${minute}`} value={minute}>{minute}</option>)}
                        </select>
                    </div>
                </div>
                <div className="ps-drp-buttons">
                    <span className="ps-drp-selected">{draftTitle || 'Chọn khoảng ngày'}</span>
                    <button type="button" className="btn btn-sm btn-default" onClick={cancel}>Hủy</button>
                    <button type="button" className="btn btn-sm btn-primary" onClick={commit} disabled={!draftFrom || !draftTo || invalid}>Đồng ý</button>
                </div>
            </div>
        </div>,
        document.body,
    ) : null;

    return (
        <div
            ref={rootRef}
            className={`ps-date-filter ps-date-range-control ${boxed ? 'is-boxed' : ''} ${open ? 'is-open' : ''} ${className}`.trim()}
            title={title}
        >
            {label ? <span className="ps-date-filter-title">{label}</span> : null}
            <button
                type="button"
                className={`form-control ps-date-range-input ${inputClassName}`.trim()}
                onClick={() => !disabled && setOpen((current) => !current)}
                disabled={disabled}
            >
                {title || 'Chọn khoảng ngày'}
            </button>
            {displayLabel ? <span className="ps-date-filter-label">{title}</span> : null}
            {popover}
        </div>
    );
}

export { normalizeDate as normalizeDateFilterValue, rangeLabel as dateRangeFilterLabel };
