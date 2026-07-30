<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryStatusImportBatch extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'company_id', 'created_by_user_id', 'batch_code', 'filename', 'is_ghtk', 'state',
        'total_count', 'processed_count', 'pending_count', 'success_count', 'error_count',
        'meta', 'uploaded_at', 'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'is_ghtk' => 'boolean',
            'meta' => 'array',
            'uploaded_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    public function rows(): HasMany
    {
        return $this->hasMany(DeliveryStatusImportRow::class, 'batch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return array{total:int,processed:int,pending:int,success:int,error:int} */
    public function recount(): array
    {
        $total = $this->rows()->count();
        $processed = $this->rows()->where('process_status', 'processed')->count();
        $success = $this->rows()->where('result_status', 'success')->count();
        $error = $this->rows()->where('result_status', 'error')->count();
        $pending = max(0, $total - $processed);

        $this->forceFill([
            'total_count' => $total,
            'processed_count' => $processed,
            'pending_count' => $pending,
            'success_count' => $success,
            'error_count' => $error,
        ])->save();

        return [
            'total' => $total,
            'processed' => $processed,
            'pending' => $pending,
            'success' => $success,
            'error' => $error,
        ];
    }
}
