<?php

namespace App\Models\Pushsale;

use App\Models\LandingConnection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacebookPostMapping extends BusinessRecord
{
    protected $fillable = [
        'facebook_page_mapping_id',
        'page_id',
        'page_name',
        'post_id',
        'content',
        'posted_at',
        'is_used',
        'landing_connection_id',
        'status',
        'metadata',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'posted_at' => 'datetime',
            'is_used' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function fanpage(): BelongsTo
    {
        return $this->belongsTo(FacebookPageMapping::class, 'facebook_page_mapping_id');
    }

    public function landingConnection(): BelongsTo
    {
        return $this->belongsTo(LandingConnection::class, 'landing_connection_id');
    }
}
