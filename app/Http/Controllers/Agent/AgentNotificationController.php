<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Concerns\RespondsWithAgentPortalJson;
use App\Http\Controllers\Controller;
use App\Support\AgentPortal\AgentPortalNotificationPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentNotificationController extends Controller
{
    use RespondsWithAgentPortalJson;

    public function __construct(
        protected AgentPortalNotificationPresenter $presenter,
    ) {}

    public function index(Request $request): JsonResponse
    {
        if (! $this->wantsAgentPortalJson($request)) {
            abort(404);
        }

        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(50, max(5, (int) $request->query('per_page', 20)));

        return $this->agentPortalJson(
            $this->presenter->presentIndex($request->user(), $page, $perPage),
        );
    }

    public function unreadSummary(Request $request): JsonResponse
    {
        if (! $this->wantsAgentPortalJson($request)) {
            abort(404);
        }

        return $this->agentPortalJson($this->presenter->presentUnreadSummary($request->user()));
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        if (! $this->wantsAgentPortalJson($request)) {
            abort(404);
        }

        return $this->agentPortalJson($this->presenter->markReadUnavailable(), 501);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        if (! $this->wantsAgentPortalJson($request)) {
            abort(404);
        }

        return $this->agentPortalJson($this->presenter->markReadUnavailable(), 501);
    }
}
