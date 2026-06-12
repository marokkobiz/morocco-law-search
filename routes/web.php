<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\CorpusStatusController;
use App\Http\Controllers\PublicPageController;
use Illuminate\Support\Facades\Route;

// Public Pages
Route::controller(PublicPageController::class)->name('pages.')->group(function () {
    Route::get('/', 'comingSoon')->name('home');
    Route::get('/test', 'home')->name('test');
    Route::get('/corpus/status', 'corpusStatus')->name('corpus.status');
});

// Protected App Pages
Route::middleware(['auth', 'paid'])->name('app.')->group(function () {
    Route::get('/app', [PublicPageController::class, 'app'])->name('workspace');
    Route::get('/search', [PublicPageController::class, 'search'])->name('search');
});

// Billing
Route::middleware('auth')->name('billing.')->prefix('billing')->group(function () {
    Route::get('/', [BillingController::class, 'show'])->name('show');
    Route::post('/checkout', [BillingController::class, 'checkout'])->name('checkout');
    Route::get('/success', [BillingController::class, 'success'])->name('success');
});

// Auth
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'loginForm'])->name('login');
    Route::get('/register', [AuthController::class, 'registerForm'])->name('register');
    Route::get('/forgot-password', [AuthController::class, 'passwordForm'])->name('password.form');
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');
