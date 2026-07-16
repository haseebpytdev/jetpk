<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\UpdateAgentAgencyRequest;
use App\Models\Agency;
use App\Models\AgencySetting;
use App\Models\Agent;
use App\Services\Agencies\AgencyBrandingService;
use App\Services\Agents\AgentWalletService;
use App\Support\Agencies\AgencyPrefixService;
use App\Support\Agents\AgentPermission;
use App\Support\Ui\MobileViewPreference;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * Agent business / agency profile (separate from personal profile settings).
 */
class AgentAgencyController extends Controller
{
    public function __construct(
        protected AgencyBrandingService $brandingService,
        protected AgentWalletService $walletService,
        protected MobileViewPreference $mobileViewPreference,
    ) {}

    public function show(Request $request): View
    {
        [$agent, $agency] = $this->resolveAgentAgency();
        Gate::authorize('view', $agent);

        $settings = $this->brandingService->getSettingsForAgency($agency);
        $details = $this->buildDetails($agent, $agency, $settings);
        $canViewWallet = auth()->user()?->hasAgentPermission(AgentPermission::WalletView) ?? false;
        $walletSummary = $canViewWallet ? $this->walletService->summary($agent) : [];

        $viewData = [
            'details' => $details,
            'walletSummary' => $walletSummary,
            'canEditAgency' => Gate::allows('updateAgency', $agent),
            'canViewWallet' => $canViewWallet,
        ];

        if ($this->mobileViewPreference->shouldUseMobileShell($request)) {
            return view('mobile.agent.agency.show', $viewData);
        }

        return view(client_view('agency', 'agent'), $viewData);
    }

    public function edit(Request $request): View
    {
        [$agent, $agency] = $this->resolveAgentAgency();
        Gate::authorize('updateAgency', $agent);

        $settings = $this->brandingService->getSettingsForAgency($agency);
        $details = $this->buildDetails($agent, $agency, $settings);

        $viewData = [
            'details' => $details,
        ];

        if ($this->mobileViewPreference->shouldUseMobileShell($request)) {
            return view('mobile.agent.agency.edit', $viewData);
        }

        return view(client_view('agency-edit', 'agent'), $viewData);
    }

    public function update(UpdateAgentAgencyRequest $request): RedirectResponse
    {
        [$agent] = $this->resolveAgentAgency();
        Gate::authorize('updateAgency', $agent);

        $validated = $request->validated();
        $meta = is_array($agent->meta) ? $agent->meta : [];

        $meta['agency_name'] = $validated['agency_name'];
        $meta['license_number'] = $validated['license_number'] ?? null;
        $meta['phone'] = $validated['phone'] ?? null;
        $meta['mobile'] = $validated['phone'] ?? null;
        $meta['city'] = $validated['city'] ?? null;
        $meta['country'] = $validated['country'] ?? null;
        $meta['office_address'] = $validated['address'] ?? null;

        if ($request->hasFile('logo')) {
            $oldPath = $meta['logo_path'] ?? null;
            $path = $request->file('logo')->store(
                'agent-logos/'.$agent->id,
                'public',
            );
            $meta['logo_path'] = $path;

            if (is_string($oldPath) && $oldPath !== '' && $oldPath !== $path && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        $agent->forceFill(['meta' => $meta])->save();

        if (array_key_exists('email', $validated) && filled($validated['email'])) {
            $agent->user?->forceFill(['email' => $validated['email']])->save();
        }

        if (
            array_key_exists('code_prefix', $validated)
            && filled($validated['code_prefix'])
            && AgencyPrefixService::canAgentSetPrefix($agency)
        ) {
            AgencyPrefixService::savePrefix($agency, (string) $validated['code_prefix']);
        }

        return redirect()
            ->route('agent.agency.show')
            ->with('status', 'agency-updated');
    }

    /**
     * @return array{0: Agent, 1: Agency}
     */
    protected function resolveAgentAgency(): array
    {
        $agent = auth()->user()?->agent();
        abort_if($agent === null, 403);

        $agent->loadMissing(['agency', 'user']);
        $agency = $agent->agency;
        abort_if($agency === null, 404);

        return [$agent, $agency];
    }

    /**
     * @return array{
     *     agency_name: string|null,
     *     license_number: string|null,
     *     logo_url: string|null,
     *     email: string|null,
     *     phone: string|null,
     *     city: string|null,
     *     country: string|null,
     *     address: string|null,
     *     agent_code: string|null,
     *     platform_agency_name: string,
     *     is_complete: bool,
     *     missing_fields: list<string>
     * }
     */
    protected function buildDetails(Agent $agent, Agency $agency, AgencySetting $settings): array
    {
        $agentMeta = is_array($agent->meta) ? $agent->meta : [];
        $userMeta = is_array($agent->user?->meta) ? $agent->user->meta : [];

        $agencyName = $this->firstFilled(
            $agentMeta['agency_name'] ?? null,
            $agentMeta['company_name'] ?? null,
            $userMeta['company_name'] ?? null,
        );

        $licenseNumber = $this->firstFilled(
            $agentMeta['license_number'] ?? null,
            $agentMeta['ntn'] ?? null,
            $agentMeta['iata_number'] ?? null,
        );

        $email = $agent->user?->email;
        $phone = $this->firstFilled(
            $agentMeta['phone'] ?? null,
            $agentMeta['mobile'] ?? null,
            $userMeta['phone'] ?? null,
        );
        $city = $this->firstFilled($agentMeta['city'] ?? null, $userMeta['city'] ?? null);
        $country = $this->firstFilled($agentMeta['country'] ?? null);
        $address = $this->firstFilled($agentMeta['office_address'] ?? null);

        $logoPath = $agentMeta['logo_path'] ?? null;
        $logoUrl = null;
        if (is_string($logoPath) && $logoPath !== '' && Storage::disk('public')->exists($logoPath)) {
            $logoUrl = asset('storage/'.$logoPath);
        }

        $required = [
            'Agency name' => $agencyName,
            'Email' => $email,
            'Phone' => $phone,
            'City' => $city,
            'Country' => $country,
            'Address' => $address,
        ];

        $missingFields = [];
        foreach ($required as $label => $value) {
            if (! filled($value)) {
                $missingFields[] = $label;
            }
        }

        return [
            'agency_name' => $agencyName,
            'license_number' => $licenseNumber,
            'logo_url' => $logoUrl,
            'email' => $email,
            'phone' => $phone,
            'city' => $city,
            'country' => $country,
            'address' => $address,
            'agent_code' => $agent->code,
            'platform_agency_name' => (string) ($settings->display_name ?: $agency->name),
            'agency_prefix' => AgencyPrefixService::resolvePrefix($agency),
            'stored_agency_prefix' => AgencyPrefixService::storedPrefix($agency),
            'suggested_agency_prefix' => AgencyPrefixService::suggestPrefix($agency->name, (int) $agency->id),
            'can_set_agency_prefix' => AgencyPrefixService::canAgentSetPrefix($agency),
            'is_complete' => $missingFields === [],
            'missing_fields' => $missingFields,
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
