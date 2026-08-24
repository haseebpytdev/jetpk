<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\RespondsWithBackOfficeJson;
use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\CommerceCheckoutSetting;
use App\Services\Commerce\CommerceCheckoutSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CommerceCheckoutSettingsController extends Controller
{
    use RespondsWithBackOfficeJson;

    public function __construct(
        private readonly CommerceCheckoutSettingsService $settingsService,
    ) {}

    public function show(Request $request): JsonResponse|RedirectResponse
    {
        Gate::authorize('platform.admin');

        $agency = $this->resolveAgency($request);
        $settings = $this->settingsService->get($agency->id);
        Gate::authorize('view', $settings);

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'settings' => $this->presentSettings($settings),
            ]);
        }

        return redirect()->to('/admin/dashboard/settings/booking-checkout');
    }

    public function update(Request $request): JsonResponse|RedirectResponse
    {
        Gate::authorize('platform.admin');

        $agency = $this->resolveAgency($request);
        $settings = $this->settingsService->get($agency->id);
        Gate::authorize('update', $settings);

        $validated = $request->validate([
            'guest_booking_enabled' => ['nullable', 'boolean'],
            'card_payment_enabled' => ['nullable', 'boolean'],
        ]);

        $payload = [];
        if ($request->has('guest_booking_enabled')) {
            $payload['guest_booking_enabled'] = $request->boolean('guest_booking_enabled');
        }
        if ($request->has('card_payment_enabled')) {
            $payload['card_payment_enabled'] = $request->boolean('card_payment_enabled');
        }

        $settings = $this->settingsService->update($request->user(), $payload, $agency);

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'message' => 'Booking & checkout settings updated.',
                'settings' => $this->presentSettings($settings),
            ]);
        }

        return back()->with('status', 'commerce-checkout-settings-updated');
    }

    /**
     * @return array<string, mixed>
     */
    private function presentSettings(CommerceCheckoutSetting $settings): array
    {
        return [
            'guest_booking_enabled' => (bool) $settings->guest_booking_enabled,
            'card_payment_enabled' => (bool) $settings->card_payment_enabled,
            'updated_at' => $settings->updated_at?->toIso8601String(),
        ];
    }

    private function resolveAgency(Request $request): Agency
    {
        $user = $request->user();
        if ($user->isPlatformAdmin() && $request->filled('agency_id')) {
            return Agency::query()->findOrFail($request->integer('agency_id'));
        }

        return Agency::query()->findOrFail($user->current_agency_id);
    }
}
