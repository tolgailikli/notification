<?php

use App\Http\Controllers\Api\NotificationController;
use App\Http\Middleware\NotificationRateLimit;
use Illuminate\Support\Facades\Route;

Route::post('/notifications', [NotificationController::class, 'store'])
    ->middleware(NotificationRateLimit::class);
Route::post('/notifications/batch', [NotificationController::class, 'storeBatch'])
    ->middleware(NotificationRateLimit::class);
Route::get('/notifications', [NotificationController::class, 'list']);
Route::get('/notifications/{id}', [NotificationController::class, 'getById']);
Route::delete('/notifications/{id}', [NotificationController::class, 'cancel']);
