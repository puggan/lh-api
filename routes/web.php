<?php

use App\Http\Controllers\BookingController;
use App\Http\Controllers\CalenderController;
use App\Http\Controllers\CalendarFeedController;
use App\Http\Controllers\PeriodsController;
use App\Http\Controllers\PlacesController;
use App\Http\Controllers\TermsController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\RequireWpAdmin;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return new \Illuminate\Http\Response('Serer working, please select an valid endpoint', 404);
});

// Public: the booking calendar and its form.
Route::get('/who-am-i', UserController::whoAmI(...));
Route::get('/places', PlacesController::places(...));
Route::get('/calender', CalenderController::calender(...));
Route::get('/calender/{place}/public.ics', CalendarFeedController::publicFeed(...))->whereNumber('place');
// No login (calendar apps can't log in): the secret token in the URL is the protection.
Route::get('/calender/{place}/admin/{token}.ics', CalendarFeedController::adminFeed(...))
    ->whereNumber('place')->where('token', '[0-9a-f]+');
Route::post('/calender/book', BookingController::book(...));
Route::get('/terms', TermsController::terms(...));
Route::get('/terms/last', TermsController::last(...));

// Admin: WordPress editors and administrators (see RequireWpAdmin).
Route::middleware(RequireWpAdmin::class)->group(function () {
    Route::post('/periods', PeriodsController::create(...));
    Route::delete('/periods/{id}', PeriodsController::delete(...))->whereNumber('id');

    Route::post('/terms', TermsController::create(...));

    Route::get('/calender/feeds', CalendarFeedController::feeds(...));

    Route::get('/request/pending', BookingController::pending(...));
    Route::post('/request/approve', BookingController::approve(...));
    Route::post('/request/decline', BookingController::declined(...));
    Route::post('/request/spam', BookingController::spam(...));
    Route::post('/request/cleanup', BookingController::cleanup(...));
});
