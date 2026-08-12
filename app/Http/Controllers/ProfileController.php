<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use App\Support\Auth\LoginDestination;
use App\Support\CustomerPortal\CustomerPortalProfilePresenter;
use App\Support\Geo\CountryList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(
        protected CustomerPortalProfilePresenter $customerProfilePresenter,
    ) {}

    public function edit(Request $request): View|JsonResponse
    {
        $user = $request->user();

        if ($request->wantsJson() || $request->query('format') === 'json') {
            $user->loadMissing('profile');

            if ($user->isCustomer()) {
                return response()->json($this->customerProfilePresenter->present($user));
            }

            return response()->json([
                'ok' => true,
                'profile' => $this->dashboardProfilePayload($user),
            ]);
        }

        $userProfile = $user->profile()->firstOrCreate([]);

        $viewData = [
            'user' => $user,
            'userProfile' => $userProfile,
            'dashboardUrl' => $this->dashboardUrlFor($user),
            'countries' => CountryList::forSelect(),
        ];

        $view = match (true) {
            $user->isCustomer() => client_view_exists('profile.edit', 'customer')
                ? client_view('profile.edit', 'customer')
                : 'profile.edit-frontend',
            $user->isAgentPortalUser() => client_view_exists('profile.edit', 'agent')
                ? client_view('profile.edit', 'agent')
                : 'profile.edit-agent',
            default => 'profile.edit-dashboard',
        };

        return view($view, $viewData);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse|JsonResponse
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

        if ($request->wantsJson() || $request->query('format') === 'json') {
            $fresh = $user->fresh(['profile']);

            if ($fresh->isCustomer()) {
                return response()->json([
                    'ok' => true,
                    'profile' => $this->customerProfilePresenter->present($fresh),
                    'message' => 'Profile updated successfully.',
                ]);
            }

            return response()->json([
                'ok' => true,
                'profile' => $this->dashboardProfilePayload($fresh),
                'message' => 'Profile updated successfully.',
            ]);
        }

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

    /**
     * @return array<string, mixed>
     */
    protected function dashboardProfilePayload(User $user): array
    {
        $user->loadMissing('profile');

        return [
            'name' => $user->name,
            'email' => $user->email,
            'username' => $user->username,
            'phone' => $user->profile?->phone,
            'city' => $user->profile?->city,
            'country_code' => $user->profile?->country_code,
            'whatsapp' => $user->profile?->whatsapp,
        ];
    }

    protected function dashboardUrlFor(User $user): string
    {
        return LoginDestination::path($user);
    }
}
