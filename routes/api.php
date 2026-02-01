<?php

use App\Http\Controllers\Api\NotificationController;
use Illuminate\Support\Facades\Route;

Route::post('/notifications', [NotificationController::class, 'store']);
Route::post('/notifications/batch', [NotificationController::class, 'storeBatch']);
Route::get('/notifications', [NotificationController::class, 'list']);
Route::get('/notifications/{id}', [NotificationController::class, 'getById']);
Route::delete('/notifications/{id}', [NotificationController::class, 'cancel']);
