<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Visa\VisaPolicyGate;
use App\Support\Platform\PlatformModuleGate;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/**
 * Admin visibility for optional Visa module controls (no secrets).
 */
final class VisaModuleSettingsController extends Controller
{
    public function __construct(private readonly VisaPolicyGate $policyGate) {}

    public function show(): View|JsonResponse
    {
        $payload = [
            'visa_module' => $this->policyGate->moduleEnabled() ? 'ON' : 'OFF',
            'saudi_mofa_provider' => $this->policyGate->providerEnabled() ? 'ENABLED' : 'DISABLED',
            'policy_approval' => $this->policyGate->policyApproved() ? 'YES' : 'NO',
            'live_allowed' => $this->policyGate->liveAllowed() ? 'YES' : 'NO',
            'live_deny_reason' => $this->policyGate->denyLiveReason(),
            'platform_module_visible' => PlatformModuleGate::visible('public_visa'),
            'default_provider' => config('visa.default_provider'),
            'document_source_type' => 'HTML',
            'pdf_export' => 'JetPakistan-generated copy (on demand)',
            'image_export' => 'JetPakistan-generated copy (on demand)',
            'notes' => [
                'Live MOFA requires module ON + provider ENABLED + policy YES + transport=live.',
                'Cookies/tokens are never shown here.',
            ],
        ];

        if (request()->wantsJson()) {
            return response()->json($payload)->header('Cache-Control', 'private, no-store');
        }

        return view('admin.visa.module-settings', ['status' => $payload]);
    }
}
