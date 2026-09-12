<?php

use App\Http\Middleware\BlockActionsDuringImpersonation;
use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Http\Middleware\HandleInertiaRequests;
use App\Support\AdminPaths;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Truly global (not just ->web()): a request that matches no route
        // at all (a bogus public URL) never runs route/group middleware,
        // only the global stack - and the exception handler's respond()
        // callback below renders an Inertia error page for exactly that
        // case, which needs the shared `siteMeta` prop (see
        // Layouts/PublicLayout.vue, Pages/Public/Error.vue) already
        // populated. Otherwise safe to run on every request: it only adds
        // shared Inertia props and asset-version checking, and the
        // Blade-only admin panel never calls Inertia::render(), so it is
        // unaffected either way.
        $middleware->append(HandleInertiaRequests::class);

        $middleware->alias([
            'block.impersonation.actions' => BlockActionsDuringImpersonation::class,
            'force.password.change' => EnsurePasswordIsChanged::class,

            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        $exceptions->report(function (Throwable $e) {

            /*
             * Do not write normal auth/validation/client errors into custom error log.
             */
            if ($e instanceof AuthenticationException) {
                return;
            }

            if ($e instanceof ValidationException) {
                return;
            }

            if ($e instanceof HttpExceptionInterface && $e->getStatusCode() < 500) {
                return;
            }

            Log::channel('custom_error')->error($e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),

                'url' => request()->fullUrl(),
                'method' => request()->method(),
                'ip' => request()->ip(),

                'user_id' => Auth::id(),
                'user_name' => Auth::user()->name ?? null,
                'user_email' => Auth::user()->email ?? null,

                'input' => request()->except([
                    'password',
                    'password_confirmation',
                    'current_password',
                    '_token',
                ]),

                'trace' => $e->getTraceAsString(),
            ]);
        });

        $exceptions->render(function (Throwable $e, $request) {

            /*
             * Logged out user should go to login page normally.
             */
            if ($e instanceof AuthenticationException) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => 'Unauthenticated.',
                    ], 401);
                }

                return redirect()->guest(route('login'));
            }

            /*
             * Validation error should work normally.
             */
            if ($e instanceof ValidationException) {
                return null;
            }

            /*
             * 404, 403, 419 etc. should work normally.
             */
            if ($e instanceof HttpExceptionInterface && $e->getStatusCode() < 500) {
                return null;
            }

            /*
             * Only real server errors should show toast + redirect back.
             */
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Something went wrong.',
                ], 500);
            }

            return redirect()
                ->back()
                ->withInput($request->except([
                    'password',
                    'password_confirmation',
                    'current_password',
                ]))
                ->with('error', 'Something went wrong. Please try again later.');
        });

        /*
         * The above render() callbacks handle the admin/auth side (falls
         * through to Laravel's default resources/views/errors/*.blade.php,
         * which is the purchased Bootstrap admin theme's error pages -
         * correct branding there). This runs after them, on the final
         * response: for an error on the *public* Phone Kinbo site
         * (everything outside AdminPaths - see routes/web.php's "public."
         * route group), swap in an on-brand Vue/Inertia error page instead
         * of a request that would otherwise inherit the admin theme's
         * "Phone Kinbo - Admin & Dashboard" 404/500 view. Scoped by path
         * rather than route name because a genuine 404 (no route matched
         * at all) never has a route to read a name from.
         */
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            $status = $response->getStatusCode();

            if (! in_array($status, [404, 403, 419, 500, 503], true)) {
                return $response;
            }

            if ($request->expectsJson() || AdminPaths::matches($request->path())) {
                return $response;
            }

            return Inertia::render('Public/Error', ['status' => $status])
                ->toResponse($request)
                ->setStatusCode($status);
        });

    })->create();
