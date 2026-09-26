<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A token stays valid until it expires, so re-check the account on every
 * API call: a user deactivated (or put back on a temporary password) after
 * logging in on a device is refused immediately.
 */
class EnsureApiUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isInactive()) {
            $user->currentAccessToken()?->delete();

            return response()->json(['success' => false, 'message' => 'Your account is disabled.'], 403);
        }

        if ($user && is_null($user->password_changed_at) && ! is_null($user->password_setup_token)) {
            return response()->json(['success' => false, 'message' => 'You must change your temporary password on the web before using the app.'], 403);
        }

        return $next($request);
    }
}
