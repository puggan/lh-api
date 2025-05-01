<?php

namespace App\Http\Controllers;

use App\Models\Places;
use App\Models\Terms;
use Illuminate\Http\JsonResponse;

class TermsController
{

    public static function terms(): JsonResponse
    {
        return new JsonResponse(Terms::all());
    }

    public static function last()
    {
        return new JsonResponse(Terms::query()->orderBy('id', 'desc')->first());
    }
}
