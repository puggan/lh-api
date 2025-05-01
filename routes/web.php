<?php

use App\Http\Controllers\BookingController;
use App\Http\Controllers\CalenderController;
use App\Http\Controllers\PlacesController;
use App\Http\Controllers\TermsController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return new \Illuminate\Http\Response('Serer working, please select an valid endpoint', 404);
});

Route::get('/who-am-i', UserController::whoAmI(...));

Route::get('/places', PlacesController::places(...));

Route::get('/calender', CalenderController::calender(...));

Route::post('/calender/book', BookingController::book(...));

Route::get('/terms', TermsController::terms(...));
Route::get('/terms/last', TermsController::last(...));

Route::get('/request/pending', BookingController::pending(...));
