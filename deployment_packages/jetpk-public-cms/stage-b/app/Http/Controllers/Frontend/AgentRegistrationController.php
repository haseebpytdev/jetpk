<?php

namespace App\Http\Controllers\Frontend;

use App\Enums\AccountType;
use App\Enums\OtaNotificationEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Frontend\StoreAgentApplicationRequest;
use App\Models\Agency;
use App\Models\AgentApplication;
use App\Models\User;
use App\Services\Client\ClientPageRenderer;
use App\Services\Communication\OtaNotificationService;
use App\Support\Client\ClientPageKeys;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class AgentRegistrationController extends Controller
{
    public function __construct(
        protected OtaNotificationService $notificationService,
        protected ClientPageRenderer $pageRenderer,
    ) {}

    public function landing(Request $request): View
    {

        return view(client_view('frontend.agent-registration.landing', 'frontend'), $this->pageRenderer->viewModel(ClientPageKeys::AGENT_REGISTRATION));
    }

    public function create(Request $request): View
    {

        return view(client_view('frontend.agent-registration.form', 'frontend'));
    }

    public function validateField(Request $request): JsonResponse
    {
        $field = trim((string) $request->input('field', ''));
        if ($field === '' || ! in_array($field, StoreAgentApplicationRequest::ajaxValidationFields(), true)) {
            return response()->json([
                'success' => false,
                'errors' => ['field' => ['Invalid field for validation.']],
            ], 422);
        }

        $payload = StoreAgentApplicationRequest::normalizeAjaxPayload($request->all());
        $fieldsToValidate = StoreAgentApplicationRequest::fieldsToValidateFor($field);
        $fieldRules = StoreAgentApplicationRequest::rulesForFields($fieldsToValidate);

        $validator = Validator::make(
            $payload,
            $fieldRules,
            (new StoreAgentApplicationRequest)->messages(),
        );

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()->toArray(),
            ], 422);
        }

        if ($field === 'email') {
            $email = (string) ($payload['email'] ?? '');
            $agentExists = User::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->where('account_type', AccountType::Agent)
                ->exists();

            if ($agentExists) {
                return response()->json([
                    'success' => false,
                    'errors' => ['email' => [StoreAgentApplicationRequest::DUPLICATE_EMAIL_MESSAGE]],
                ], 422);
            }
        }

        return response()->json([
            'success' => true,
            'errors' => new \stdClass,
        ]);
    }

    public function store(StoreAgentApplicationRequest $request): RedirectResponse
    {
        $validated = $request->applicationAttributes();
        $email = (string) $validated['email'];

        $agentExists = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->where('account_type', AccountType::Agent)
            ->exists();

        if ($agentExists) {
            return back()
                ->withErrors(['email' => StoreAgentApplicationRequest::DUPLICATE_EMAIL_MESSAGE])
                ->withInput($request->except('terms'));
        }

        $existingApplication = AgentApplication::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if ($existingApplication) {
            return redirect()
                ->route('agent.register.submitted')
                ->with('status', 'We already received an agent application for this email address. Our team will review the existing application.');
        }

        AgentApplication::query()->create([
            ...$validated,
            'status' => 'pending',
        ]);

        $agency = Agency::query()->where('slug', config('ota.default_agency_slug'))->first()
            ?? Agency::query()->first();
        if ($agency !== null) {
            try {
                $this->notificationService->send(
                    agency: $agency,
                    eventKey: OtaNotificationEvent::AgentApplicationSubmitted->value,
                    payload: [
                        'applicant_name' => trim($validated['first_name'].' '.$validated['last_name']),
                        'company_name' => $validated['company_name'],
                        'city' => $validated['city'],
                    ],
                    fallbackSubject: 'New agent application received',
                    fallbackBody: 'A new agent application was submitted and is pending review.',
                    templateVariables: [
                        'applicant_name' => trim($validated['first_name'].' '.$validated['last_name']),
                        'company_name' => $validated['company_name'],
                        'city' => $validated['city'],
                    ],
                    recipientContext: ['applicant_email' => $email],
                );
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return redirect()->route('agent.register.submitted');
    }

    public function submitted(Request $request): View
    {

        return view(client_view('frontend.agent-registration.submitted', 'frontend'));
    }
}
