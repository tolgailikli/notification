<?php

return [
    /*
    | External notification provider URL.
    | - Local (host): http://localhost:3457/api/webhook/forward
    | - Docker: set NOTIFICATION_WEBHOOK_URL=http://nginx/api/webhook/forward (docker-compose does this).
    | - Production: https://webhook.site/{your-uuid}
    | When APP_ENV=local and not set, defaults to http://localhost:3457/api/webhook/forward.
    */
    'provider' => [
        'url' => env('NOTIFICATION_WEBHOOK_URL') ?: (env('APP_ENV') === 'local' ? 'http://localhost:3457/api/webhook/forward' : ''),
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
