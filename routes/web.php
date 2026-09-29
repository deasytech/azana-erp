<?php

use App\Http\Controllers\AnimalLookupController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/animals/lookup/{code}', AnimalLookupController::class)
    ->middleware(['auth', 'throttle:60,1'])
    ->where('code', '.*')
    ->name('animals.lookup');
