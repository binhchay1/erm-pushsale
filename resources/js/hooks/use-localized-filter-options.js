import { useMemo } from 'react';

import { useLabels } from '@/hooks/use-labels';
import { useT } from '@/providers/I18nProvider';

function mapEnumOptions(options, labelMap) {
    if (!options?.length || !labelMap) {
        return options;
    }

    return options.map((opt) => ({
        ...opt,
        label: labelMap[opt.value] ?? opt.label,
    }));
}

/** Re-label server filter enum options using active client locale. */
export function useLocalizedFilterOptions(filterOptions) {
    const labels = useLabels();
    const t = useT();

    return useMemo(() => {
        if (!filterOptions) {
            return filterOptions;
        }

        return {
            ...filterOptions,
            dateTypes: mapEnumOptions(filterOptions.dateTypes, labels.date_type),
            discountModes: mapEnumOptions(filterOptions.discountModes, labels.discount_mode),
            deliveryStatuses: mapEnumOptions(filterOptions.deliveryStatuses, labels.delivery_status),
            operationStages: mapEnumOptions(filterOptions.operationStages, labels.operation_stage),
            operationResults: mapEnumOptions(filterOptions.operationResults, labels.operation_result),
            closingStatuses: mapEnumOptions(filterOptions.closingStatuses, labels.closing_status),
            sourceTypes: filterOptions.sourceTypes?.map((opt) => ({
                ...opt,
                label: opt.value === 'standard' ? t('filters.source_standard') : opt.label,
            })),
            reconciliationStatuses: filterOptions.reconciliationStatuses?.map((opt) => ({
                ...opt,
                label: t(`filters.reconciliation_${opt.value}`),
            })),
        };
    }, [filterOptions, labels, t]);
}
