<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('coming-soon');
});


Route::get('/test', function () {
    return view('home');
});
