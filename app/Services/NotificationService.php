<?php

namespace App\Services;

use App\Enums\NotificationPriority;
use App\Enums\NotificationStatus;
use App\Exceptions\ProviderRateLimitException;
use App\Jobs\SendNotificationToProviderJob;
use App\Models\Notification;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    const MAX_BATCH_SIZE = 1000;
    const MAX_RETRIES = 4;
    const COOLDOWN_429 = [3, 5, 8, 15];

    public function create(array $data, ?string $traceId = null): Notification
    {
        if (!empty($data['idempotency_key'])) {
            $existing = Notification::where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                return $existing;
            }
        }

        $notification = Notification::create([
            'batch_id' => $data['batch_id'] ?? null,
            'recipient' => $data['to'] ?? $data['recipient'],
            'channel' => $data['channel'],
            'content' => $data['content'] ?? '',
            'priority' => $data['priority'] ?? NotificationPriority::NORMAL->value,
            'status' => 'pending',
            'idempotency_key' => $data['idempotency_key'] ?? null,
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'retry_count' => 0,
            'trace_id' => $traceId,
        ]);

        SendNotificationToProviderJob::dispatch($notification->id)
            ->onQueue($notification->getPriorityEnum()->queueName());

        return $notification;
    }

    public function sendToProvider(Notification $notification): Notification
    {
        $url = config('notification.provider.url') ?: env('NOTIFICATION_WEBHOOK_URL', '');
        if (empty($url)) {
            return $notification;
        }

        Log::info('Notification provider request', [
            'notification_id' => $notification->uuid,
            'provider_url' => $url,
        ]);

        $timeout = (int) (config('notification.provider.timeout') ?: env('NOTIFICATION_WEBHOOK_TIMEOUT', 10));
        $payload = [
            'to' => $notification->recipient,
            'channel' => $notification->channel,
            'content' => $notification->content,
        ];

        try {
            $response = Http::timeout($timeout)->acceptJson()->post($url, $payload);
        } catch (\Throwable $e) {
            Log::warning('Notification provider request failed (timeout/connection)', [
                'notification_id' => $notification->uuid,
                'provider_url' => $url,
                'timeout_seconds' => $timeout,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        $statusCode = $response->status();
        $body = $response->json();
        $responseBody = $response->body();

        Log::info('Notification provider response', [
            'notification_id' => $notification->uuid,
            'provider_url' => $url,
            'http_status' => $statusCode,
            'body' => $responseBody,
        ]);

        if ($statusCode === 429) {
            $retryAfter = (int) $response->header('Retry-After');
            $cooldown = $retryAfter > 0 ? $retryAfter : self::COOLDOWN_429[min($notification->retry_count, count(self::COOLDOWN_429) - 1)];
            Log::warning('Notification provider rate limit (429)', [
                'notification_id' => $notification->uuid,
                'retry_after_header' => $response->header('Retry-After'),
                'cooldown_seconds' => $cooldown,
                'body' => $responseBody,
            ]);
            throw new ProviderRateLimitException($cooldown);
        } elseif (in_array($statusCode, [200, 202], true) &&
            isset($body['status']) &&
            $body['status'] === 'accepted'
        ) {
            $sentAt = now();
            if (!empty($body['timestamp'])) {
                try {
                    $sentAt = \Illuminate\Support\Carbon::parse($body['timestamp'])->toDateTime();
                } catch (\Throwable) {
                }
            }
            $notification->update([
                'provider_message_id' => $body['messageId'] ?? null,
                'status' => NotificationStatus::SENT->value,
                'sent_at' => $sentAt,
            ]);
            return $notification;
        }

        Log::warning('Notification provider error', [
            'notification_id' => $notification->uuid,
            'http_status' => $statusCode,
            'body' => $responseBody,
        ]);
        return $notification;
    }

    public function createBatch(array $items, ?string $correlationId = null): array
    {
        if (count($items) > self::MAX_BATCH_SIZE) {
            throw new \InvalidArgumentException("Batch size cannot exceed " . self::MAX_BATCH_SIZE);
        }

        $batchId = Str::uuid()->toString();
        $notifications = [];

        foreach ($items as $item) {
            $item['batch_id'] = $batchId;
            $notifications[] = $this->create($item, $correlationId);
        }

        return [
            'batch_id' => $batchId,
            'notifications' => $notifications,
        ];
    }

    public function cancel(string $idOrUuid): ?Notification
    {
        $notification = Notification::where('id', $idOrUuid)
            ->orWhere('uuid', $idOrUuid)
            ->first();

        if (!$notification || !$notification->getStatusEnum()->canCancel()) {
            return null;
        }

        $notification->update(['status' => 'cancelled']);
        return $notification;
    }
}
