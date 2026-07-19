<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AccountType;
use App\Enums\UserAccountStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StoreCustomerRegistrationRequest;
use App\Mail\AdminNewCustomerSignupMail;
use App\Mail\CustomerWelcomeMail;
use App\Models\Agency;
use App\Models\User;
use App\Services\Client\ClientPageRenderer;
use App\Services\Client\ClientRedirectResolver;
use App\Support\Client\ClientPageKeys;
use App\Support\Auth\CheckoutReturnIntent;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Throwable;

class RegisteredUserController extends Controller
{
    public function __construct(
        protected ClientRedirectResolver $clientRedirectResolver,
        protected ClientPageRenderer $pageRenderer,
    ) {}

    /**
     * Display the registration view.
     */
    public function create(Request $request): View
    {
        CheckoutReturnIntent::primeSessionFromQuery($request);

        $question = $request->session()->get('register_security_question');
        if (! $request->session()->has('register_security_answer') || ! is_string($question) || $question === '') {
            $question = $this->storeSecurityChallenge($request);
        }

        $viewData = array_merge($this->pageRenderer->viewModel(ClientPageKeys::REGISTER), [
            'securityQuestion' => $question,
        ]);

        return view(client_view('auth.register', 'frontend'), $viewData);
    }

    public function store(StoreCustomerRegistrationRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $phone = StoreCustomerRegistrationRequest::formatStoredPhone(
            $validated['mobile_country_code'],
            $validated['mobile'],
        );

        $user = User::create([
            'name' => trim($validated['first_name'].' '.$validated['last_name']),
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'account_type' => AccountType::Customer,
            'status' => UserAccountStatus::Active,
            'current_agency_id' => Agency::query()->where('slug', config('ota.default_agency_slug'))->value('id'),
            'social_email_verification_deadline' => now()->addDay(),
            'meta' => [
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'phone' => $phone,
            ],
        ]);

        event(new Registered($user));

        $this->sendCustomerWelcomeEmail($user);
        $this->sendAdminNewCustomerEmail($user, $phone);

        Auth::login($user);
        $request->session()->regenerate();
        $this->storeSecurityChallenge($request);

        return $this->clientRedirectResolver
            ->route('verification.notice')
            ->with('status', 'registration-complete');
    }

    public function validateField(Request $request): JsonResponse
    {
        $allowedFields = [
            'first_name',
            'last_name',
            'email',
            'mobile_country_code',
            'mobile',
            'password',
            'password_confirmation',
            'security_answer',
        ];

        $field = trim((string) $request->input('field', ''));
        if ($field === '' || ! in_array($field, $allowedFields, true)) {
            return response()->json([
                'valid' => false,
                'errors' => ['field' => ['Invalid field for validation.']],
            ], 422);
        }

        $payload = $this->validationPayload($request);
        $payload['terms'] = '1';

        $rules = StoreCustomerRegistrationRequest::sharedRules($request->session()->get('register_security_answer'));
        $fieldsToValidate = [$field];

        if (in_array($field, ['password', 'password_confirmation'], true)) {
            $fieldsToValidate = ['password', 'password_confirmation'];
        }

        if (in_array($field, ['mobile_country_code', 'mobile'], true)) {
            $fieldsToValidate = ['mobile_country_code', 'mobile'];
        }

        $fieldRules = [];
        foreach ($fieldsToValidate as $name) {
            $fieldRules[$name] = $rules[$name];
        }

        $validator = Validator::make($payload, $fieldRules, (new StoreCustomerRegistrationRequest)->messages());

        if ($validator->fails()) {
            return response()->json([
                'valid' => false,
                'errors' => $validator->errors()->toArray(),
            ], 422);
        }

        return response()->json([
            'valid' => true,
            'errors' => new \stdClass,
        ]);
    }

    private function validationPayload(Request $request): array
    {
        $securityAnswer = $request->input('security_answer', $request->input('security_check'));
        $countryCode = StoreCustomerRegistrationRequest::normalizeCountryCode((string) $request->input('mobile_country_code', ''));
        $rawMobile = trim((string) $request->input('mobile', ''));

        if ($countryCode === '') {
            $countryCode = '+92';
        }

        if ($countryCode === '+92' && preg_match('/^\d+$/', $rawMobile) && str_starts_with($rawMobile, '92') && strlen($rawMobile) > 10) {
            $rawMobile = substr($rawMobile, 2);
        }

        return [
            'first_name' => trim((string) $request->input('first_name', '')),
            'last_name' => trim((string) $request->input('last_name', '')),
            'email' => strtolower(trim((string) $request->input('email', ''))),
            'mobile_country_code' => $countryCode,
            'mobile' => $rawMobile,
            'password' => (string) $request->input('password', ''),
            'password_confirmation' => (string) $request->input('password_confirmation', ''),
            'security_answer' => is_scalar($securityAnswer) ? trim((string) $securityAnswer) : '',
        ];
    }

    private function storeSecurityChallenge(Request $request): string
    {
        $left = random_int(1, 9);
        $right = random_int(1, 9);
        $question = 'What is '.$left.' + '.$right.'?';
        $request->session()->put('register_security_answer', $left + $right);
        $request->session()->put('register_security_question', $question);

        return $question;
    }

    private function sendCustomerWelcomeEmail(User $user): void
    {
        try {
            Mail::to($user->email)->send(CustomerWelcomeMail::forUser($user));
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function sendAdminNewCustomerEmail(User $user, string $phone): void
    {
        $recipients = $this->resolveAdminSignupRecipients($user);
        if ($recipients === []) {
            return;
        }

        try {
            Mail::to($recipients)->send(new AdminNewCustomerSignupMail($user, $phone));
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * @return array<int, string>
     */
    private function resolveAdminSignupRecipients(User $user): array
    {
        $emails = [];
        $agency = $user->currentAgency;
        if ($agency !== null) {
            $agency->loadMissing('agencySetting');
            $support = $agency->agencySetting?->support_email;
            if (is_string($support) && filter_var($support, FILTER_VALIDATE_EMAIL)) {
                $emails[] = strtolower(trim($support));
            }
        }

        foreach ([config('ota-brand.support_email'), config('ota-client.support_email')] as $fallback) {
            if (is_string($fallback) && filter_var($fallback, FILTER_VALIDATE_EMAIL)) {
                $emails[] = strtolower(trim($fallback));
            }
        }

        return array_values(array_unique($emails));
    }
}
