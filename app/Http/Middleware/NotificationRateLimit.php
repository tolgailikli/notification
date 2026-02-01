<?php

namespace App\Http\Middleware;

use App\Services\NotificationService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class NotificationRateLimit
{
    private const DECAY_SECONDS = 1; // per-second limit

    /**
     * Enforce per-channel, per-second rate limit using Laravel's RateLimiter
     * (limiters registered in AppServiceProvider::boot()). Uses same key format as ThrottleRequests.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $channelCounts = $this->channelCountsFromRequest($request);

        if (empty($channelCounts)) {
            return $next($request);
        }

        $max = (int) config('notification.rate_limit.max_per_second_per_channel', 100);

        foreach ($channelCounts as $channel => $count) {
            $key = $this->rateLimitKey($channel);
            $lock = Cache::lock('notification_rate_lock:' . $channel, 2);

            if (!$lock->get()) {
                return $this->tooManyRequestsResponse($max);
            }

            try {
                $current = (int) RateLimiter::attempts($key);
                if ($current + $count > $max) {
                    return $this->tooManyRequestsResponse($max);
                }
                RateLimiter::increment($key, self::DECAY_SECONDS, $count);
            } finally {
                $lock->release();
            }
        }

        return $next($request);
    }

    private function rateLimitKey(string $channel): string
    {
        $limiterName = 'notification-' . $channel;
        $limitKey = $channel;

        return md5($limiterName . $limitKey);
    }

    private function channelCountsFromRequest(Request $request): array
    {
        if (!$request->isMethod('POST')) {
            return [];
        }

        if ($request->is('api/notifications') && !$request->is('api/notifications/batch')) {
            return $this->channelCountsFromItem($request->only(['channel']));
        }

        if ($request->is('api/notifications/batch')) {
            $items = $request->input('notifications', []);
            if (count($items) > NotificationService::MAX_BATCH_SIZE) {
                return [];
            }

            return $this->channelCountsFromItems($items);
        }

        return [];
    }

    /** @return array<string, int> */
    private function channelCountsFromItem(array $item): array
    {
        $channel = $item['channel'] ?? null;
        if (!$channel || !in_array($channel, ['sms', 'email', 'push'], true)) {
            return [];
        }

        return [$channel => 1];
    }

    private function channelCountsFromItems(array $items): array
    {
        $counts = [];
        foreach ($items as $item) {
            $channel = $item['channel'] ?? null;
            if (!$channel || !in_array($channel, ['sms', 'email', 'push'], true)) {
                continue;
            }
            $counts[$channel] = ($counts[$channel] ?? 0) + 1;
        }

        return $counts;
    }

    private function tooManyRequestsResponse(int $max): Response
    {
        return response()->json([
            'message' => "Rate limit exceeded. Maximum {$max} messages per second per channel.",
        ], 429);
    }
}
