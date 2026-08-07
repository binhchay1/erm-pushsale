import { useT } from '@/providers/I18nProvider';

/**
 * Shared sale name cell for sales leader reports (DRY #6).
 */
export function SaleNameCell({
    row,
    sale: saleProp,
    account: accountProp,
    emptyLabel,
    totalLabel,
    isTotal = false,
    className = '',
    as: Component = 'span',
}) {
    const t = useT();
    const resolvedEmptyLabel = emptyLabel ?? t('reports.pushsale.unassigned_sale');
    const resolvedTotalLabel = totalLabel ?? t('reports.pushsale.total');

    if (isTotal) {
        return <Component className={className || undefined}>{resolvedTotalLabel}</Component>;
    }

    const sale = String(saleProp ?? row?.sale ?? '').trim() || resolvedEmptyLabel;
    const account = String(accountProp ?? row?.sale_account ?? '').trim();

    return (
        <Component className={className || undefined}>
            {sale}
            {account ? <small> ({account})</small> : null}
        </Component>
    );
}
