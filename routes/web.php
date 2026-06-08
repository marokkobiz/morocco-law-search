<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('coming-soon');
});

Route::get('/test', function () {
    return view('landing');
});

Route::get('/app', function () {
    return view('react-law-search');
})->middleware('auth');

Route::get('/login', [AuthController::class, 'loginForm'])->name('login');
Route::get('/register', [AuthController::class, 'registerForm'])->name('register');

Route::middleware('guest')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth');

Route::get('/laravel-search', function () {
    return view('law-search');
})->middleware('auth');
