<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** The API is for signed-in, active users who still hold the mobile permission, on a token issued for the mobile app. Checked on every request, so revoking the permission ends access at once. */
class EnsureMobileAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active || ! $user->can('mobile.view') || ! $user->tokenCan('mobile')) {
            return response()->json(['message' => 'You are not allowed to use the mobile app.', 'code' => 'mobile_forbidden'], 403);
        }

        return $next($request);
    }
}
