<?php

use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\WebhookForwardController;
use App\Http\Middleware\NotificationRateLimit;
use Illuminate\Support\Facades\Route;

// Webhook.site forward target: receive forwarded requests and return provider-style response
Route::any('/webhook/forward', WebhookForwardController::class);

Route::post('/notifications', [NotificationController::class, 'store'])
    ->middleware(NotificationRateLimit::class);
Route::post('/notifications/batch', [NotificationController::class, 'storeBatch'])
    ->middleware(NotificationRateLimit::class);
Route::get('/notifications', [NotificationController::class, 'list']);
Route::get('/notifications/{id}', [NotificationController::class, 'getById']);
Route::delete('/notifications/{id}', [NotificationController::class, 'cancel']);
