<?php

namespace App\Http\Controllers;

use App\Models\Places;
use Illuminate\Http\JsonResponse;

class PlacesController
{
    public static function places(): JsonResponse
    {
        return new JsonResponse(Places::all());
    }
}
