<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\CorpusStatusController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('coming-soon');
});

Route::get('/test', function () {
    return view('landing');
});

Route::get('/app', function () {
    return view('react-law-search');
})->middleware(['auth', 'paid']);

Route::get('/login', [AuthController::class, 'loginForm'])->name('login');
Route::get('/register', [AuthController::class, 'registerForm'])->name('register');

Route::middleware('guest')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth');

Route::middleware('auth')->group(function (): void {
    Route::get('/billing', [BillingController::class, 'show']);
    Route::post('/billing/checkout', [BillingController::class, 'checkout']);
    Route::get('/billing/success', [BillingController::class, 'success']);
});

Route::get('/laravel-search', function () {
    return redirect('/app');
})->middleware(['auth', 'paid']);

Route::get('/react-search', function () {
    return view('react-law-search');
})->middleware(['auth', 'paid']);

Route::get('/corpus/status', [CorpusStatusController::class, 'show']);
