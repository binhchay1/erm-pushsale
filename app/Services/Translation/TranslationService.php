<?php

namespace App\Services\Translation;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Best-effort machine translation for free-text content.
 *
 * Every method is fail-safe: any error / disabled config / unreachable provider
 * returns null so callers keep the original text. We never let translation break
 * a request or a queue job.
 */
class TranslationService
{
    public function enabled(): bool
    {
        return (bool) config('translation.enabled');
    }

    /**
     * @return list<string>
     */
    public function targetLocales(): array
    {
        return (array) config('translation.target_locales', ['vi', 'en']);
    }

    public function sourceLocale(): string
    {
        return (string) config('translation.source_locale', 'vi');
    }

    /**
     * Translate a single string. Returns null when unavailable.
     */
    public function translate(string $text, string $from, string $to): ?string
    {
        $text = trim($text);

        if ($text === '' || $from === $to || ! $this->enabled()) {
            return null;
        }

        $cacheKey = 'translation:'.$from.':'.$to.':'.md5($text);

        if (($cached = Cache::get($cacheKey)) !== null) {
            return $cached;
        }

        $translated = $this->run($text, $from, $to);

        if ($translated !== null && $translated !== '') {
            Cache::put($cacheKey, $translated, now()->addDays((int) config('translation.cache_days', 30)));
        }

        return $translated;
    }

    private function run(string $text, string $from, string $to): ?string
    {
        try {
            return match (config('translation.driver')) {
                'libretranslate' => $this->viaLibreTranslate($text, $from, $to),
                default => $this->viaMyMemory($text, $from, $to),
            };
        } catch (Throwable $e) {
            Log::warning('Translation failed', [
                'driver' => config('translation.driver'),
                'from' => $from,
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function viaMyMemory(string $text, string $from, string $to): ?string
    {
        $config = config('translation.drivers.mymemory');

        $response = Http::timeout((int) config('translation.timeout', 8))
            ->get($config['endpoint'], array_filter([
                'q' => $text,
                'langpair' => $from.'|'.$to,
                'de' => $config['email'] ?? null,
            ]));

        if (! $response->ok()) {
            return null;
        }

        $translated = $response->json('responseData.translatedText');

        return is_string($translated) && $translated !== '' ? $translated : null;
    }

    private function viaLibreTranslate(string $text, string $from, string $to): ?string
    {
        $config = config('translation.drivers.libretranslate');

        $response = Http::timeout((int) config('translation.timeout', 8))
            ->asJson()
            ->post($config['endpoint'], array_filter([
                'q' => $text,
                'source' => $from,
                'target' => $to,
                'format' => 'text',
                'api_key' => $config['api_key'] ?? null,
            ]));

        if (! $response->ok()) {
            return null;
        }

        $translated = $response->json('translatedText');

        return is_string($translated) && $translated !== '' ? $translated : null;
    }
}
