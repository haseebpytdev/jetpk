<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AccountType;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Client\ClientRedirectResolver;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VerifyEmailController extends Controller
{
    /**
     * Mark the signed route target user's email address as verified.
     */
    public function __invoke(Request $request, ClientRedirectResolver $clientRedirectResolver): RedirectResponse
    {
        $user = User::query()->findOrFail($request->route('id'));
        $hash = (string) $request->route('hash');

        if (! hash_equals($hash, sha1($user->getEmailForVerification()))) {
            abort(403);
        }

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        if ($user->account_type === AccountType::Customer) {
            Auth::login($user);
            $request->session()->regenerate();

            return redirect()->to($clientRedirectResolver->pathForRoute('dashboard').'?verified=1');
        }

        return $clientRedirectResolver
            ->route('login')
            ->with('status', 'Email verified.');
    }
}
