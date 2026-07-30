import {
    formatReportMoney,
    formatReportNumber,
    formatReportPercent,
} from '@/components/reports/reportFormat';

function Cell({ as: Component = 'td', className = '', align, children, ...rest }) {
    const alignClass = align ? `text-${align}` : '';
    return (
        <Component className={`${alignClass} ${className}`.trim()} {...rest}>
            {children}
        </Component>
    );
}

/** Empty / missing value placeholder (DRY #9). */
export function EmptyCell({
    as = 'td',
    className = '',
    align = 'center',
    children = '—',
    ...rest
}) {
    return (
        <Cell as={as} className={className} align={align} {...rest}>
            {children}
        </Cell>
    );
}

export function NumberCell({
    value,
    as = 'td',
    className = '',
    align = 'center',
    empty = '0',
    ...rest
}) {
    return (
        <Cell as={as} className={`nowrap ${className}`.trim()} align={align} {...rest}>
            {formatReportNumber(value, { empty })}
        </Cell>
    );
}

export function PercentCell({
    value,
    as = 'td',
    className = '',
    align = 'center',
    empty = '—',
    infinity = '∞ %',
    spaceBeforeSuffix = true,
    digits,
    ...rest
}) {
    return (
        <Cell as={as} className={`nowrap ${className}`.trim()} align={align} {...rest}>
            {formatReportPercent(value, { empty, infinity, spaceBeforeSuffix, digits })}
        </Cell>
    );
}

export function MoneyCell({
    value,
    as = 'td',
    className = '',
    align = 'center',
    empty = '—',
    stripCurrencySymbol = false,
    ...rest
}) {
    return (
        <Cell as={as} className={`nowrap ${className}`.trim()} align={align} {...rest}>
            {formatReportMoney(value, { empty, stripCurrencySymbol })}
        </Cell>
    );
}
