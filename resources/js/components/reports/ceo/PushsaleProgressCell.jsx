import {
    ReportProgressCell,
    sumRows,
    maxInRows,
    formatPct,
    formatNaNPct,
} from '@/components/reports/ReportProgressCell';

/** CEO progress cells keep raw Math.round display (no thousand separators). */
export function PushsaleProgressCell(props) {
    return <ReportProgressCell display="raw" {...props} />;
}

export {
    ReportProgressCell,
    sumRows,
    maxInRows,
    formatPct,
    formatNaNPct,
};
