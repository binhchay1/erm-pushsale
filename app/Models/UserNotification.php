<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotification extends Model
{
    use BelongsToTenant;

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

    /** Lead notifications that must open log data trùng / ngoại lệ, not phân bổ data. */
    private const LEAD_EXCEPTION_VARIANTS = [
        'duplicate_lead',
        'late_upsell',
        'orphan_upsell',
    ];

    public function resolvedUrl(): ?string
    {
        $url = $this->url;
        if ($this->type !== 'lead') {
            return $url;
        }

        $variant = is_array($this->data) ? ($this->data['variant'] ?? null) : null;
        if (! in_array($variant, self::LEAD_EXCEPTION_VARIANTS, true)) {
            return $url;
        }

        $path = strtok($url ?? '', '?') ?: '';
        if ($path === '/admin/leads' || $path === '/admin/leads/allocate' || $path === '') {
            return '/admin/leads/log?bucket=exceptions';
        }
        if ($path === '/allocator/workspace' || $path === '/allocator/leads') {
            return '/allocator/leads/log?bucket=exceptions';
        }

        return $url;
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
            'url' => $this->resolvedUrl(),
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
