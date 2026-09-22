<?php

namespace Tests\Unit\Ai;

use App\Data\Ai\Lab\V1\ConsultantTurnResponse;
use App\Services\Ai\Lab\RagLiveDataBlocker;
use Tests\TestCase;

class RagLiveDataBlockerTest extends TestCase
{
    public function test_rag_live_fare_query_is_refused_without_mutation(): void
    {
        config(['ai_lab.rag_block_live_data' => true]);

        $blocker = new RagLiveDataBlocker;
        $response = ConsultantTurnResponse::fromArray([
            'assistant_message' => 'Current fare is PKR 50000',
            'status' => 'ok',
            'mode' => 'LAB_CONSULTANT_V1',
            'action' => ['kind' => 'RAG_ANSWER'],
            'confirmation' => ['state' => 'NONE'],
            'rag' => ['hits' => [], 'blocked_live_data' => false],
            'lab_state' => ['dialog_state' => 'COLLECTING'],
        ]);

        $sanitized = $blocker->sanitize($response, 'What is the live fare for LHE to DXB?');

        $this->assertSame('refused', $sanitized->status);
        $this->assertSame('NONE', $sanitized->actionKind());
        $this->assertEmpty($sanitized->recommendations);
        $this->assertTrue($sanitized->meta['rag_live_data_blocked'] ?? false);
    }
}
