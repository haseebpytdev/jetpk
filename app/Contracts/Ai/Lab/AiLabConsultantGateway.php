<?php

namespace App\Contracts\Ai\Lab;

use App\Data\Ai\Lab\V1\ConsultantTurnRequest;
use App\Data\Ai\Lab\V1\ConsultantTurnResponse;

/**
 * HTTP port to the localhost AI lab consultant gateway.
 */
interface AiLabConsultantGateway
{
    public function isHealthy(): bool;

    public function turn(ConsultantTurnRequest $request): ConsultantTurnResponse;
}
