<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Auth\LoginOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicAuthController extends Controller
{
    public function __construct(
        private readonly LoginOtpService $loginOtpService,
    ) {}

    public function otpChallenge(Request $request): JsonResponse
    {
        if (! $this->loginOtpService->hasPending($request)) {
            return response()->json([
                'has_challenge' => false,
            ]);
        }

        return response()->json([
            'has_challenge' => true,
            'masked_email' => $this->loginOtpService->maskedEmail($request),
            'resend_available_in' => $this->loginOtpService->resendAvailableIn($request),
        ]);
    }

    public function registrationSecurityChallenge(Request $request): JsonResponse
    {
        $question = $request->session()->get('register_security_question');
        if (! is_string($question) || $question === '') {
            $left = random_int(1, 9);
            $right = random_int(1, 9);
            $question = 'What is '.$left.' + '.$right.'?';
            $request->session()->put('register_security_answer', $left + $right);
            $request->session()->put('register_security_question', $question);
        }

        return response()->json([
            'security_question' => $question,
        ]);
    }
}
