import { Search } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useReportSearch } from '@/hooks/useReportSearch';

function SelectFilter({ label, name, value, options, onChange, placeholder }) {
    return (
        <div className="space-y-1">
            <Label className="text-xs text-muted-foreground">{label}</Label>
            <select
                name={name}
                value={value ?? ''}
                onChange={(e) => onChange(name, e.target.value || null)}
                className="h-8 w-full rounded-lg border border-input bg-background px-2 text-sm"
            >
                <option value="">{placeholder ?? '— Tất cả —'}</option>
                {(options ?? []).map((opt) => (
                    <option key={opt.value ?? opt.id} value={opt.value ?? opt.id}>
                        {opt.label ?? opt.name}
                    </option>
                ))}
            </select>
        </div>
    );
}

export function RankingFilterBar({ routeUrl, filters, filterOptions, periods }) {
    const { search } = useReportSearch(routeUrl, filters);

    const set = (key, val) => search({ [key]: val });

    const applyPeriod = (periodValue) => {
        search({
            period: periodValue,
            date_from: null,
            date_to: null,
        });
    };

    return (
        <div className="space-y-3 rounded-xl border border-border bg-card p-4 shadow-sm">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <p className="text-sm font-semibold text-foreground">Bộ lọc xếp hạng</p>
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
                            {item.label}
                        </button>
                    ))}
                </div>
            </div>

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                <div className="space-y-1">
                    <Label className="text-xs">Từ ngày chốt</Label>
                    <Input
                        type="date"
                        value={filters.date_from ?? ''}
                        onChange={(e) => set('date_from', e.target.value)}
                    />
                </div>
                <div className="space-y-1">
                    <Label className="text-xs">Đến ngày chốt</Label>
                    <Input
                        type="date"
                        value={filters.date_to ?? ''}
                        onChange={(e) => set('date_to', e.target.value)}
                    />
                </div>
                <SelectFilter
                    label="Cách tính DS"
                    name="discount_mode"
                    value={filters.discount_mode}
                    options={filterOptions?.discountModes}
                    onChange={set}
                />
                <SelectFilter
                    label="Tác nghiệp cần"
                    name="operation_stage"
                    value={filters.operation_stage}
                    options={filterOptions?.operationStages}
                    onChange={set}
                    placeholder="— Tất cả TN —"
                />
                <SelectFilter
                    label="Trưởng nhóm"
                    name="team_leader_id"
                    value={filters.team_leader_id}
                    options={filterOptions?.teamLeaders?.map((u) => ({
                        value: u.id,
                        label: u.name,
                    }))}
                    onChange={set}
                    placeholder="— Chọn trưởng nhóm —"
                />
                <SelectFilter
                    label="Nhóm"
                    name="team_id"
                    value={filters.team_id}
                    options={filterOptions?.teams?.map((t) => ({
                        value: t.id,
                        label: t.name,
                    }))}
                    onChange={set}
                    placeholder="— Chọn nhóm —"
                />
            </div>

            <div className="flex justify-end">
                <Button size="sm" onClick={() => search()}>
                    <Search className="size-4" />
                    Tìm kiếm
                </Button>
            </div>
        </div>
    );
}
