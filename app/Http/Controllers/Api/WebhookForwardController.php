<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

/**
 * Receives webhook.site forwards. When webhook.site/d49626cf-... is configured to
 * forward to http://localhost:3457/api/webhook/forward, requests hit this endpoint.
 * Returns provider-style JSON so the notification app can complete the flow locally.
 */
class WebhookForwardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        // Random delay 0.1–10 seconds before responding
        // $delayMicroseconds = random_int(100_000, 10_000_000);
        // usleep($delayMicroseconds);

        $accepted = random_int(1, 100) <= 80; // 80% accepted, 20% failed
        $status = $accepted ? 'accepted' : 'failed';
        $httpStatus = $status == 'accepted' ? 202 : 500;

        Log::info('Webhook forward received', [
            'method' => $request->method(),
            'body' => $request->all(),
            //'delay_seconds' => round($delayMicroseconds / 1_000_000, 2),
            'response_status' => $status,
            'http_status' => $httpStatus,
        ]);

        return response()->json([
            'messageId' => (string) Str::uuid(),
            'status' => $status,
            'timestamp' => now()->toIso8601String(),
        ], $httpStatus);
    }
}
