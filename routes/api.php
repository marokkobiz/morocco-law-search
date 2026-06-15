<?php

use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\CorpusController;
use App\Http\Controllers\Api\LawController;
use App\Http\Controllers\BillingController;
use Illuminate\Support\Facades\Route;

Route::get('/corpus/status', [CorpusController::class, 'status']);
Route::get('/laws/overview', [LawController::class, 'overview']);
Route::post('/billing/stripe-webhook', [BillingController::class, 'webhook'])->middleware('throttle:60,1');

Route::prefix('laws')->middleware(['web', 'auth', 'paid', 'throttle:240,1'])->group(function (): void {
    Route::get('/suggestions', [LawController::class, 'suggestions']);
    Route::get('/search', [LawController::class, 'search']);
    Route::get('/{law}/translate', [LawController::class, 'translate']);
    Route::post('/ask', [ChatController::class, 'ask']);
});
