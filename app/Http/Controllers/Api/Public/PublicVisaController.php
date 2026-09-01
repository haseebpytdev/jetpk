<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Services\Visa\DTO\VisaLookupRequest;
use App\Services\Visa\Exceptions\VisaException;
use App\Services\Visa\Support\VisaRedactor;
use App\Services\Visa\VisaExportService;
use App\Services\Visa\VisaLookupService;
use App\Services\Visa\VisaPolicyGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

final class PublicVisaController extends Controller
{
    public function __construct(
        private readonly VisaLookupService $lookups,
        private readonly VisaExportService $exports,
        private readonly VisaPolicyGate $policyGate,
        private readonly VisaRedactor $redactor,
    ) {}

    public function health(): JsonResponse
    {
        $health = $this->lookups->health();

        return response()->json([
            'module_enabled' => $this->policyGate->moduleEnabled(),
            'provider' => [
                'key' => $health->providerKey,
                'status' => $health->status,
                'detail' => $health->detail,
                'live_allowed' => $health->liveAllowed,
                'policy_approved' => $health->policyApproved,
                'lookup' => $health->lookupCapable,
                'captcha' => $health->captchaCapable,
                'document' => $health->documentCapable,
                'pdf_export' => $health->pdfExportCapable,
                'image_export' => $health->imageExportCapable,
            ],
            'official_fallback_url' => config('visa.saudi_mofa.official_fallback_url'),
        ])->header('Cache-Control', 'private, no-store')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function capabilities(): JsonResponse
    {
        try {
            $caps = $this->lookups->capabilities();
        } catch (Throwable $e) {
            return $this->errorFrom($e);
        }

        return response()->json([
            'provider_key' => $caps->providerKey,
            'country_code' => $caps->countryCode,
            'country_label' => $caps->countryLabel,
            'service_label' => $caps->serviceLabel,
            'criteria' => $caps->criteria,
            'nationality_required' => $caps->nationalityRequired,
            'captcha_required' => $caps->captchaRequired,
            'document_source_type' => $caps->documentSourceType,
            'export_formats' => $caps->exportFormats,
            'official_fallback_url' => $caps->officialFallbackUrl,
        ])->header('Cache-Control', 'private, no-store')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function start(Request $request): JsonResponse
    {
        try {
            $started = $this->lookups->start();
            $session = $started['session'];
            $captcha = $started['captcha'];
        } catch (Throwable $e) {
            return $this->errorFrom($e);
        }

        return response()->json([
            'lookup_session_id' => $session->id,
            'expires_at' => $session->expiresAt,
            'captcha' => [
                'mime' => $captcha->mimeType,
                'image_base64' => $captcha->imageBase64,
                'expires_at' => $captcha->expiresAt,
            ],
        ])->header('Cache-Control', 'private, no-store')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function refreshCaptcha(Request $request): JsonResponse
    {
        $request->validate(['lookup_session_id' => 'required|string']);
        try {
            $captcha = $this->lookups->refreshCaptcha((string) $request->input('lookup_session_id'));
        } catch (Throwable $e) {
            return $this->errorFrom($e);
        }

        return response()->json([
            'lookup_session_id' => $captcha->lookupSessionId,
            'captcha' => [
                'mime' => $captcha->mimeType,
                'image_base64' => $captcha->imageBase64,
                'expires_at' => $captcha->expiresAt,
            ],
        ])->header('Cache-Control', 'private, no-store')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function lookup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lookup_session_id' => 'required|string',
            'first_criterion' => 'required|string',
            'first_value' => 'required|string|max:64',
            'second_criterion' => 'required|string',
            'second_value' => 'required|string|max:64',
            'nationality' => 'required|string|max:8',
            'captcha_answer' => 'required|string|max:32',
        ]);

        try {
            $result = $this->lookups->lookup(new VisaLookupRequest(
                firstCriterion: $data['first_criterion'],
                firstValue: $data['first_value'],
                secondCriterion: $data['second_criterion'],
                secondValue: $data['second_value'],
                nationality: $data['nationality'],
                captchaAnswer: $data['captcha_answer'],
                lookupSessionId: $data['lookup_session_id'],
            ));
        } catch (Throwable $e) {
            return $this->errorFrom($e);
        }

        $fields = $result->fields;
        if (isset($fields['passport_number'])) {
            $fields['passport_number_masked'] = $this->redactor->maskIdentifier((string) $fields['passport_number']);
        }

        return response()->json([
            'lookup_session_id' => $result->lookupSessionId,
            'status' => $result->status,
            'fields' => $fields,
            'document_ref' => $result->documentRef,
            'attribution' => $result->sourceAttribution,
            'expires_at' => $result->expiresAt,
        ])->header('Cache-Control', 'private, no-store')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function document(Request $request): Response|JsonResponse
    {
        $data = $request->validate([
            'lookup_session_id' => 'required|string',
            'document_ref' => 'required|string',
        ]);
        try {
            $doc = $this->exports->document($data['lookup_session_id'], $data['document_ref']);
        } catch (Throwable $e) {
            return $this->errorFrom($e);
        }

        return response($doc->bytes, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Content-Disposition' => 'inline; filename="visa-document.html"',
            'Cache-Control' => 'private, no-store',
            'X-Robots-Tag' => 'noindex, nofollow',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function exportPdf(Request $request): Response|JsonResponse
    {
        $data = $request->validate([
            'lookup_session_id' => 'required|string',
            'document_ref' => 'required|string',
        ]);
        try {
            $export = $this->exports->exportPdf($data['lookup_session_id'], $data['document_ref']);
        } catch (Throwable $e) {
            return $this->errorFrom($e);
        }

        return response($export->bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="visa-copy.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Robots-Tag' => 'noindex, nofollow',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function exportPng(Request $request): Response|JsonResponse
    {
        $data = $request->validate([
            'lookup_session_id' => 'required|string',
            'document_ref' => 'required|string',
        ]);
        try {
            $export = $this->exports->exportPng($data['lookup_session_id'], $data['document_ref']);
        } catch (Throwable $e) {
            return $this->errorFrom($e);
        }

        return response($export->bytes, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="visa-copy.png"',
            'Cache-Control' => 'private, no-store',
            'X-Robots-Tag' => 'noindex, nofollow',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function errorFrom(Throwable $e): JsonResponse
    {
        $code = $e instanceof VisaException ? $e->errorCode() : 'PROVIDER_UNAVAILABLE';
        $messages = [
            'CAPTCHA_INVALID' => 'The image code was incorrect. Please try again.',
            'CAPTCHA_EXPIRED' => 'The image code expired. Refresh and try again.',
            'VISA_NOT_FOUND' => 'No matching visa was found for the details provided.',
            'SESSION_EXPIRED' => 'Your visa lookup session expired. Please start again.',
            'PROVIDER_CHANGED' => 'Saudi visa lookup is temporarily unavailable. Please use the official Saudi MOFA service.',
            'PROVIDER_UNAVAILABLE' => 'Saudi visa lookup is temporarily unavailable. Please use the official Saudi MOFA service.',
        ];

        return response()->json([
            'error' => $code,
            'message' => $messages[$code] ?? $messages['PROVIDER_UNAVAILABLE'],
            'official_fallback_url' => config('visa.saudi_mofa.official_fallback_url'),
        ], 422)->header('Cache-Control', 'private, no-store')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
