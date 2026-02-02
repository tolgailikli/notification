<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Observability: metrics and health.
 */
class ObservabilityController extends Controller
{
    /**
     * Real-time metrics: queue depth, success/failure rates, latency.
     */
    public function metrics(): JsonResponse
    {
        // Queue depth (per queue)
        $queueDepth = DB::table('jobs')
            ->selectRaw('queue, count(*) as count')
            ->groupBy('queue')
            ->pluck('count', 'queue')
            ->all();

        $failedJobsCount = DB::table('failed_jobs')->count();

        // Notification counts by status (all time)
        $byStatus = Notification::query()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->all();

        $total = array_sum($byStatus);
        $sent = (int) ($byStatus['sent'] ?? 0);
        $failed = (int) ($byStatus['failed'] ?? 0);
        $pending = (int) ($byStatus['pending'] ?? 0);
        $processing = (int) ($byStatus['processing'] ?? 0);
        $cancelled = (int) ($byStatus['cancelled'] ?? 0);
        $completed = $sent + $failed + $cancelled; // terminal with outcome

        $successRate = $completed > 0 ? round($sent / $completed * 100, 2) : null;
        $failureRate = $completed > 0 ? round($failed / $completed * 100, 2) : null;

        // Latency: average seconds from created_at to sent_at for sent (last 24h)
        $latencySeconds = null;
        $driver = Notification::query()->getConnection()->getDriverName();
        try {
            if ($driver === 'mysql') {
                $latencySeconds = Notification::query()
                    ->where('status', 'sent')
                    ->whereNotNull('sent_at')
                    ->where('created_at', '>=', now()->subDay())
                    ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, created_at, sent_at)) as avg_seconds')
                    ->value('avg_seconds');
            } else {
                // SQLite (e.g. tests)
                $latencySeconds = Notification::query()
                    ->where('status', 'sent')
                    ->whereNotNull('sent_at')
                    ->where('created_at', '>=', now()->subDay())
                    ->selectRaw('AVG((julianday(sent_at) - julianday(created_at)) * 86400) as avg_seconds')
                    ->value('avg_seconds');
            }
        } catch (\Throwable) {
            // ignore
        }

        return response()->json([
            'queue' => [
                'depth_by_queue' => $queueDepth,
                'total_pending' => array_sum($queueDepth),
                'failed_jobs' => $failedJobsCount,
            ],
            'notifications' => [
                'total' => $total,
                'by_status' => [
                    'sent' => $sent,
                    'failed' => $failed,
                    'pending' => $pending,
                    'processing' => $processing,
                    'cancelled' => $cancelled,
                ],
                'success_rate_percent' => $successRate,
                'failure_rate_percent' => $failureRate,
            ],
            'latency' => [
                'avg_send_latency_seconds' => $latencySeconds !== null ? round((float) $latencySeconds, 2) : null,
                'description' => 'Average seconds from created_at to sent_at (sent, last 24h)',
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Health check: database, cache (and optionally queue).
     */
    public function health(): JsonResponse
    {
        $checks = [];
        $healthy = true;

        try {
            DB::connection()->getPdo();
            $checks['database'] = 'ok';
        } catch (\Throwable $e) {
            $checks['database'] = 'error';
            $healthy = false;
        }

        try {
            $key = 'health_check_' . uniqid();
            Cache::put($key, 1, 5);
            if (Cache::get($key) !== 1) {
                throw new \RuntimeException('Cache read back failed');
            }
            Cache::forget($key);
            $checks['cache'] = 'ok';
        } catch (\Throwable $e) {
            $checks['cache'] = 'error';
            $healthy = false;
        }

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => $checks,
            'timestamp' => now()->toIso8601String(),
        ], $healthy ? 200 : 503);
    }
}
