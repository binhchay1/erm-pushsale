import {
    formatReportNumber,
    formatReportPercent,
} from '@/components/reports/reportFormat';

/**
 * Unified progress bar table cell (DRY #6).
 * Preserves Pushsale `.tdProgress` / `.box-progress` layout.
 *
 * @param {'number'|'percent'|'currency'} format
 * @param {number} [max] Denominator for non-percent bar width
 * @param {boolean} [fillWhenNoMax] When true and no max, fill bar to 100% (SalesTeam/Data style)
 * @param {'locale'|'raw'} [display] `raw` = Math.round (CEO parity); `locale` = vi-VN separators
 */
export function ReportProgressCell({
    value,
    max,
    format = 'number',
    className = '',
    fillWhenNoMax = false,
    infinityLabel = '∞ %',
    display = 'locale',
}) {
    const numeric = Number(value);
    const safeNumeric = Number.isFinite(numeric) ? numeric : 0;
    const maxVal = Number(max) || 0;

    let width = 0;
    if (format === 'percent') {
        width = Math.min(100, Math.max(0, Number.isFinite(numeric) ? numeric : 0));
    } else if (maxVal > 0) {
        width = Math.min(100, Math.max(0, (safeNumeric / maxVal) * 100));
    } else if (fillWhenNoMax) {
        width = 100;
    }

    let text;
    if (format === 'percent') {
        text = formatReportPercent(value, {
            empty: '0 %',
            infinity: infinityLabel,
            spaceBeforeSuffix: true,
        });
    } else if (display === 'raw') {
        text = String(Math.round(safeNumeric));
    } else {
        text = formatReportNumber(value, { empty: '0' });
    }

    return (
        <td className={`tdProgress ${className}`.trim()}>
            <div className="box-progress">
                <div className="progress">
                    <div
                        className="progress-bar"
                        role="progressbar"
                        style={{ width: `${width}%` }}
                        aria-valuenow={safeNumeric}
                        aria-valuemin={0}
                        aria-valuemax={format === 'percent' ? 100 : maxVal || 100}
                    />
                </div>
                <span className="progress-text">{text}</span>
            </div>
        </td>
    );
}

export function sumRows(rows, key) {
    return (rows ?? []).reduce((acc, row) => acc + (Number(row[key]) || 0), 0);
}

export function maxInRows(rows, key) {
    return (rows ?? []).reduce((acc, row) => Math.max(acc, Number(row[key]) || 0), 0);
}

export function formatPct(numerator, denominator) {
    if (!denominator) return '0 %';
    return `${Math.round((numerator / denominator) * 1000) / 10} %`;
}

export function formatNaNPct(numerator, denominator) {
    if (!denominator) return 'NaN %';
    return `${Math.round((numerator / denominator) * 1000) / 10} %`;
}
