<?php

namespace App\Http\Controllers;

use App\Models\WpUser;
use Illuminate\Http\JsonResponse;

class UserController
{
    public static function whoAmI(): JsonResponse
    {
        $user = WpUser::loggedIn();
        return new JsonResponse(
            $user ? [
                'loggedIn' => true,
                'who' => $user->user_login,
            ] : [
                'loggedIn' => false,
            ]
        );
    }
}
