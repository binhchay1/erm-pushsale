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
    return (
        <tr>
            <td colSpan={colSpan} className={className}>
                {message}
            </td>
        </tr>
    );
}

export function EmptyState({
    message = DEFAULT_EMPTY_MESSAGE,
    className = 'ps-empty-state text-center',
    as: Component = 'div',
}) {
    return <Component className={className}>{message}</Component>;
}
