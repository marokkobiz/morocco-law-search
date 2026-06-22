<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\ComingSoonController;
use App\Http\Controllers\CorpusStatusController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

// Public Pages
Route::get('/', ComingSoonController::class)->name('landing');
// Route::get('/test', LandingController::class)->name('landing');
Route::get('/corpus/status', [CorpusStatusController::class, 'show'])->name('corpus.status');

// Locale switcher
Route::get('/locale/{locale}', function ($locale) {
    if (in_array($locale, ['en', 'fr', 'ar'])) {
        session(['locale' => $locale]);
    }
    return redirect()->back();
})->name('locale.switch');

// Protected App Pages
Route::middleware(['auth', 'paid'])->name('app.')->group(function () {
    Route::get('/dashboard', WorkspaceController::class)->name('workspace');
    Route::get('/search', WorkspaceController::class)->name('search');
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
