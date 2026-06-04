<?php

use App\Http\Controllers\CorpusStatusController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('landing');
});

Route::get('/app', function () {
    return view('react-law-search');
});

Route::get('/login', function () {
    return view('auth-page', ['mode' => 'login']);
});

Route::get('/register', function () {
    return view('auth-page', ['mode' => 'register']);
});

Route::get('/laravel-search', function () {
    return view('law-search');
});

Route::get('/corpus/status', [CorpusStatusController::class, 'show']);
