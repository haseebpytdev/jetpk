<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Ai\AiAssistantEligibility;
use App\Services\Ai\AiChatOrchestrator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Read-only Ask JetPakistan status for platform admins (no secrets).
 */
class AiAssistantStatusController extends Controller
{
    public function __construct(
        private readonly AiAssistantEligibility $eligibility,
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
        ]);
    }
}
