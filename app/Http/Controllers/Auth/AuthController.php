<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthController extends Controller
{
    /**
     * Failed attempts allowed (per username+IP) before login is throttled,
     * and how many seconds the block lasts once triggered - Laravel's own
     * documented example values for this exact pattern, a sensible
     * default for a normal site (not so tight that a legitimate user
     * mistyping their password twice gets blocked).
     */
    protected const MAX_LOGIN_ATTEMPTS = 5;

    protected const LOGIN_DECAY_SECONDS = 60;

    public function loginView(): View
    {
        $page_title = 'Login';

        return view('auth.login', compact('page_title'));
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $throttleKey = $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_LOGIN_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()
                ->withInput($request->only('username', 'remember'))
                ->withErrors([
                    'username' => "Too many login attempts. Please try again in {$seconds} seconds.",
                ]);
        }

        $credentials = [
            'username' => $request->username,
            'password' => $request->password,
        ];

        $remember = $request->boolean('remember');

        if (Auth::attempt($credentials, $remember)) {
            RateLimiter::clear($throttleKey);

            $request->session()->regenerate();

            $user = Auth::user();

            if (is_null($user->password_changed_at) && ! is_null($user->password_setup_token)) {
                return redirect()
                    ->route('password.change.show')
                    ->with('error', 'You must change your temporary password before continuing.');
            }

            return redirect()
                ->route('dashboard')
                ->with('success', 'Login successful.');
        }

        RateLimiter::hit($throttleKey, self::LOGIN_DECAY_SECONDS);

        return back()
            ->withInput($request->only('username', 'remember'))
            ->withErrors([
                'username' => 'Invalid username or password.',
            ]);
    }

    /**
     * Keyed by username+IP (not IP alone) - one attacker guessing many
     * usernames from one IP still gets throttled per-username, but a
     * shared office/NAT IP with several real users never locks all of
     * them out over one person's typos.
     */
    protected function throttleKey(LoginRequest $request): string
    {
        return Str::transliterate(Str::lower((string) $request->input('username')).'|'.$request->ip());
    }

    public function logout(): RedirectResponse
    {
        Auth::logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->with('success', 'Logged out successfully.');
    }

    public function forgotPasswordView(): View
    {
        $page_title = 'Forgot Password';

        return view('auth.forgot-password', compact('page_title'));
    }
}
