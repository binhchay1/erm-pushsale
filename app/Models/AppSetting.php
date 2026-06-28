<?php

namespace App\Models;

use App\Support\TenantManager;
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

    /**
     * Tách cài đặt theo công ty — trừ key bắt đầu bằng `platform:` (cấu hình nền tảng, không theo tenant).
     */
    private static function scopedKey(string $key): string
    {
        if (str_starts_with($key, 'platform:')) {
            return $key;
        }

        $tenant = app(TenantManager::class);
        $companyId = $tenant->hasContext() ? $tenant->id() : null;

        return $companyId !== null ? 'c'.$companyId.':'.$key : $key;
    }

    public static function getPlatform(string $key, ?string $default = null): ?string
    {
        return self::get('platform:'.$key, $default);
    }

    public static function setPlatform(string $key, ?string $value): void
    {
        self::set('platform:'.$key, $value);
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $scoped = self::scopedKey($key);

        try {
            $value = Cache::rememberForever(
                self::CACHE_PREFIX.$scoped,
                fn () => self::query()->whereKey($scoped)->value('value'),
            );
        } catch (\Throwable) {
            // Bảng settings chưa migrate / lỗi DB tạm thời → giữ hành vi mặc định.
            return $default;
        }

        return $value ?? $default;
    }

    public static function set(string $key, ?string $value): void
    {
        $scoped = self::scopedKey($key);
        self::query()->updateOrCreate(['key' => $scoped], ['value' => $value]);
        Cache::forget(self::CACHE_PREFIX.$scoped);
    }
}
