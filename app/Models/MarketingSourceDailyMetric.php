<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingSourceDailyMetric extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'company_id',
        'marketing_source_id',
        'metric_date',
        'utm_source',
        'utm_campaign',
        'budget',
        'clicks',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'metric_date' => 'date',
            'budget' => 'integer',
            'clicks' => 'integer',
        ];
    }

    public function marketingSource(): BelongsTo
    {
        return $this->belongsTo(MarketingSource::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
