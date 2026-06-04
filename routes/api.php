<?php

use App\Http\Controllers\Api\LawController;
use App\Http\Controllers\Api\CorpusController;
use Illuminate\Support\Facades\Route;

Route::get('/corpus/status', [CorpusController::class, 'status']);

Route::prefix('laws')->group(function (): void {
    Route::get('/overview', [LawController::class, 'overview']);
    Route::get('/suggestions', [LawController::class, 'suggestions']);
    Route::get('/search', [LawController::class, 'search']);
    Route::post('/chat', [LawController::class, 'chat']);
    Route::get('/{law}/translate', [LawController::class, 'translate']);
});
