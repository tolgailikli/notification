<?php

return [
    /*
    | External notification provider: Webhook.site URL (https://webhook.site/{your-uuid}).
    */
    'provider' => [
        'url' => env('NOTIFICATION_WEBHOOK_URL', ''),
        'timeout' => (int) env('NOTIFICATION_WEBHOOK_TIMEOUT', 10),
    ],

    /*
    | Rate limiting uses Laravel's RateLimiter (limiters registered in AppServiceProvider::boot()).
    | The rate limiter uses the default cache store. Set CACHE_STORE=redis in .env to use Redis.
    */
    'rate_limit' => [
        'max_per_second_per_channel' => (int) env('NOTIFICATION_RATE_LIMIT_PER_CHANNEL', 100),
    ],
];
