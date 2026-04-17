<?php

use App\Http\Controllers\DiagramController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::apiResource('diagrams', DiagramController::class)->names('api.diagrams');

    Route::prefix('diagrams/{diagram}')->name('diagrams.')->group(function () {
        Route::get ('snapshots',          [DiagramController::class, 'snapshots'])  ->name('snapshots');
        Route::post('snapshots/{snapshot}/revert', [DiagramController::class, 'revert']) ->name('revert');
        Route::get ('export',             [DiagramController::class, 'export'])     ->name('export');
        Route::post('ai-suggest',         [DiagramController::class, 'aiSuggest'])  ->name('ai-suggest');
    });
});
