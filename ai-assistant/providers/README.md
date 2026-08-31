# Inference providers

Laravel talks to models only through `App\Contracts\Ai\InferenceProvider`.

## Implementations

- `App\Services\Ai\LocalLlamaProvider` — OpenAI-compatible chat completions on `127.0.0.1` only
- `App\Services\Ai\NullInferenceProvider` — always unhealthy; used when AI is disabled

## Binding

`AppServiceProvider` binds `InferenceProvider` to LocalLlama when `ota.ai_assistant.enabled` is true, otherwise Null.

## Hard rules

- Provider generates text/JSON only
- Application owns tools (`AiShoppingTools`, knowledge, handoff)
- Non-localhost gateway URLs are rejected
- Load shed / unhealthy provider → `STRUCTURED_FALLBACK` via `StructuredTravelIntentParser`
