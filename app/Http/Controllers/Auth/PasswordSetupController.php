<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\PasswordSetupUpdateRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class PasswordSetupController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
        ]);

        $user = User::where('email', $request->email)
            ->where('password_setup_token', hash('sha256', $request->token))
            ->first();

        if (
            ! $user ||
            ! $user->password_setup_expires_at ||
            $user->password_setup_expires_at->isPast()
        ) {
            return redirect()
                ->route('login.view')
                ->with('error', 'Password setup link is invalid or expired.');
        }

        return view('auth.password-setup', [
            'email' => $request->email,
            'token' => $request->token,
        ]);
    }

    public function update(PasswordSetupUpdateRequest $request): RedirectResponse
    {
        $user = User::where('email', $request->email)
            ->where('password_setup_token', hash('sha256', $request->token))
            ->first();

        if (
            ! $user ||
            ! $user->password_setup_expires_at ||
            $user->password_setup_expires_at->isPast()
        ) {
            return redirect()
                ->route('login.view')
                ->with('error', 'Password setup link is invalid or expired.');
        }

        if (! Hash::check($request->temporary_password, $user->password)) {
            return back()
                ->withInput()
                ->withErrors([
                    'temporary_password' => 'Temporary password does not match.',
                ]);
        }

        $user->update([
            'password' => Hash::make($request->password),
            'password_setup_token' => null,
            'password_setup_expires_at' => null,
            'password_changed_at' => now(),
        ]);

        return redirect()
            ->route('login.view')
            ->with('success', 'Password changed successfully. You can now login.');
    }
}
