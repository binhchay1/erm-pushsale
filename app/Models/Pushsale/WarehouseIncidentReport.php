<?php

namespace App\Models\Pushsale;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseIncidentReport extends BusinessRecord
{
    protected $table = 'warehouse_incident_reports';

    protected $fillable = [
        'manager_user_id',
        'name',
        'document_date',
        'carrier',
        'sender_name',
        'receiver_name',
        'order_count',
        'product_count',
        'status',
        'note',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'document_date' => 'date',
            'order_count' => 'integer',
            'product_count' => 'integer',
        ];
    }

    public function manager(): BelongsTo { return $this->belongsTo(User::class, 'manager_user_id'); }
}
