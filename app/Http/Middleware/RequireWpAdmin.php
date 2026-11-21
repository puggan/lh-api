<?php

namespace App\Http\Middleware;

use App\Models\WpUser;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin endpoints: the request must come from a WordPress user who can edit pages
 * (editors and administrators, the same users who see the Bokningar page in wp-admin).
 *
 * Requests that change something must also be JSON: an HTML form on another site can't send JSON,
 * and a cross-site fetch() with JSON is stopped by the browser (no CORS headers here). That way
 * another site can't use an admin's WordPress cookie to approve or decline bookings.
 */
class RequireWpAdmin
{
    public const CAPABILITY = 'edit_pages';

    public function handle(Request $request, Closure $next): Response
    {
        $user = WpUser::loggedIn();
        if (!$user) {
            return new JsonResponse(['ok' => false, 'error' => 'Not logged in to WordPress'], 401);
        }
        if (!$user->hasCapability(self::CAPABILITY)) {
            return new JsonResponse(['ok' => false, 'error' => 'Not allowed'], 403);
        }
        if (!$request->isMethodSafe() && !$request->isJson()) {
            return new JsonResponse(['ok' => false, 'error' => 'Send the request as JSON'], 415);
        }
        return $next($request);
    }
}
