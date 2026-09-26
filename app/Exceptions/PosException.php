<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * A POS business-rule violation ("order already completed", "payment
 * exceeds amount due"...). Thrown by App\Services\Pos\* so web and API
 * callers share the same rules. A 422 HttpException, so bootstrap/app.php
 * treats it as a client error (not written to the custom_error log) and it
 * renders itself: JSON for AJAX/API, a flash error + redirect back for forms.
 */
class PosException extends HttpException
{
    public function __construct(string $message)
    {
        parent::__construct(422, $message);
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $this->getMessage()], 422);
        }

        return redirect()->back()->withInput()->with('error', $this->getMessage());
    }
}
