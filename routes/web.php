<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\LegalAidController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/', [LandingController::class, 'index'])->name('landing');
Route::get('/test/beta/legal-aid', [LegalAidController::class, 'index'])->name('legal-aid');
Route::post('/test/beta/legal-aid', [LegalAidController::class, 'store'])->name('legal-aid.store');
Route::get('/legal-aid/payment/{ticket}', [LegalAidController::class, 'payment'])->name('legal-aid.payment');
Route::post('/legal-aid/payment/{ticket}/receipt', [LegalAidController::class, 'uploadReceipt'])->name('legal-aid.receipt');

Route::get('/locale/{locale}', function ($locale) {
    if (in_array($locale, ['en', 'fr', 'ar'])) {
        session(['locale' => $locale]);
    }

    return redirect()->back();
})->name('locale.switch');

// Guest routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'loginForm'])->name('login');
    Route::get('/register', [AuthController::class, 'registerForm'])->name('register');
    Route::get('/forgot-password', [AuthController::class, 'passwordForm'])->name('password.form');
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
});

// Authenticated routes
Route::middleware(['auth'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Workspace
    Route::prefix('app')->name('app.')->group(function () {
        Route::get('/dashboard', [WorkspaceController::class, 'index'])->name('workspace');
        Route::get('/search', [WorkspaceController::class, 'index'])->name('search');
        Route::get('/laws/{document}', [WorkspaceController::class, 'show'])->name('law.show');
    });
});

// Admin routes (Secured with auth and admin middleware)
Route::middleware(['auth', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users/{user}/toggle-admin', [UserController::class, 'toggleAdmin'])->name('users.toggle-admin');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        Route::get('/services', [ServiceController::class, 'index'])->name('services.index');
        Route::get('/services/create', [ServiceController::class, 'create'])->name('services.create');
        Route::post('/services', [ServiceController::class, 'store'])->name('services.store');
        Route::get('/services/{service}/edit', [ServiceController::class, 'edit'])->name('services.edit');
        Route::put('/services/{service}', [ServiceController::class, 'update'])->name('services.update');
        Route::delete('/services/{service}', [ServiceController::class, 'destroy'])->name('services.destroy');
        Route::get('/legal-aid', [LegalAidController::class, 'adminIndex'])->name('legal-aid.index');
        Route::get('/legal-aid/{legalAidRequest}', [LegalAidController::class, 'show'])->name('legal-aid.show');
        Route::post('/legal-aid/{legalAidRequest}/confirm', [LegalAidController::class, 'confirm'])->name('legal-aid.confirm');
        Route::post('/legal-aid/{legalAidRequest}/resend', [LegalAidController::class, 'resendPaymentLink'])->name('legal-aid.resend');
        Route::post('/legal-aid/{legalAidRequest}/reject', [LegalAidController::class, 'reject'])->name('legal-aid.reject');
    });
