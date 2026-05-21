<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPreference extends Model
{
    public const THEME_DEFAULT = 'brand';

    public const APPEARANCE_SYSTEM = 'system';

    public const APPEARANCE_LIGHT = 'light';

    public const APPEARANCE_DARK = 'dark';

    protected $fillable = [
        'user_id',
        'theme',
        'appearance',
        'notifications',
    ];

    protected function casts(): array
    {
        return [
            'notifications' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function defaultNotifications(): array
    {
        return [
            'new_lead' => true,
            'order_update' => true,
            'reminder' => true,
            'delivery_issue' => true,
            'kpi_alert' => false,
            'sound' => true,
            'desktop' => true,
            'email_digest' => false,
        ];
    }

    public function mergedNotifications(): array
    {
        return array_merge(self::defaultNotifications(), $this->notifications ?? []);
    }

    public function toFrontendArray(): array
    {
        return [
            'theme' => $this->theme,
            'appearance' => $this->appearance,
            'notifications' => $this->mergedNotifications(),
        ];
    }
}
