<?php

namespace App\Models\Pushsale;

use App\Models\Product;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Model;

class WarehouseVoucherLine extends Model
{
    protected $table = 'warehouse_voucher_lines';

    protected $fillable = [
        'warehouse_voucher_id',
        'product_id',
        'document_quantity',
        'quantity',
        'unit_cost',
        'batch_code',
        'expiry_date',
        'location_code',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'document_quantity' => 'integer',
            'quantity' => 'integer',
            'unit_cost' => 'integer',
            'expiry_date' => 'date',
        ];
    }

    public function voucher(): BelongsTo { return $this->belongsTo(WarehouseVoucher::class, 'warehouse_voucher_id'); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
