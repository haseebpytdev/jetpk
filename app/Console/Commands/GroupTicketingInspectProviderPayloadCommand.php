<?php

namespace App\Console\Commands;

use App\Services\Suppliers\AlHaider\AlHaiderClient;
use App\Services\Suppliers\AlHaider\AlHaiderPackageNormalizer;
use App\Support\GroupTicketing\AlHaiderProviderPayloadInspector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GroupTicketingInspectProviderPayloadCommand extends Command
{
    protected $signature = 'group-ticketing:inspect-provider-payload {--limit=5 : Number of sample records to summarize}';

    protected $description = 'Read-only CLI summary of Al-Haider group inventory payload fields (sanitized)';

    public function handle(AlHaiderClient $client, AlHaiderPackageNormalizer $normalizer): int
    {
        $limit = max(1, min(20, (int) $this->option('limit')));
        $enabled = (bool) config('suppliers.al_haider.enabled');
        $configured = $client->isConfigured();

        $this->line('');
        $this->info('Al-Haider group inventory payload inspection (read-only)');
        $this->line('API enabled: '.($enabled ? 'yes' : 'no'));
        $this->line('Credentials configured: '.($configured ? 'yes' : 'no'));

        if (! $enabled) {
            $this->warn('Al-Haider API is disabled. Set ALHAIDER_API_ENABLED=true to fetch live payload.');

            return self::SUCCESS;
        }

        if (! $configured) {
            $this->warn('Al-Haider credentials are not configured.');

            return self::SUCCESS;
        }

        try {
            $response = $client->listGroups([]);
        } catch (\Throwable $exception) {
            $this->error('Provider request failed: '.$exception->getMessage());
            Log::warning('group_ticketing_provider_payload_inspect_failed', [
                'message' => $exception->getMessage(),
            ]);

            return self::FAILURE;
        }

        $rawGroups = $response['groups'] ?? [];
        if (! is_array($rawGroups)) {
            $rawGroups = [];
        }

        $rows = [];
        foreach ($rawGroups as $row) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }

        $this->line('Records returned: '.count($rows));

        if ($rows === []) {
            $this->warn('No group records in provider response.');

            return self::SUCCESS;
        }

        $inspector = new AlHaiderProviderPayloadInspector($normalizer);
        $report = $inspector->inspect($rows, $limit);

        $this->line('');
        $this->info('Top-level keys (sample of '.$limit.' row(s)):');
        foreach ($report['top_level_keys'] as $key => $count) {
            $this->line("  - {$key}: present in {$count}/{$limit} sample row(s)");
        }

        $this->line('');
        $this->info('Available provider fields:');
        foreach ($report['field_matrix'] as $field => $info) {
            $status = ($info['present'] ?? false) ? 'yes' : 'no';
            $example = $info['example'] ?? null;
            $suffix = $example !== null ? ' / example: '.$example : '';
            $this->line("  * {$field}: {$status}{$suffix}");
        }

        if ($report['missing_expected'] !== []) {
            $this->line('');
            $this->warn('Expected normalizer keys missing from sample top-level rows:');
            foreach ($report['missing_expected'] as $missing) {
                $this->line('  - '.$missing);
            }
        }

        $this->line('');
        $this->info('Sample normalized records:');
        foreach ($report['normalized_samples'] as $index => $sample) {
            $this->line('  ['.($index + 1).'] '.json_encode($sample, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        }

        Log::info('group_ticketing_provider_payload_inspected', [
            'record_count' => count($rows),
            'sample_limit' => $limit,
            'fields_detected' => count(array_filter(
                $report['field_matrix'],
                static fn (array $info): bool => (bool) ($info['present'] ?? false),
            )),
        ]);

        $this->line('');

        return self::SUCCESS;
    }
}
