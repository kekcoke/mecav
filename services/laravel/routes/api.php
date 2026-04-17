<?php

use App\Http\Controllers\Api\DiagramController;
use App\Http\Controllers\Api\AiServiceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    // AI service endpoints
    Route::prefix('ai')->name('ai.')->group(function () {
        Route::get ('health',  [AiServiceController::class, 'health'])       ->name('health');
        Route::post('analyze/{diagram}', [AiServiceController::class, 'analyze']) ->name('analyze');
    });

    Route::apiResource('diagrams', DiagramController::class)->names('api.diagrams');

    Route::prefix('diagrams/{diagram}')->name('api.diagrams.')->group(function () {
        Route::get ('snapshots',          [DiagramController::class, 'snapshots'])  ->name('snapshots');
        Route::post('snapshots/{snapshot}/revert', [DiagramController::class, 'revert']) ->name('revert');
        Route::get ('export',             [DiagramController::class, 'export'])     ->name('export');
        Route::post('ai-suggest',         [DiagramController::class, 'aiSuggest'])  ->name('ai-suggest');
    });
});
