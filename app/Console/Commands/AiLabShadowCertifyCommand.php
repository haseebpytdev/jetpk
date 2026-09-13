<?php

namespace App\Console\Commands;

use App\Contracts\Ai\Lab\AiLabConsultantGateway;
use App\Data\Ai\Lab\V1\ConsultantTurnRequest;
use App\Services\Ai\Lab\ConfirmationPolicyGate;
use Illuminate\Console\Command;

/**
 * Internal production canary — exercise lab gateway contract without browser UI.
 */
class AiLabShadowCertifyCommand extends Command
{
    protected $signature = 'ai:lab-shadow-certify {--json : Output JSON summary}';

    protected $description = 'Run internal shadow certification probes against the AI lab gateway';

    public function handle(
        AiLabConsultantGateway $gateway,
        ConfirmationPolicyGate $gate,
    ): int {
        if (! $gateway->isHealthy()) {
            $this->error('AI lab gateway unhealthy');

            return self::FAILURE;
        }

        $cases = [
            ['id' => 'english_clarify', 'message' => 'LHE to DXB'],
            ['id' => 'roman_urdu', 'message' => 'lahore se dubai kal'],
            ['id' => 'rag_policy', 'message' => 'baggage allowance'],
            ['id' => 'live_fare_block', 'message' => 'what is the live fare LHE to DXB'],
            ['id' => 'visa_unsupported', 'message' => 'I need a tourist visa'],
        ];

        $passed = 0;
        $failed = 0;
        $results = [];

        foreach ($cases as $case) {
            try {
                $response = $gateway->turn(new ConsultantTurnRequest(
                    conversationId: 'certify-'.$case['id'],
                    message: $case['message'],
                ));
                $ok = $response->assistantMessage !== '';
                if ($case['id'] === 'live_fare_block') {
                    $ok = $ok && ($response->rag['blocked_live_data'] ?? false) === true
                        || str_contains(strtolower($response->assistantMessage), 'booking tools');
                }
                if ($case['id'] === 'visa_unsupported') {
                    $ok = $ok && str_contains(strtolower($response->assistantMessage), 'visa');
                }
                $actionGate = $gate->evaluate($response, []);
                if ($response->actionKind() === 'SHADOW_FLIGHT_SEARCH' && $actionGate['allowed']) {
                    $ok = false;
                }
            } catch (\Throwable $e) {
                $ok = false;
                $results[] = ['id' => $case['id'], 'ok' => false, 'error' => $e->getMessage()];
                $failed++;
                continue;
            }

            $results[] = ['id' => $case['id'], 'ok' => $ok, 'status' => $response->status];
            $ok ? $passed++ : $failed++;
        }

        $summary = [
            'gateway_healthy' => true,
            'passed' => $passed,
            'failed' => $failed,
            'results' => $results,
        ];

        if ($this->option('json')) {
            $this->line(json_encode($summary, JSON_PRETTY_PRINT));
        } else {
            $this->info("Shadow certify: {$passed} passed, {$failed} failed");
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
