<?php

namespace App\Http\Controllers;

use App\Http\Middleware\RequireWpAdmin;
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
                // may use the admin endpoints (see RequireWpAdmin)
                'admin' => $user->hasCapability(RequireWpAdmin::CAPABILITY),
            ] : [
                'loggedIn' => false,
            ]
        );
    }
}
