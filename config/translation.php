<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Auto-translation
    |--------------------------------------------------------------------------
    |
    | Used ONLY for free-text content that has no i18n key (e.g. an admin typing
    | a custom notification). Keyed content is already bilingual on the client.
    | The whole pipeline is resilient: if disabled or the provider is
    | unreachable, the app keeps working and simply shows the source text.
    |
    */

    'enabled' => (bool) env('TRANSLATION_ENABLED', false),

    'driver' => env('TRANSLATION_DRIVER', 'mymemory'),

    // Locale that free-text is usually authored in.
    'source_locale' => env('TRANSLATION_SOURCE_LOCALE', 'vi'),

    // Locales we want every message to exist in.
    'target_locales' => ['vi', 'en'],

    // Hard timeout (seconds) so a slow provider never blocks a worker for long.
    'timeout' => (int) env('TRANSLATION_TIMEOUT', 8),

    // Cache successful translations to avoid hammering the provider.
    'cache_days' => (int) env('TRANSLATION_CACHE_DAYS', 30),

    'drivers' => [

        // Free, no API key required (anonymous quota). Good default.
        'mymemory' => [
            'endpoint' => env('TRANSLATION_MYMEMORY_ENDPOINT', 'https://api.mymemory.translated.net/get'),
            // Optional: providing an email raises the anonymous quota.
            'email' => env('TRANSLATION_MYMEMORY_EMAIL'),
        ],

        // Self-hosted LibreTranslate (recommended for private/internal data).
        'libretranslate' => [
            'endpoint' => env('LIBRETRANSLATE_ENDPOINT', 'http://127.0.0.1:5000/translate'),
            'api_key' => env('LIBRETRANSLATE_API_KEY'),
        ],
    ],
];
