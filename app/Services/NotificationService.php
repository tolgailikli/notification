<?php

namespace App\Services;

use App\Enums\NotificationPriority;
use App\Enums\NotificationStatus;
use App\Models\Notification;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    const MAX_BATCH_SIZE = 1000;
    const MAX_RETRIES = 4;
    const COOLDOWN = [3, 5, 8, 15];

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

        $this->sendToProvider($notification);
        $notification->refresh();

        return $notification;
    }

    /**
     * POST notification to external provider (e.g. Webhook.site).
     * Request: { "to", "channel", "content" }
     */
    public function sendToProvider(Notification $notification): Notification
    {
        if ($notification->retry_count >= self::MAX_RETRIES) {
            $notification->update(['status' => NotificationStatus::FAILED->value]);
            return $notification;
        }

        $url = config('notification.provider.url') ?: env('NOTIFICATION_WEBHOOK_URL', '');
        $url = is_string($url) ? trim($url) : '';

        if (empty($url)) {
            return $notification;
        }

        $timeout = (int) (config('notification.provider.timeout') ?: env('NOTIFICATION_WEBHOOK_TIMEOUT', 10));
        $payload = [
            'to' => $notification->recipient,
            'channel' => $notification->channel,
            'content' => $notification->content,
        ];
        $response = Http::timeout($timeout)->acceptJson()->post($url, $payload);
        Log::info('Notification provider response', [
            'notification_id' => $notification->uuid,
            'response' => $response,
        ]);
        $body = $response->json();
        if (in_array($response->status(), [202, 200], true) &&
            isset($body['status']) &&
            $body['status'] === 'accepted'
        ) {
            $sentAt = now();
            if (isset($body['timestamp']) && !empty($body['timestamp'])) {
                try {
                    $sentAt = \Illuminate\Support\Carbon::parse($body['timestamp'])->toDateTime();
                } catch (\Throwable) {  
                }
            }

            $notification->update([
                'provider_message_id' => $body['messageId'] ?? null,
                'status' => NotificationStatus::SENT->value,
                'sent_at' => $sentAt
            ]);
            return $notification;
        } elseif ($response->status() == 429) {
            $cooldown = self::COOLDOWN[$notification->retry_count];
            Log::info('Cooldown webhook.site', [
                'cooldown' => $cooldown
            ]);
            sleep($cooldown);
        }
        sleep(2);
        // retry
        $notification->increment('retry_count');
        $notification->update(['status' => NotificationStatus::PROCESSING->value]);
        Log::warning('Notification provider non-202-200', [
            'notification_id' => $notification->uuid,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);
        return $this->sendToProvider($notification);
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
