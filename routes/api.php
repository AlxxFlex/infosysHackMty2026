<?php

use App\Http\Controllers\Api\V1\PlanController;
use App\Http\Controllers\Api\V1\RecommendationController;
use App\Http\Controllers\Api\V1\ShiftController;
use App\Http\Controllers\Api\V1\SimulationController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:120,1')->prefix('v1')->group(function (): void {
    Route::post('simulations', [SimulationController::class, 'store']);
    Route::get('simulations/{run}', [SimulationController::class, 'show']);
    Route::post('simulations/{run}/start', [SimulationController::class, 'start']);
    Route::post('simulations/{run}/pause', [SimulationController::class, 'pause']);
    Route::post('simulations/{run}/resume', [SimulationController::class, 'resume']);
    Route::post('simulations/{run}/tick', [SimulationController::class, 'tick']);
    Route::post('simulations/{run}/finish', [SimulationController::class, 'finish']);
    Route::post('simulations/{run}/events', [SimulationController::class, 'events']);
    Route::get('shifts/{shift}', [ShiftController::class, 'show']);
    Route::get('shifts/{shift}/orders', [ShiftController::class, 'orders']);
    Route::post('shifts/{shift}/recommendations', [RecommendationController::class, 'store']);
    Route::post('shifts/{shift}/plans/accept', [PlanController::class, 'accept']);
});
