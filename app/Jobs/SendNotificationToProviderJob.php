<?php

namespace App\Jobs;

use App\Enums\NotificationStatus;
use App\Exceptions\ProviderRateLimitException;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SendNotificationToProviderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $backoff = 5;

    public function __construct(
        public int $notificationId
    ) {}

    public function handle(NotificationService $service): void
    {
        $notification = Notification::find($this->notificationId);

        Log::info('SendNotificationToProviderJob started', [
            'notification_id' => $this->notificationId,
            'job_attempt' => $this->attempts(),
            'job_tries' => $this->tries,
        ]);

        if (!$notification) {
            Log::warning('SendNotificationToProviderJob skipped: notification not found', ['notification_id' => $this->notificationId]);
            return;
        }
        if ($notification->getStatusEnum()->isTerminal()) {
            Log::info('SendNotificationToProviderJob skipped: notification already terminal', [
                'notification_id' => $notification->uuid,
                'status' => $notification->status,
            ]);
            return;
        }

        // Job owns retry_count: stop if max retries reached
        if ($notification->retry_count >= NotificationService::MAX_RETRIES) {
            $notification->update(['status' => NotificationStatus::FAILED->value]);
            Log::info('SendNotificationToProviderJob done: max retries reached', [
                'notification_id' => $notification->uuid,
                'retry_count' => $notification->retry_count,
            ]);
            return;
        }

        try {
            $service->sendToProvider($notification);
        } catch (ProviderRateLimitException $e) {
            Log::warning('SendNotificationToProviderJob rate limited (429), releasing', [
                'notification_id' => $notification->uuid,
                'release_seconds' => $e->getRetryAfterSeconds(),
            ]);
            $this->release($e->getRetryAfterSeconds());
            return;
        } catch (\Throwable $e) {
            // Timeout, connection, etc. – job increments retry_count and retries or fails
            $notification->increment('retry_count');
            $notification->refresh();
            $isFailed = $notification->retry_count >= NotificationService::MAX_RETRIES;
            $notification->update([
                'status' => $isFailed ? NotificationStatus::FAILED->value : NotificationStatus::PROCESSING->value,
            ]);
            Log::warning('SendNotificationToProviderJob attempt failed (exception)', [
                'notification_id' => $notification->uuid,
                'retry_count' => $notification->retry_count,
                'error' => $e->getMessage(),
            ]);
            if ($isFailed) {
                return;
            }
            throw new RuntimeException('Provider request failed: ' . $e->getMessage(), 0, $e);
        }

        $notification->refresh();
        $status = $notification->getStatusEnum();

        if ($status === NotificationStatus::SENT || $status === NotificationStatus::CANCELLED) {
            Log::info('SendNotificationToProviderJob success', [
                'notification_id' => $notification->uuid,
                'status' => $notification->status,
            ]);
            return;
        }
        if ($status->isTerminal()) {
            Log::info('SendNotificationToProviderJob done (failed)', [
                'notification_id' => $notification->uuid,
                'status' => $notification->status,
            ]);
            return;
        }

        // Non-success response (no exception) – job increments retry_count and retries or fails
        $notification->increment('retry_count');
        $notification->refresh();
        $isFailed = $notification->retry_count >= NotificationService::MAX_RETRIES;
        $notification->update([
            'status' => $isFailed ? NotificationStatus::FAILED->value : NotificationStatus::PROCESSING->value,
        ]);
        Log::warning('SendNotificationToProviderJob will retry', [
            'notification_id' => $notification->uuid,
            'retry_count' => $notification->retry_count,
            'status' => $notification->status,
        ]);
        if ($isFailed) {
            return;
        }
        throw new RuntimeException("Notification {$notification->uuid} not sent (status: {$notification->status}). Queue will retry.");
    }
}
