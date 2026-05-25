<?php

use Illuminate\Support\Facades\Route;

// Catch-all route to serve the React SPA
// It will match any route except those starting with "api/"
Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');
