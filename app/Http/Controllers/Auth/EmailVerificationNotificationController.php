<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Client\ClientRedirectResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class EmailVerificationNotificationController extends Controller
{
    /**
     * Send a new email verification notification.
     */
    public function store(Request $request, ClientRedirectResolver $clientRedirectResolver): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return $clientRedirectResolver->intended(user: $user);
        }

        try {
            $user->sendEmailVerificationNotification();
        } catch (Throwable $e) {
            report($e);

            return back()
                ->withErrors(['email' => 'We could not send the verification email right now. Please try again in a few minutes.']);
        }

        return back()
            ->with('status', 'verification-link-sent')
            ->with('verification_notice', 'A new verification link has been sent. It expires in 24 hours.');
    }
}
