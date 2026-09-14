<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Ai\AiAssistantEligibility;
use App\Services\Ai\AiAssistantSettingsService;
use App\Services\Ai\AiChatOrchestrator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Ask JetPakistan admin control plane — safe toggles with env hard ceiling.
 */
class AiAssistantStatusController extends Controller
{
    public function __construct(
        private readonly AiAssistantEligibility $eligibility,
        private readonly AiAssistantSettingsService $settingsService,
        private readonly AiChatOrchestrator $orchestrator,
    ) {}

    public function show(Request $request): View
    {
        Gate::authorize('platform.admin');

        $status = $this->eligibility->statusPayload();
        $health = $this->orchestrator->healthPayload();

        return view('dashboard.admin.settings.ai-assistant', [
            'status' => $status,
            'health' => $health,
            'controlMatrix' => $status['control_matrix'] ?? [],
            'hardLockedWrites' => $status['hard_locked_writes'] ?? [],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        Gate::authorize('platform.admin');

        $validated = $request->validate([
            'master_enabled' => ['nullable', 'boolean'],
            'lab_adapter_enabled' => ['nullable', 'boolean'],
            'rag_enabled' => ['nullable', 'boolean'],
            'human_handoff_enabled' => ['nullable', 'boolean'],
            'learning_queue_enabled' => ['nullable', 'boolean'],
            'internal_canary_enabled' => ['nullable', 'boolean'],
            'flight_search_read_only_enabled' => ['nullable', 'boolean'],
        ]);

        $payload = [];
        foreach ([
            'master_enabled',
            'lab_adapter_enabled',
            'rag_enabled',
            'human_handoff_enabled',
            'learning_queue_enabled',
            'internal_canary_enabled',
            'flight_search_read_only_enabled',
        ] as $field) {
            $payload[$field] = $request->boolean($field);
        }

        $payload['audience_mode'] = $request->boolean('internal_canary_enabled')
            ? 'internal_canary'
            : 'off';

        try {
            $this->settingsService->update($request->user(), $payload);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['audience_mode' => $e->getMessage()]);
        }

        return back()->with('status', 'ai-assistant-settings-updated');
    }
}
