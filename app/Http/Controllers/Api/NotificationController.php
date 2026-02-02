<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateBatchNotificationRequest;
use App\Http\Requests\CreateNotificationRequest;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService
    ) {}

    public function store(CreateNotificationRequest $request): JsonResponse
    {
        $traceId = $request->attributes->get('correlation_id');
        $notification = $this->notificationService->create($request->validated(), $traceId);

        return response()->json([
            'id' => $notification->uuid,
            'batch_id' => $notification->batch_id,
            'status' => $notification->status,
            'created_at' => $notification->created_at->toIso8601String(),
        ], 202);
    }

    public function storeBatch(CreateBatchNotificationRequest $request): JsonResponse
    {
        $correlationId = $request->attributes->get('correlation_id');
        $result = $this->notificationService->createBatch($request->input('notifications'), $correlationId);

        return response()->json([
            'batch_id' => $result['batch_id'],
            'count' => count($result['notifications']),
            'notifications' => array_map(fn (Notification $n) => [
                'id' => $n->uuid,
                'status' => $n->status,
                'created_at' => $n->created_at->toIso8601String(),
            ], $result['notifications']),
        ], 202);
    }

    public function getById(string $id): JsonResponse
    {
        $notification = Notification::where('uuid', $id)->orWhere('id', $id)->first();
        if (!$notification) {
            return response()->json(['message' => 'Notification not found'], 404);
        }

        return response()->json($this->notificationToArray($notification));
    }

    public function list(Request $request): JsonResponse
    {
        $query = Notification::query()
            ->when($request->filled('batch_id'), fn ($q) => $q->byBatch($request->batch_id))
            ->when($request->filled('status'), fn ($q) => $q->inStatus((array) $request->status))
            ->when($request->filled('channel'), fn ($q) => $q->forChannel($request->channel))
            ->when($request->filled('from'), fn ($q) => $q->where('created_at', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->where('created_at', '<=', $request->to))
            ->orderByDesc('created_at');

        $perPage = max(1, min((int) $request->get('per_page', 15), 100));
        $paginated = $query->paginate($perPage);

        return response()->json([
            'data' => array_map(fn (Notification $n) => $this->notificationToArray($n), $paginated->items()),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    public function cancel(string $id): JsonResponse
    {
        $notification = $this->notificationService->cancel($id);
        if (!$notification) {
            return response()->json(['message' => 'Notification not found or cannot be cancelled'], 404);
        }

        return response()->json([
            'id' => $notification->uuid,
            'status' => $notification->status,
        ]);
    }

    private function notificationToArray(Notification $n): array
    {
        return [
            'id' => $n->uuid,
            'batch_id' => $n->batch_id,
            'recipient' => $n->recipient,
            'channel' => $n->channel,
            'content' => $n->content,
            'priority' => $n->priority,
            'status' => $n->status,
            'scheduled_at' => $n->scheduled_at?->toIso8601String(),
            'sent_at' => $n->sent_at?->toIso8601String(),
            'provider_message_id' => $n->provider_message_id,
            'retry_count' => $n->retry_count,
            'trace_id' => $n->trace_id,
            'created_at' => $n->created_at->toIso8601String(),
            'updated_at' => $n->updated_at->toIso8601String(),
        ];
    }
}
