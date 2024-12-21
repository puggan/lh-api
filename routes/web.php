<?php

use App\Models\WpUser;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return new \Illuminate\Http\Response('Serer working, please select an valid endpoint', 404);
});

Route::get('/who-am-i', \App\Http\Controllers\UserController::whoAmI(...));
