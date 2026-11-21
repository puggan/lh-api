<?php

namespace App\Http\Controllers;

use App\Models\Terms;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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

    /**
     * Admin: publishes new terms. Terms are never edited, because each booking points at the
     * version the booker accepted; the newest version is the one new bookings must accept.
     */
    public static function create(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'content' => ['required', 'string', 'max:65535'],
        ]);
        if ($validator->fails()) {
            return new JsonResponse(['ok' => false, 'errors' => $validator->errors()], 422);
        }

        $terms = new Terms();
        $terms->content = $request->input('content');
        $terms->save();

        return new JsonResponse($terms->refresh(), 201);
    }
}
