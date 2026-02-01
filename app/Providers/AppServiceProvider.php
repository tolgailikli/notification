<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $perSecond = (int) config('notification.rate_limit.max_per_second_per_channel', 100);

        foreach (['sms', 'email', 'push'] as $channel) {
            RateLimiter::for('notification-' . $channel, fn () => Limit::perSecond($perSecond)->by($channel));
        }
    }
}
