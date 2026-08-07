import { useT } from '@/providers/I18nProvider';
import { translateReportText } from '@/lib/reportI18n';

/**
 * Shared empty table / block states (DRY #14).
 */

export const DEFAULT_TABLE_EMPTY_MESSAGE = 'Chưa có dữ liệu phù hợp với bộ lọc.';
export const DEFAULT_EMPTY_MESSAGE = 'Không có dữ liệu.';

export function TableEmptyRow({
    colSpan = 1,
    message = DEFAULT_TABLE_EMPTY_MESSAGE,
    className = 'text-center',
}) {
    const t = useT();
    const text = translateReportText(t, message, message);

    return (
        <tr>
            <td colSpan={colSpan} className={className}>
                {text}
            </td>
        </tr>
    );
}

export function EmptyState({
    message = DEFAULT_EMPTY_MESSAGE,
    className = 'ps-empty-state text-center',
    as: Component = 'div',
}) {
    const t = useT();
    return <Component className={className}>{translateReportText(t, message, message)}</Component>;
}
