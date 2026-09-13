<?php

namespace Tests\Unit\Ai\Lab;

use App\Data\Ai\Lab\V1\ConsultantTurnResponse;
use App\Services\Ai\Lab\ConfirmationPolicyGate;
use PHPUnit\Framework\TestCase;

class ConfirmationPolicyGateTest extends TestCase
{
    public function test_shadow_search_blocked_without_confirmation(): void
    {
        $gate = new ConfirmationPolicyGate;
        $response = ConsultantTurnResponse::fromArray([
            'assistant_message' => 'Searching...',
            'status' => 'ok',
            'mode' => 'LAB_CONSULTANT_V1',
            'action' => ['kind' => 'SHADOW_FLIGHT_SEARCH', 'payload' => ['query' => ['origin' => 'LHE', 'destination' => 'DXB']]],
            'confirmation' => ['state' => 'PROPOSED', 'snapshot_hash' => 'abc'],
            'lab_state' => ['tool_executed' => false, 'user_confirmed' => false],
        ]);

        $result = $gate->evaluate($response, ['confirmation_token' => 'abc']);

        $this->assertFalse($result['allowed']);
        $this->assertSame('confirmation_required', $result['reason']);
    }

    public function test_shadow_search_allowed_after_confirmed_snapshot(): void
    {
        $gate = new ConfirmationPolicyGate;
        $response = ConsultantTurnResponse::fromArray([
            'assistant_message' => 'Done',
            'status' => 'ok',
            'mode' => 'LAB_CONSULTANT_V1',
            'action' => ['kind' => 'SHADOW_FLIGHT_SEARCH', 'payload' => ['query' => ['origin' => 'LHE', 'destination' => 'DXB']]],
            'confirmation' => ['state' => 'CONFIRMED', 'snapshot_hash' => 'abc'],
            'lab_state' => [
                'tool_executed' => true,
                'user_confirmed' => true,
                'confirmation_valid' => true,
            ],
        ]);

        $result = $gate->evaluate($response, ['confirmation_token' => 'abc']);

        $this->assertTrue($result['allowed']);
    }

    public function test_snapshot_mismatch_blocks_action(): void
    {
        $gate = new ConfirmationPolicyGate;
        $response = ConsultantTurnResponse::fromArray([
            'assistant_message' => 'Done',
            'status' => 'ok',
            'mode' => 'LAB_CONSULTANT_V1',
            'action' => ['kind' => 'SHADOW_FLIGHT_SEARCH', 'payload' => []],
            'confirmation' => ['state' => 'CONFIRMED', 'snapshot_hash' => 'new'],
            'lab_state' => [
                'tool_executed' => true,
                'user_confirmed' => true,
                'confirmation_valid' => true,
            ],
        ]);

        $result = $gate->evaluate($response, ['confirmation_token' => 'old']);

        $this->assertFalse($result['allowed']);
        $this->assertSame('snapshot_mismatch', $result['reason']);
    }
}
