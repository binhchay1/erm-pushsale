<?php

namespace App\Models\Pushsale;

class KpiCatalogItem extends BusinessRecord
{
    protected $table = 'kpi_catalog_items';

    protected $fillable = [
        'position_key',
        'kpi_name',
        'daily_budget',
        'daily_clicks',
        'daily_contacts',
        'daily_revenue',
        'daily_new_contacts',
        'daily_new_closed',
        'daily_old_contacts',
        'daily_old_closed',
        'is_active',
        'sort_order',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'daily_budget' => 'integer',
            'daily_clicks' => 'integer',
            'daily_contacts' => 'integer',
            'daily_revenue' => 'integer',
            'daily_new_contacts' => 'integer',
            'daily_new_closed' => 'integer',
            'daily_old_contacts' => 'integer',
            'daily_old_closed' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function isMarketing(): bool
    {
        return $this->position_key === 'marketing';
    }

    public function isSales(): bool
    {
        return $this->position_key === 'sales';
    }
}
