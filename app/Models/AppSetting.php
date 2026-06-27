<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AppSetting extends Model
{
    protected $table = 'settings';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    private const CACHE_PREFIX = 'app_setting:';

    public static function get(string $key, ?string $default = null): ?string
    {
        try {
            $value = Cache::rememberForever(
                self::CACHE_PREFIX.$key,
                fn () => self::query()->whereKey($key)->value('value'),
            );
        } catch (\Throwable) {
            // Bảng settings chưa migrate / lỗi DB tạm thời → giữ hành vi mặc định.
            return $default;
        }

        return $value ?? $default;
    }

    public static function set(string $key, ?string $value): void
    {
        self::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget(self::CACHE_PREFIX.$key);
    }
}
