<?php

namespace App\Services\Commerce;

use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\CommerceCheckoutSetting;
use App\Models\User;
use App\Services\Platform\PlatformModuleSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

/**
 * Platform/agency commerce checkout gates (guest booking, card payment, customer group booking).
 */
final class CommerceCheckoutSettingsService
{
    /**
     * @return array{
     *   guest_booking_enabled: bool,
     *   card_payment_enabled: bool,
     *   customer_group_booking_enabled: bool,
     *   customer_registration_enabled: bool
     * }
     */
    public function gates(?int $agencyId = null): array
    {
        $settings = $this->get($agencyId);

        return [
            'guest_booking_enabled' => (bool) $settings->guest_booking_enabled,
            'card_payment_enabled' => (bool) $settings->card_payment_enabled,
            'customer_group_booking_enabled' => $this->readCustomerGroupBookingEnabled($settings),
            'customer_registration_enabled' => $this->isCustomerRegistrationEnabled(),
        ];
    }

    public function get(?int $agencyId = null): CommerceCheckoutSetting
    {
        if (! Schema::hasTable('commerce_checkout_settings')) {
            return new CommerceCheckoutSetting([
                'agency_id' => $agencyId,
                'guest_booking_enabled' => true,
                'card_payment_enabled' => true,
                'customer_group_booking_enabled' => true,
            ]);
        }

        if ($agencyId !== null) {
            $agencySetting = CommerceCheckoutSetting::query()
                ->where('agency_id', $agencyId)
                ->first();
            if ($agencySetting !== null) {
                return $agencySetting;
            }
        }

        return CommerceCheckoutSetting::query()->firstOrCreate(
            ['agency_id' => null],
            [
                'guest_booking_enabled' => true,
                'card_payment_enabled' => true,
                'customer_group_booking_enabled' => true,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(User $actor, array $payload, ?Agency $agency = null): CommerceCheckoutSetting
    {
        $agencyId = $agency?->id;
        $setting = $agencyId !== null
            ? CommerceCheckoutSetting::query()->firstOrCreate(
                ['agency_id' => $agencyId],
                [
                    'guest_booking_enabled' => true,
                    'card_payment_enabled' => true,
                    'customer_group_booking_enabled' => true,
                ],
            )
            : $this->get();

        $oldValues = [
            'guest_booking_enabled' => (bool) $setting->guest_booking_enabled,
            'card_payment_enabled' => (bool) $setting->card_payment_enabled,
            'customer_group_booking_enabled' => $this->readCustomerGroupBookingEnabled($setting),
        ];

        $changes = [];
        if (array_key_exists('guest_booking_enabled', $payload)) {
            $changes['guest_booking_enabled'] = (bool) $payload['guest_booking_enabled'];
        }
        if (array_key_exists('card_payment_enabled', $payload)) {
            $changes['card_payment_enabled'] = (bool) $payload['card_payment_enabled'];
        }
        if (
            array_key_exists('customer_group_booking_enabled', $payload)
            && Schema::hasColumn('commerce_checkout_settings', 'customer_group_booking_enabled')
        ) {
            $changes['customer_group_booking_enabled'] = (bool) $payload['customer_group_booking_enabled'];
        }

        if ($changes !== []) {
            $setting->fill($changes);
            $setting->save();
        }

        $newValues = [
            'guest_booking_enabled' => (bool) $setting->guest_booking_enabled,
            'card_payment_enabled' => (bool) $setting->card_payment_enabled,
            'customer_group_booking_enabled' => $this->readCustomerGroupBookingEnabled($setting),
        ];

        foreach ($changes as $field => $newValue) {
            $oldValue = $oldValues[$field] ?? null;
            if ($oldValue === $newValue) {
                continue;
            }

            AuditLog::query()->create([
                'agency_id' => $agencyId,
                'user_id' => $actor->id,
                'action' => 'commerce_checkout.settings_updated',
                'auditable_type' => CommerceCheckoutSetting::class,
                'auditable_id' => $setting->id,
                'properties' => [
                    'setting' => $field,
                    'old_value' => $oldValue,
                    'new_value' => $newValue,
                    'old_values' => $oldValues,
                    'new_values' => $newValues,
                ],
            ]);
        }

        return $setting->fresh() ?? $setting;
    }

    public function isGuestBookingEnabled(?int $agencyId = null): bool
    {
        return (bool) $this->get($this->resolveAgencyId($agencyId))->guest_booking_enabled;
    }

    public function isCardPaymentEnabled(?int $agencyId = null): bool
    {
        return (bool) $this->get($this->resolveAgencyId($agencyId))->card_payment_enabled;
    }

    public function isCustomerGroupBookingEnabled(?int $agencyId = null): bool
    {
        return $this->readCustomerGroupBookingEnabled($this->get($this->resolveAgencyId($agencyId)));
    }

    public function isCustomerRegistrationEnabled(): bool
    {
        try {
            return app(PlatformModuleSettingsService::class)->stateFor('customer_registration');
        } catch (\Throwable) {
            return true;
        }
    }

    public function guestBookingGateResponse(Request $request, ?int $agencyId = null): RedirectResponse|JsonResponse|null
    {
        if (Auth::check() || $this->isGuestBookingEnabled($agencyId)) {
            return null;
        }

        $returnPath = $request->getRequestUri();
        if (! is_string($returnPath) || $returnPath === '') {
            $returnPath = '/booking/passengers';
        }

        $accountUrl = '/booking/account?redirect='.rawurlencode($returnPath);
        $loginUrl = '/login?redirect='.rawurlencode($returnPath);
        $registerUrl = $this->isCustomerRegistrationEnabled()
            ? '/register?redirect='.rawurlencode($returnPath)
            : null;

        if ($request->wantsJson() || $request->query('format') === 'json') {
            return response()->json([
                'ok' => false,
                'status' => 'guest_booking_disabled',
                'code' => 'AUTH_REQUIRED',
                'message' => 'Please sign in'.($registerUrl ? ' or register' : '').' to continue booking.',
                'redirect_url' => $accountUrl,
                'login_url' => $loginUrl,
                'register_url' => $registerUrl,
                'customer_registration_enabled' => $registerUrl !== null,
                'continue_as_guest' => false,
            ], 401);
        }

        return redirect()->guest($accountUrl);
    }

    private function readCustomerGroupBookingEnabled(CommerceCheckoutSetting $settings): bool
    {
        if (! Schema::hasTable('commerce_checkout_settings')) {
            return true;
        }

        if (! Schema::hasColumn('commerce_checkout_settings', 'customer_group_booking_enabled')) {
            return true;
        }

        return (bool) ($settings->customer_group_booking_enabled ?? true);
    }

    private function resolveAgencyId(?int $agencyId): ?int
    {
        if ($agencyId !== null) {
            return $agencyId;
        }

        return Agency::query()
            ->where('slug', (string) config('ota.default_agency_slug', 'asif-travels'))
            ->value('id');
    }
}
