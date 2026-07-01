<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CarrierSettlementBatch extends Model
{
    use BelongsToTenant;

    public const SOURCE_API = 'api';

    public const SOURCE_IMPORT = 'import';

    public const SOURCE_WEBHOOK = 'webhook';

    protected $fillable = [
        'provider', 'source', 'settlement_code', 'period_from', 'period_to',
        'lines_total', 'lines_matched', 'lines_unmatched', 'cod_total', 'meta', 'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'period_from' => 'date',
            'period_to' => 'date',
            'meta' => 'array',
            'imported_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CarrierSettlementLine::class, 'batch_id');
    }
}
