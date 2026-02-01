<?php

namespace App\Services;

use App\Enums\NotificationPriority;
use App\Models\Notification;
use Illuminate\Support\Str;

class NotificationService
{

    const MAX_BATCH_SIZE = 1000;

    public function create(array $data, ?string $traceId = null): Notification
    {
        if (!empty($data['idempotency_key'])) {
            $existing = Notification::where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                return $existing;
            }
        }

        return Notification::create([
            'batch_id' => $data['batch_id'] ?? null,
            'recipient' => $data['to'] ?? $data['recipient'],
            'channel' => $data['channel'],
            'content' => $data['content'] ?? '',
            'priority' => $data['priority'] ?? NotificationPriority::NORMAL->value,
            'status' => 'pending',
            'idempotency_key' => $data['idempotency_key'] ?? null,
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'trace_id' => $traceId,
        ]);
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
