<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use App\Support\Auth\LoginDestination;
use App\Support\Geo\CountryList;
use App\Support\Ui\MobileViewPreference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(
        protected MobileViewPreference $mobileViewPreference,
    ) {}

    public function edit(Request $request): View
    {
        $user = $request->user();
        $userProfile = $user->profile()->firstOrCreate([]);

        $viewData = [
            'user' => $user,
            'userProfile' => $userProfile,
            'dashboardUrl' => $this->dashboardUrlFor($user),
            'countries' => CountryList::forSelect(),
        ];

        if ($user->isCustomer() && $this->mobileViewPreference->shouldUseMobileShell($request, 'profile.edit-frontend')) {
            return view('mobile.customer.profile.edit', $viewData);
        }

        $view = match (true) {
            $user->isCustomer() => 'profile.edit-frontend',
            $user->isAgentPortalUser() => 'profile.edit-agent',
            default => 'profile.edit-dashboard',
        };

        return view($view, $viewData);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'username' => $validated['username'],
        ]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $profile = $user->profile()->firstOrNew([]);
        $profile->fill(collect($validated)->only([
            'phone',
            'whatsapp',
            'country_code',
            'city',
            'date_of_birth',
            'gender',
            'nationality',
            'passport_number',
            'passport_issuing_country',
            'passport_expiry_date',
            'national_id',
            'emergency_contact_name',
            'emergency_contact_phone',
        ])->all());

        if ($request->boolean('remove_profile_photo') && filled($profile->profile_photo_path)) {
            $this->deleteProfilePhoto($profile->profile_photo_path);
            $profile->profile_photo_path = null;
        }

        if ($request->hasFile('profile_photo')) {
            if (filled($profile->profile_photo_path)) {
                $this->deleteProfilePhoto($profile->profile_photo_path);
            }
            $profile->profile_photo_path = $request->file('profile_photo')->store('profile-photos', 'public');
        }

        $profile->user_id = $user->id;
        $profile->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();
        $user->loadMissing('profile');

        if (filled($user->profile?->profile_photo_path)) {
            $this->deleteProfilePhoto($user->profile->profile_photo_path);
        }

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    protected function deleteProfilePhoto(string $path): void
    {
        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    protected function dashboardUrlFor(User $user): string
    {
        return LoginDestination::path($user);
    }
}
