<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Authoritative operational entity changed under a concurrent actor.
 * Controllers should refresh and return 409 rather than silently overwrite.
 */
class StaleOperationalStateException extends Exception
{
    /**
     * @param  array<string, mixed>  $freshState
     */
    public function __construct(
        string $message = 'This item was updated by another operator. Refresh and retry.',
        public readonly array $freshState = [],
    ) {
        parent::__construct($message);
    }

    public function render(Request $request): ?JsonResponse
    {
        if (! $request->expectsJson() && ! $request->wantsJson() && $request->query('format') !== 'json') {
            return null;
        }

        return response()->json([
            'ok' => false,
            'error' => 'stale_state',
            'message' => $this->getMessage(),
            'ticket' => $this->freshState['ticket'] ?? null,
        ], 409);
    }
}
