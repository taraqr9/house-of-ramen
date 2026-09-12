<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! is_null($user->password_changed_at) || is_null($user->password_setup_token)) {
            return $next($request);
        }

        if ($request->routeIs('logout', 'password.change.show', 'password.change.update')) {
            return $next($request);
        }

        return redirect()
            ->route('password.change.show')
            ->with('error', 'You must change your temporary password before continuing.');
    }
}
