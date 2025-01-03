<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return new \Illuminate\Http\Response('Serer working, pelase select an valid endpoint', 404);
});
