<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class CustomerSegmentAssignment extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'company_id',
        'phone_key',
        'segment_id',
        'segment_name',
        'successful_order_value',
    ];

    protected function casts(): array
    {
        return [
            'segment_id' => 'integer',
            'successful_order_value' => 'integer',
        ];
    }
}
