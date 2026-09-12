<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Mail\ForgotPasswordMail;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ForgotPasswordController extends Controller
{
    public function showForgotForm(): View
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(ForgotPasswordRequest $request): RedirectResponse
    {
        $user = User::where('email', $request->email)->firstOrFail();

        $plainToken = Str::random(64);

        $user->update([
            'password_setup_token' => hash('sha256', $plainToken),
            'password_setup_expires_at' => now()->addMinutes(15),
        ]);

        Mail::to($user->email)
            ->queue(new ForgotPasswordMail($user, $plainToken));

        return back()->with('success', 'Password reset link has been sent to your email.');
    }

    public function showResetForm(Request $request): View|RedirectResponse
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
                ->with('error', 'Password reset link is invalid or expired.');
        }

        return view('auth.reset-password', [
            'email' => $request->email,
            'token' => $request->token,
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request): RedirectResponse
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
                ->with('error', 'Password reset link is invalid or expired.');
        }

        $user->update([
            'password' => Hash::make($request->password),
            'password_setup_token' => null,
            'password_setup_expires_at' => null,
            'password_changed_at' => now(),
        ]);

        return redirect()
            ->route('login.view')
            ->with('success', 'Password reset successfully. You can now login.');
    }
}
