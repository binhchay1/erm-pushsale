import { PushsaleSelect } from '@/components/reports/PushsaleReportChrome';
import { resolveFilterOptions } from '@/config/reportFilters';

/**
 * Shared operation-stage select — options from filterOptions.operationStages
 * (backend: SaleOperationConfigurationService / menu 1.8.1).
 */
export function OperationStageSelect({
    value = '',
    onChange,
    options,
    filterOptions,
    placeholder = '-- Chọn tác nghiệp --',
    className = '',
    disabled = false,
    includeNoOperation = false,
}) {
    const raw = options
        ?? resolveFilterOptions(filterOptions, 'operationStages');

    const list = includeNoOperation
        ? raw
        : raw.filter((option) => {
            const key = option?.value ?? option?.id ?? option?.key;
            return key !== 'no_operation';
        });

    return (
        <PushsaleSelect
            value={value ?? ''}
            options={list}
            placeholder={placeholder}
            onChange={onChange}
            className={className}
            disabled={disabled}
        />
    );
}
