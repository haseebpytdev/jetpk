<?php

namespace App\Http\Middleware;

use App\Services\Ai\AiAssistantEligibility;
use App\Services\Ai\Lab\AiLabFaultInjectionContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies authorized internal-canary fault simulation for one HTTP request only.
 */
final class ApplyAiLabCanaryFaultHeader
{
    public function __construct(
        private readonly AiAssistantEligibility $eligibility,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $header = trim((string) $request->header('X-JP-AI-Canary-Fault-Mode', ''));
        if ($header === '') {
            return $next($request);
        }

        if (! $this->eligibility->isEligibleRequest($request)) {
            return $next($request);
        }

        $expectedToken = trim((string) config('ai_lab.canary_fault_token', ''));
        $providedToken = trim((string) $request->header('X-JP-AI-Canary-Fault-Token', ''));
        if ($expectedToken === '' || ! hash_equals($expectedToken, $providedToken)) {
            return $next($request);
        }

        $mode = AiLabFaultInjectionContext::normalize($header);
        if ($mode === AiLabFaultInjectionContext::MODE_NORMAL) {
            return $next($request);
        }

        AiLabFaultInjectionContext::set($mode);
        try {
            return $next($request);
        } finally {
            AiLabFaultInjectionContext::clear();
        }
    }
}
