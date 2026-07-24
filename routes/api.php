<?php

use App\Http\Controllers\Api\LawController;
use Illuminate\Support\Facades\Route;

Route::get('/laws/overview', [LawController::class, 'overview']);
Route::get('/laws/suggestions', [LawController::class, 'suggestions']);
Route::get('/laws/search', [LawController::class, 'search']);

Route::get('/laws/{law}/translate', [LawController::class, 'translate'])
    ->middleware(['web', 'auth']);

Route::post('/laws/sync', [LawController::class, 'sync'])
    ->middleware('web');
