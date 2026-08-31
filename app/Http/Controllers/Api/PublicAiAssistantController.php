<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public AI chat entry. When AI is disabled or gateway unhealthy, returns a safe fallback.
 * Never exposes model paths, secrets, or mutation tools.
 */
class PublicAiAssistantController extends Controller
{
    public function chat(Request $request): JsonResponse
    {
        $enabled = (bool) config('ota.ai_assistant.enabled', false);
        $data = $request->validate([
            'message' => ['required', 'string', 'max:'.max(100, (int) config('ota.ai_assistant.max_message_chars', 2000))],
            'session_id' => ['nullable', 'string', 'max:64'],
        ]);

        if (! $enabled) {
            return response()->json([
                'ok' => false,
                'status' => 'unavailable',
                'message' => 'AI assistance is temporarily unavailable.',
                'actions' => [
                    ['label' => 'Search Flights', 'href' => '/#flight-search'],
                    ['label' => 'Browse Groups', 'href' => '/groups'],
                    ['label' => 'Manage Booking', 'href' => '/lookup-booking'],
                    ['label' => 'Contact Support', 'href' => '/support'],
                ],
            ], 503);
        }

        // Gateway call reserved for capacity-approved runtime; fail closed.
        return response()->json([
            'ok' => false,
            'status' => 'unavailable',
            'message' => 'AI assistance is temporarily unavailable.',
            'echo' => mb_substr($data['message'], 0, 80),
            'actions' => [
                ['label' => 'Search Flights', 'href' => '/#flight-search'],
                ['label' => 'Browse Groups', 'href' => '/groups'],
                ['label' => 'Contact Support', 'href' => '/support'],
            ],
        ], 503);
    }

    public function health(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'enabled' => (bool) config('ota.ai_assistant.enabled', false),
            'gateway' => 'not_loaded',
        ]);
    }
}
