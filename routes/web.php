<?php

use App\Http\Controllers\AnimalLookupController;
use App\Http\Controllers\ApiDocsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/animals/lookup/{code}', AnimalLookupController::class)
    ->middleware(['auth', 'throttle:60,1'])
    ->where('code', '.*')
    ->name('animals.lookup');

Route::get('/docs', ApiDocsController::class)->name('api.docs');
