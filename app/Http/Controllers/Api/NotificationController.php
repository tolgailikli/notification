<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateNotificationRequest;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;

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
}
