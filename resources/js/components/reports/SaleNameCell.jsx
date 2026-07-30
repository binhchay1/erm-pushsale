/**
 * Shared sale name cell for sales leader reports (DRY #6).
 */
export function SaleNameCell({
    row,
    sale: saleProp,
    account: accountProp,
    emptyLabel = 'Chưa phân sale',
    totalLabel = 'Tổng',
    isTotal = false,
    className = '',
    as: Component = 'span',
}) {
    if (isTotal) {
        return <Component className={className || undefined}>{totalLabel}</Component>;
    }

    const sale = String(saleProp ?? row?.sale ?? '').trim() || emptyLabel;
    const account = String(accountProp ?? row?.sale_account ?? '').trim();

    return (
        <Component className={className || undefined}>
            {sale}
            {account ? <small> ({account})</small> : null}
        </Component>
    );
}
