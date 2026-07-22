<?php

use App\Http\Controllers\Api\LawController;
use Illuminate\Support\Facades\Route;

Route::get('/laws/overview', [LawController::class, 'overview']);

Route::prefix('laws')->middleware(['web', 'auth', 'throttle:240,1'])->group(function (): void {
    Route::get('/suggestions', [LawController::class, 'suggestions']);
    Route::get('/search', [LawController::class, 'search']);
    Route::get('/{law}/translate', [LawController::class, 'translate']);
});

Route::post('/laws/sync', [LawController::class, 'sync'])
    ->middleware('web');
