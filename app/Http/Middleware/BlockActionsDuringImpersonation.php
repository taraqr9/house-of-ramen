<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockActionsDuringImpersonation
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app('impersonate')->isImpersonating()) {
            /*
             * Block all write/change actions while impersonating.
             */
            if (! $request->isMethod('GET')) {
                return redirect()
                    ->back()
                    ->with('error', 'Action is disabled while impersonating. You can only view data.');
            }
        }

        return $next($request);
    }
}
