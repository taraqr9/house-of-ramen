<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\PasswordChangeUpdateRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class PasswordChangeController extends Controller
{
    public function show(): View|RedirectResponse
    {
        $user = Auth::user();

        if (! is_null($user->password_changed_at)) {
            return redirect()->route('dashboard');
        }

        return view('auth.password-change-required');
    }

    public function update(PasswordChangeUpdateRequest $request): RedirectResponse
    {
        $user = Auth::user();

        if (! is_null($user->password_changed_at)) {
            return redirect()->route('dashboard');
        }

        if (! Hash::check($request->current_password, $user->password)) {
            return back()
                ->withErrors([
                    'current_password' => 'Current password does not match.',
                ]);
        }

        $user->update([
            'password' => Hash::make($request->password),
            'password_setup_token' => null,
            'password_setup_expires_at' => null,
            'password_changed_at' => now(),
        ]);

        return redirect()
            ->route('dashboard')
            ->with('success', 'Password changed successfully.');
    }
}
