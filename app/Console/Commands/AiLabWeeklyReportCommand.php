<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class AiLabWeeklyReportCommand extends Command
{
    protected $signature = 'ai:lab-weekly-report {--json : JSON output}';

    protected $description = 'Summarize redacted AI lab canary metrics and learning events';

    public function handle(): int
    {
        $metrics = $this->readJsonl('ai-lab/metrics.jsonl');
        $learning = $this->readJsonl('ai-lab/learning/events.jsonl');

        $failureClasses = [];
        foreach ($learning as $row) {
            $cat = (string) ($row['failure_category'] ?? 'OTHER');
            $failureClasses[$cat] = ($failureClasses[$cat] ?? 0) + 1;
        }

        arsort($failureClasses);

        $report = [
            'TOTAL_AI_CONVERSATIONS' => count(array_filter($metrics, fn ($r) => ($r['event'] ?? '') === 'turn_complete')),
            'SUCCESSFUL_COMPLETIONS' => count(array_filter($metrics, fn ($r) => ($r['status'] ?? '') === 'ok')),
            'CLARIFICATIONS' => count(array_filter($metrics, fn ($r) => ($r['status'] ?? '') === 'clarify')),
            'USER_CORRECTIONS' => count(array_filter($learning, fn ($r) => ($r['user_corrected_ai'] ?? false) === true)),
            'FALLBACKS' => count(array_filter($metrics, fn ($r) => ($r['event'] ?? '') === 'gateway_error')),
            'HANDOFFS' => count(array_filter($learning, fn ($r) => ($r['handoff_used'] ?? false) === true)),
            'FAILURE_EVENTS' => count($learning),
            'TOP_FAILURE_CLASSES' => array_slice($failureClasses, 0, 10, true),
            'GATEWAY_MS_SAMPLES' => array_values(array_filter(array_map(
                fn ($r) => $r['gateway_ms'] ?? null,
                $metrics
            ))),
        ];

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT));
        } else {
            foreach ($report as $k => $v) {
                $this->line($k.'='.(is_array($v) ? json_encode($v) : $v));
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readJsonl(string $path): array
    {
        if (! Storage::disk('local')->exists($path)) {
            return [];
        }

        $rows = [];
        foreach (explode("\n", Storage::disk('local')->get($path)) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $rows[] = $decoded;
            }
        }

        return $rows;
    }
}
