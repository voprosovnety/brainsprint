<?php

use App\Http\Controllers\FunnelController;
use App\Http\Middleware\PrivateResponse;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')->middleware([PrivateResponse::class, 'throttle:consumer'])->group(function () {
    Route::get('bootstrap', [FunnelController::class, 'bootstrap']);
    Route::post('runs', [FunnelController::class, 'start']);
    Route::get('runs/{run}', [FunnelController::class, 'show'])->whereUuid('run');
    Route::post('runs/{run}/steps/{step}/complete', [FunnelController::class, 'complete'])->whereUuid('run');
});
