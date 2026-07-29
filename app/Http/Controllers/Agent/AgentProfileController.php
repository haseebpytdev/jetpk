<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Concerns\RespondsWithAgentPortalJson;
use App\Http\Controllers\Controller;
use App\Support\AgentPortal\AgentPortalProfilePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentProfileController extends Controller
{
    use RespondsWithAgentPortalJson;

    public function __construct(
        protected AgentPortalProfilePresenter $profilePresenter,
    ) {}

    public function show(Request $request): JsonResponse
    {
        if (! $this->wantsAgentPortalJson($request)) {
            abort(404);
        }

        return $this->agentPortalJson($this->profilePresenter->present($request->user()));
    }
}
