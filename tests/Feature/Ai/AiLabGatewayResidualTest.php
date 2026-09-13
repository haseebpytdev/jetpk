<?php

namespace Tests\Feature\Ai;

use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Residual Lab-03 cases against real Python lab pipeline (subprocess).
 */
class AiLabGatewayResidualTest extends TestCase
{
    public function test_residual_cases_route_visible_and_no_execution_without_confirmation(): void
    {
        $labRoot = base_path('tmp/ai-lab');
        $gateway = base_path('ai-lab-gateway/server.py');
        if (! is_dir($labRoot) || ! is_file($gateway)) {
            $this->markTestSkipped('AI lab or gateway not present in workspace.');
        }

        $fixtures = json_decode(
            (string) file_get_contents(base_path('tests/Fixtures/ai/lab-03-residual-5.json')),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        foreach ($fixtures['cases'] as $case) {
            $payload = json_encode([
                'contract_version' => 'v1',
                'conversation_id' => 'residual-'.$case['case_id'],
                'message' => $case['message'],
                'state' => ['lab' => []],
                'user_context' => ['authenticated' => false, 'user_id' => null],
            ], JSON_THROW_ON_ERROR);

            $script = <<<'PY'
import json, os, sys
os.environ.setdefault("OTA_AI_LAB_REPO_PATH", sys.argv[1])
sys.path.insert(0, sys.argv[2])
from server import handle_turn
print(json.dumps(handle_turn(json.loads(sys.stdin.read()))))
PY;

            $process = new Process(['python', '-c', $script, $labRoot, dirname($gateway)]);
            $process->setInput($payload);
            $process->setTimeout(30);
            $process->run();

            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
            $result = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);

            $origin = $case['expected_origin'];
            $destination = $case['expected_destination'];
            $reply = (string) ($result['assistant_message'] ?? '');
            $labState = is_array($result['lab_state'] ?? null) ? $result['lab_state'] : [];
            $requireFullRoute = ($case['safety_class'] ?? '') === 'SAFE_CONFIRMATION_CATCH';

            $this->assertNotSame('', $reply, $case['case_id'].' silent response');
            if ($requireFullRoute) {
                $this->assertStringContainsString($origin, $reply, $case['case_id'].' origin not visible');
                $this->assertStringContainsString($destination, $reply, $case['case_id'].' destination not visible');
            } else {
                $this->assertTrue(
                    str_contains(strtolower($reply), strtolower($destination))
                    || str_contains(strtolower($reply), 'kahan')
                    || ($labState['clarification_needed'] ?? false) === true,
                    $case['case_id'].' clarification not surfaced'
                );
            }
            $this->assertNotTrue($labState['tool_executed'] ?? false, $case['case_id'].' executed without confirmation');
        }
    }
}
