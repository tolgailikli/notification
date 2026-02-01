<?php

namespace App\Services;

use App\Enums\NotificationPriority;
use App\Models\Notification;

class NotificationService
{

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
}
