<?php

use App\Http\Controllers\DemoAnalyticsController;
use App\Http\Controllers\FunnelController;
use App\Http\Controllers\PrivacyController;
use App\Http\Middleware\PrivateResponse;
use App\Http\Middleware\VisitorTransaction;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')->middleware([PrivateResponse::class, 'throttle:consumer', VisitorTransaction::class])->group(function () {
    Route::post('privacy/delete', PrivacyController::class)->block(30, 30);
    Route::get('bootstrap', [FunnelController::class, 'bootstrap'])->block(30, 30);
    Route::post('runs', [FunnelController::class, 'start'])->block(30, 30);
    Route::get('runs/{run}', [FunnelController::class, 'show'])->whereUuid('run')->block(30, 30);
    Route::post('runs/{run}/steps/{step}/complete', [FunnelController::class, 'complete'])->whereUuid('run')->block(30, 30);
});

Route::post('api/v1/events', [FunnelController::class, 'exposure'])->middleware([PrivateResponse::class, 'throttle:analytics', VisitorTransaction::class])->block(30, 30);

Route::get('api/v1/demo-analytics', DemoAnalyticsController::class)->middleware([PrivateResponse::class, 'throttle:consumer'])->block(30, 30);
