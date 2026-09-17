<?php

namespace App\Services\Ai\Lab;

use App\Contracts\Ai\Lab\AiLabConsultantGateway;
use App\Data\Ai\Lab\V1\ConsultantTurnRequest;
use App\Data\Ai\Lab\V1\ConsultantTurnResponse;

/**
 * In-memory gateway for integration tests.
 */
final class FakeAiLabConsultantGateway implements AiLabConsultantGateway
{
    /** @var callable|null */
    private $turnHandler;

    public function __construct(
        private bool $healthy = true,
        ?callable $turnHandler = null,
    ) {
        $this->turnHandler = $turnHandler;
    }

    public function setTurnHandler(callable $handler): void
    {
        $this->turnHandler = $handler;
    }

    public function isHealthy(): bool
    {
        return $this->healthy;
    }

    public function turn(ConsultantTurnRequest $request): ConsultantTurnResponse
    {
        if ($this->turnHandler !== null) {
            return ($this->turnHandler)($request);
        }

        return ConsultantTurnResponse::fromArray([
            'assistant_message' => 'Lab fake response.',
            'status' => 'ok',
            'mode' => 'LAB_CONSULTANT_V1',
            'action' => ['kind' => 'NONE'],
            'confirmation' => ['state' => 'NONE'],
            'lab_state' => ['dialog_state' => 'COLLECTING'],
        ]);
    }
}
