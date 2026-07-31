import { Search } from 'lucide-react';

import { DateRangeFilter } from '@/components/filters/DateRangeFilter';
import { OperationStageSelect } from '@/components/filters/OperationStageSelect';
import { SelectFilter } from '@/components/filters/SelectFilter';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useLocalizedFilterOptions } from '@/hooks/use-localized-filter-options';
import { useInertiaFilters } from '@/hooks/useInertiaFilters';
import { useT } from '@/providers/I18nProvider';

export function RankingFilterBar({ routeUrl, filters, filterOptions, periods }) {
    const t = useT();
    const localizedOptions = useLocalizedFilterOptions(filterOptions);
    const { search } = useInertiaFilters(routeUrl, filters, { sync: false });

    const set = (key, val) => search({ [key]: val });

    const applyPeriod = (periodValue) => {
        search({
            period: periodValue,
            date_from: null,
            date_to: null,
        });
    };

    return (
        <div className="space-y-4 rounded-lg bg-card p-5 shadow-sm">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <p className="text-sm font-semibold text-foreground">{t('rankings.filter_title')}</p>
                <div className="inline-flex rounded-lg border bg-muted/30 p-0.5">
                    {periods.map((item) => (
                        <button
                            key={item.value}
                            type="button"
                            onClick={() => applyPeriod(item.value)}
                            className={`rounded-md px-3 py-1 text-xs font-medium transition-colors ${
                                filters.period === item.value
                                    ? 'bg-primary text-primary-foreground shadow-sm'
                                    : 'text-muted-foreground hover:text-foreground'
                            }`}
                        >
                            {t(`filters.periods.${item.value}`)}
                        </button>
                    ))}
                </div>
            </div>

            <div className="grid gap-x-5 gap-y-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                <div className="space-y-1 sm:col-span-2">
                    <Label className="text-xs">{t('rankings.from_date')} / {t('rankings.to_date')}</Label>
                    <DateRangeFilter
                        from={filters.date_from ?? ''}
                        to={filters.date_to ?? ''}
                        onChange={({ date_from, date_to }) => {
                            search({ date_from, date_to, period: 'custom' });
                        }}
                    />
                </div>
                <SelectFilter
                    label={t('rankings.revenue_calc')}
                    name="discount_mode"
                    value={filters.discount_mode}
                    options={localizedOptions?.discountModes}
                    onChange={set}
                />
                <div className="space-y-1">
                    <Label className="text-xs">{t('rankings.operation_needed')}</Label>
                    <OperationStageSelect
                        value={filters.operation_stage ?? ''}
                        filterOptions={localizedOptions}
                        placeholder={t('rankings.all_operations')}
                        onChange={(value) => set('operation_stage', value)}
                    />
                </div>
                <SelectFilter
                    label={t('rankings.team_leader')}
                    name="team_leader_id"
                    value={filters.team_leader_id}
                    options={filterOptions?.teamLeaders?.map((u) => ({
                        value: u.id,
                        label: u.name,
                    }))}
                    onChange={set}
                    placeholder={t('rankings.select_leader')}
                />
                <SelectFilter
                    label={t('rankings.team')}
                    name="team_id"
                    value={filters.team_id}
                    options={filterOptions?.teams?.map((team) => ({
                        value: team.id,
                        label: team.name,
                    }))}
                    onChange={set}
                    placeholder={t('rankings.select_team')}
                />
            </div>

            <div className="flex justify-end">
                <Button size="sm" onClick={() => search()}>
                    <Search className="size-4" />
                    {t('rankings.search')}
                </Button>
            </div>
        </div>
    );
}
