<?php

namespace App\Providers;

use App\Contracts\Ai\InferenceProvider;
use App\Contracts\Ai\Lab\AiLabConsultantGateway;
use App\Http\Middleware\ApplyAiLabCanaryFaultHeader;
use App\Services\Ai\Lab\AiLabAdapter;
use App\Services\Ai\Lab\HttpAiLabConsultantGateway;
use App\Services\Ai\LocalLlamaProvider;
use App\Services\Ai\NullInferenceProvider;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

/**
 * AI runtime bindings — isolated from SEO/shared AppServiceProvider deploy copies.
 */
final class AiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AiLabConsultantGateway::class, HttpAiLabConsultantGateway::class);
        $this->app->singleton(AiLabAdapter::class);

        $this->app->singleton(InferenceProvider::class, function (): InferenceProvider {
            $mode = strtolower((string) config('ota.ai_assistant.mode', 'off'));
            $legacyOn = (bool) config('ota.ai_assistant.enabled', false);
            $runtimeOn = $mode === 'public' || $mode === 'internal_canary' || ($mode === 'off' && $legacyOn);
            $conversational = (bool) config('ota.ai_assistant.conversational_enabled', true);
            $optionalAssist = (bool) config('ota.ai_assistant.optional_llm_assist', false);

            if (! $runtimeOn || (! $conversational && ! $optionalAssist)) {
                return new NullInferenceProvider;
            }

            return new LocalLlamaProvider;
        });
    }

    public function boot(): void
    {
        $this->app->booted(function (): void {
            $this->app->make(Router::class)->aliasMiddleware(
                'ai.lab.canary.fault',
                ApplyAiLabCanaryFaultHeader::class,
            );
        });
    }
}
