<?php

namespace App\Support\AgentPortal;

use App\Models\Agency;
use App\Models\AgencySetting;
use App\Models\Agent;
use App\Models\User;
use App\Services\Agencies\AgencyBrandingService;
use App\Support\Agencies\AgencyPrefixService;
use App\Support\Agents\AgentPermission;
use App\Support\Geo\CountryList;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * Agent personal and agency profile JSON for Next.js dashboard.
 */
class AgentPortalProfilePresenter
{
    public function __construct(
        protected AgencyBrandingService $brandingService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function present(User $user): array
    {
        $agent = $user->agent();
        abort_if($agent === null, 403);

        $agent->loadMissing(['agency', 'user']);
        $agency = $agent->agency;
        abort_if($agency === null, 404);

        $profile = $user->profile()->firstOrCreate([]);
        $settings = $this->brandingService->getSettingsForAgency($agency);
        $agencyDetails = $this->buildAgencyDetails($agent, $agency, $settings);

        return [
            'ok' => true,
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'username' => $user->username,
                'email_verified' => $user->email_verified_at !== null,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'role_label' => $user->isAgentAdmin() ? 'Agency owner' : 'Agency staff',
            ],
            'personal_profile' => [
                'phone' => $profile->phone,
                'whatsapp' => $profile->whatsapp,
                'country_code' => $profile->country_code,
                'city' => $profile->city,
                'designation' => data_get($user->meta, 'designation'),
            ],
            'agency_profile' => $agencyDetails,
            'capabilities' => [
                'can_edit_personal' => true,
                'can_edit_agency' => Gate::allows('updateAgency', $agent),
                'can_view_agency' => $user->hasAgentPermission(AgentPermission::AgencyView),
            ],
            'countries' => CountryList::forSelect(),
            'personal_update_url' => '/laravel/profile',
            'agency_update_url' => '/laravel/agent/agency',
            'password_update_url' => '/laravel/password',
            'supported_personal_fields' => [
                'name', 'email', 'username', 'phone', 'whatsapp', 'country_code', 'city',
            ],
            'supported_agency_fields' => [
                'agency_name', 'license_number', 'phone', 'city', 'country', 'address', 'email', 'logo',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAgencyDetails(Agent $agent, Agency $agency, AgencySetting $settings): array
    {
        $agentMeta = is_array($agent->meta) ? $agent->meta : [];

        $agencyName = $this->firstFilled(
            $agentMeta['agency_name'] ?? null,
            $agentMeta['company_name'] ?? null,
        );

        $logoPath = $agentMeta['logo_path'] ?? null;
        $logoUrl = null;
        if (is_string($logoPath) && $logoPath !== '' && Storage::disk('public')->exists($logoPath)) {
            $logoUrl = asset('storage/'.$logoPath);
        }

        $required = [
            'Agency name' => $agencyName,
            'Email' => $agent->user?->email,
            'Phone' => $this->firstFilled($agentMeta['phone'] ?? null, $agentMeta['mobile'] ?? null),
            'City' => $this->firstFilled($agentMeta['city'] ?? null),
            'Country' => $this->firstFilled($agentMeta['country'] ?? null),
            'Address' => $this->firstFilled($agentMeta['office_address'] ?? null),
        ];

        $missingFields = [];
        foreach ($required as $label => $value) {
            if (! filled($value)) {
                $missingFields[] = $label;
            }
        }

        return [
            'agency_name' => $agencyName,
            'legal_name' => $agencyName,
            'license_number' => $this->firstFilled($agentMeta['license_number'] ?? null, $agentMeta['ntn'] ?? null),
            'registration_number' => $this->firstFilled($agentMeta['license_number'] ?? null),
            'tax_number' => $this->firstFilled($agentMeta['ntn'] ?? null),
            'email' => $agent->user?->email,
            'phone' => $this->firstFilled($agentMeta['phone'] ?? null, $agentMeta['mobile'] ?? null),
            'city' => $this->firstFilled($agentMeta['city'] ?? null),
            'country' => $this->firstFilled($agentMeta['country'] ?? null),
            'address' => $this->firstFilled($agentMeta['office_address'] ?? null),
            'logo_url' => $logoUrl,
            'agent_code' => $agent->code,
            'platform_agency_name' => (string) ($settings->display_name ?: $agency->name),
            'verification' => [
                'is_complete' => $missingFields === [],
                'missing_fields' => $missingFields,
            ],
        ];
    }

    protected function firstFilled(mixed ...$values): ?string
    {
        foreach ($values as $value) {
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }
}
