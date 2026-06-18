<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotification extends Model
{
    protected $fillable = [
        'user_id', 'type', 'title', 'message', 'data', 'url', 'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'data' => 'array',
        ];
    }

    /** @return array<string, mixed> */
    public function toFrontendArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'message' => $this->message,
            'data' => $this->data,
            'url' => $this->url,
            'is_read' => (bool) $this->read_at,
            'created_at' => $this->created_at?->diffForHumans(),
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }
}
