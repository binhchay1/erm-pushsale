function displayValue(value, format) {
    if (value == null || value === '') {
        return '0';
    }

    if (format === 'percent') {
        return `${value} %`;
    }

    if (format === 'currency' || format === 'number') {
        return String(Math.round(Number(value)));
    }

    return String(value);
}

export function PushsaleProgressCell({ value, max, format = 'number', className = '' }) {
    const numeric = Number(value) || 0;
    const maxVal = Number(max) || 0;
    const width =
        format === 'percent'
            ? Math.min(100, Math.max(0, numeric))
            : maxVal > 0
              ? Math.min(100, (numeric / maxVal) * 100)
              : 0;

    return (
        <td className={`tdProgress ${className}`.trim()}>
            <div className="box-progress">
                <div className="progress">
                    <div
                        className="progress-bar"
                        role="progressbar"
                        style={{ width: `${width}%` }}
                        aria-valuenow={numeric}
                        aria-valuemin={0}
                        aria-valuemax={format === 'percent' ? 100 : maxVal || 100}
                    />
                </div>
                <span className="progress-text">{displayValue(value, format)}</span>
            </div>
        </td>
    );
}

export function sumRows(rows, key) {
    return rows.reduce((acc, row) => acc + (Number(row[key]) || 0), 0);
}

export function maxInRows(rows, key) {
    return rows.reduce((acc, row) => Math.max(acc, Number(row[key]) || 0), 0);
}

export function formatPct(numerator, denominator) {
    if (!denominator) {
        return '0 %';
    }

    return `${Math.round((numerator / denominator) * 1000) / 10} %`;
}

export function formatNaNPct(numerator, denominator) {
    if (!denominator) {
        return 'NaN %';
    }

    return `${Math.round((numerator / denominator) * 1000) / 10} %`;
}
