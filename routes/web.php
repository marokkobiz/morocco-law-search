<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CorpusStatusController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\LegalAidController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public Pages
Route::get('/', LandingController::class)->name('landing');
// Route::get('/test', LandingController::class)->name('landing');
// Route::get('/corpus/status', [CorpusStatusController::class, 'show'])->name('corpus.status');

Route::get('/test/beta/legal-aid', [LegalAidController::class, 'index'])->name('legal-aid');

// Locale switcher
Route::get('/locale/{locale}', function ($locale) {
    if (in_array($locale, ['en', 'fr', 'ar'])) {
        session(['locale' => $locale]);
    }

    return redirect()->back();
})->name('locale.switch');

// Protected App Pages
Route::middleware(['auth'])->name('app.')->group(function () {
    Route::get('/dashboard', WorkspaceController::class)->name('workspace');
    Route::get('/search', WorkspaceController::class)->name('search');
});

Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {

    // Admin Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Admin Users List
    Route::get('/users', [UserController::class, 'index'])->name('users.index');

    // Toggle User Agent Status
    Route::post('/users/{user}/toggle-agent', [UserController::class, 'toggleAgent'])->name('users.toggle-agent');

});

Route::middleware('auth')->group(function () {

    Route::get('/email/verify', function () {
        return view('auth.verify-email');
    })->name('verification.notice');

    Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
        $request->fulfill();

        return redirect()->route('app.workspace');
    })->middleware('signed')->name('verification.verify');

    Route::post('/email/verification-notification', function (Request $request) {
        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'Verification link sent!');
    })->middleware('throttle:6,1')->name('verification.send');
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
