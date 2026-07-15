<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CompleteGoogleProfileRequest;
use App\Mail\GoogleCustomerWelcomeMail;
use App\Models\User;
use App\Support\Auth\GoogleOnboarding;
use App\Support\Auth\LoginDestination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Throwable;

class GoogleOnboardingController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if ($user === null || ! $user->isCustomer()) {
            abort(403);
        }

        if (! GoogleOnboarding::sessionRequiresCompletion($request)) {
            if (GoogleOnboarding::customerProfileIsComplete($user)) {
                return redirect()->to($this->postOnboardingDestination($request, $user));
            }

            abort(403);
        }

        if (GoogleOnboarding::customerProfileIsComplete($user)) {
            GoogleOnboarding::clearSession($request);

            return redirect()->to($this->postOnboardingDestination($request, $user));
        }

        $oauthName = $user->socialAccounts()
            ->where('provider', 'google')
            ->value('provider_name');

        return view('auth.google-complete-profile', [
            'defaults' => GoogleOnboarding::formDefaults($user, is_string($oauthName) ? $oauthName : null),
            'countryCodes' => $this->countryCodes(),
        ]);
    }

    public function store(CompleteGoogleProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        if ($user === null || ! $user->isCustomer()) {
            abort(403);
        }

        if (! GoogleOnboarding::sessionRequiresCompletion($request)) {
            abort(403);
        }

        GoogleOnboarding::persistCompletedProfile($user, $request->profilePayload());

        $sendWelcome = GoogleOnboarding::sessionIsNewCustomer($request);
        GoogleOnboarding::clearSession($request);

        if ($sendWelcome) {
            $this->queueWelcomeEmail($user);
        }

        return redirect()
            ->to($this->postOnboardingDestination($request, $user))
            ->with('status', 'google-profile-complete');
    }

    private function postOnboardingDestination(Request $request, User $user): string
    {
        $intended = $request->session()->pull('url.intended');
        if (is_string($intended) && $intended !== '') {
            return $intended;
        }

        return LoginDestination::path($user);
    }

    private function queueWelcomeEmail(User $user): void
    {
        try {
            Mail::to($user->email)->queue(GoogleCustomerWelcomeMail::forUser($user));
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * @return array<string, string>
     */
    private function countryCodes(): array
    {
        return [
            '+92' => 'Pakistan (+92)',
            '+61' => 'Australia (+61)',
            '+971' => 'UAE (+971)',
            '+966' => 'Saudi Arabia (+966)',
            '+44' => 'United Kingdom (+44)',
            '+1' => 'United States / Canada (+1)',
            '+974' => 'Qatar (+974)',
            '+965' => 'Kuwait (+965)',
            '+968' => 'Oman (+968)',
            '+973' => 'Bahrain (+973)',
        ];
    }
}
