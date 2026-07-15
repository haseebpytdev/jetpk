<?php

namespace Tests\Unit\Console\Commands;

use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\PiaNdc\PiaNdcCancelExecutionLock;
use App\Services\Suppliers\PiaNdc\PiaNdcXmlBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PiaNdcCancelEvidenceReportCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancel_evidence_report_does_not_call_supplier(): void
    {
        $connection = $this->piaConnection();
        $this->seedDiagnosticSummaries($connection->id, '9FCPZ3');

        Http::fake();

        $this->artisan('pia-ndc:cancel-evidence-report', [
            '--connection' => $connection->id,
            '--order-id' => '9FCPZ3',
            '--owner-code' => 'PK',
        ])
            ->expectsOutputToContain('supplier_called=false')
            ->expectsOutputToContain('success=true')
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_cancel_evidence_report_generates_support_message_and_masks_pii(): void
    {
        $connection = $this->piaConnection();
        $this->seedDiagnosticSummaries($connection->id, '9FCPZ3', includePii: true);

        $outputRoot = storage_path('app/diagnostics/pia-ndc/cancel-evidence-test/'.$connection->id);
        File::deleteDirectory($outputRoot);

        $this->artisan('pia-ndc:cancel-evidence-report', [
            '--connection' => $connection->id,
            '--order-id' => '9FCPZ3',
            '--output' => $outputRoot.'/9FCPZ3/report-run',
        ])->assertSuccessful();

        $supportPath = $outputRoot.'/9FCPZ3/report-run/support_message.txt';
        $this->assertFileExists($supportPath);
        $supportText = file_get_contents($supportPath) ?: '';
        $this->assertStringContainsString('Please confirm doOrderCancelPreview', $supportText);
        $this->assertStringContainsString('9FCPZ3', $supportText);
        $this->assertStringContainsString('java.lang.NullPointerException', $supportText);
        $this->assertStringContainsString('DoOrderCancel_VIEW_ONLY_req.txt', $supportText);
        $this->assertStringContainsString('DoOrderCancel_COMMIT_req.txt', $supportText);
        $this->assertStringContainsString('Section 3.3', $supportText);
        $this->assertStringContainsString('doOrderCancelPreview', $supportText);
        $this->assertStringContainsString('doOrderCancelCommit', $supportText);
        $this->assertStringNotContainsString('JOHN DOE', $supportText);
        $this->assertStringNotContainsString('passport@secret.test', $supportText);

        $report = json_decode(file_get_contents($outputRoot.'/9FCPZ3/report-run/report.json') ?: '', true);
        $this->assertIsArray($report);
        $this->assertFalse($report['supplier_called'] ?? true);
        $this->assertSame('3.3', $report['hitit_manual_references']['section'] ?? null);
        $this->assertContains('DoOrderCancel_COMMIT_req.txt', $report['hitit_manual_references']['manual_sample_filenames'] ?? []);
        $samples = $report['official_cancel_sample_files'] ?? [];
        $this->assertCount(4, $samples);
        $this->assertSame('implemented_from_official_zip', $report['sample_alignment']['builder_alignment'] ?? null);
        $presence = (string) ($report['sample_alignment']['sample_files_physical_presence'] ?? '');
        $this->assertContains($presence, ['not_deployed_on_server', 'deployed_on_server']);
        if ($presence === 'not_deployed_on_server') {
            $this->assertStringContainsString('not stored on the production server', $supportText);
            $this->assertStringContainsString('builder_alignment=implemented_from_official_zip', $supportText);
        }
        $this->assertStringContainsString('doOrderCancelPreview_OW_req.xml', $supportText);
        $this->assertStringContainsString('doOrderCancelCommit_OW_req.xml', $supportText);
        $encoded = json_encode($report);
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('JOHN DOE', $encoded);
        $this->assertStringNotContainsString('passport@secret.test', $encoded);

        File::deleteDirectory($outputRoot);
        $this->cleanupSeedDiagnostics($connection->id);
    }

    public function test_hitit_manual_shapes_blocked_on_execute(): void
    {
        $connection = $this->piaConnection();

        $this->artisan('pia-ndc:order-cancel-diagnostic', [
            '--connection' => $connection->id,
            '--order-id' => '9FCPZ3',
            '--execute-cancel' => true,
            '--confirm' => 'PREVIEW_OPTION_PNR',
            '--shape' => 'hitit_cancel_commit_sample_exact',
        ])
            ->expectsOutputToContain('dry-run/probe only')
            ->assertFailed();

        foreach (['hitit_manual_view_only_exact'] as $shape) {
            $this->artisan('pia-ndc:order-cancel-diagnostic', [
                '--connection' => $connection->id,
                '--order-id' => '9FCPZ3',
                '--execute-cancel' => true,
                '--confirm' => 'PREVIEW_OPTION_PNR',
                '--shape' => $shape,
            ])
                ->expectsOutputToContain('Live execute is limited to:')
                ->assertFailed();
        }

        $this->artisan('pia-ndc:order-cancel-diagnostic', [
            '--connection' => $connection->id,
            '--order-id' => '9FCPZ3',
            '--execute-cancel' => true,
            '--confirm' => 'PREVIEW_OPTION_PNR',
            '--shape' => 'hitit_manual_commit_exact',
        ])
            ->expectsOutputToContain('doOrderCancelCommit execution is blocked')
            ->assertFailed();
    }

    public function test_preview_sample_exact_requires_preview_confirm_phrase(): void
    {
        $connection = $this->piaConnection();

        $this->artisan('pia-ndc:order-cancel-diagnostic', [
            '--connection' => $connection->id,
            '--order-id' => '9FCPZ3',
            '--execute-cancel' => true,
            '--confirm' => 'CANCEL_OPTION_PNR',
            '--shape' => 'hitit_cancel_preview_sample_exact',
        ])
            ->expectsOutputToContain('PREVIEW_OPTION_PNR')
            ->assertFailed();
    }

    public function test_commit_sample_exact_blocked_even_with_cancel_confirm(): void
    {
        $connection = $this->piaConnection();

        $this->artisan('pia-ndc:order-cancel-diagnostic', [
            '--connection' => $connection->id,
            '--order-id' => '9FCPZ3',
            '--execute-cancel' => true,
            '--confirm' => 'CANCEL_OPTION_PNR',
            '--shape' => 'hitit_cancel_commit_sample_exact',
        ])
            ->expectsOutputToContain('dry-run/probe only')
            ->assertFailed();
    }

    public function test_hitit_manual_shapes_have_no_payment_or_ticketing_tags(): void
    {
        $builder = new PiaNdcXmlBuilder;
        $config = [
            'agency_id' => 'SELENS',
            'agency_name' => 'NDC GATEWAY',
            'currency' => 'PKR',
            'language_code' => 'EN',
            'owner_code' => 'PK',
        ];

        $previewXml = $builder->buildCancelDiagnosticRequest($config, '9FCPZ3', 'PK', 'hitit_manual_view_only_exact');
        $commitXml = $builder->buildCancelDiagnosticRequest($config, '9FCPZ3', 'PK', 'hitit_manual_commit_exact');

        foreach ([$previewXml, $commitXml] as $xml) {
            $this->assertStringNotContainsString('PaymentFunctions', $xml);
            $this->assertStringNotContainsString('AccountableDoc', $xml);
            $this->assertStringNotContainsString('MCO', $xml);
        }

        $this->assertStringContainsString('IATA_OrderReshopRQ', $previewXml);
        $this->assertStringContainsString('UpdateOrder', $previewXml);
        $this->assertStringContainsString('IATA_OrderChangeRQ', $commitXml);
        $this->assertStringContainsString('ChangeOrder', $commitXml);
    }

    public function test_hitit_exact_from_sample_v2_blocked_on_execute(): void
    {
        $connection = $this->piaConnection();

        $this->artisan('pia-ndc:order-cancel-diagnostic', [
            '--connection' => $connection->id,
            '--order-id' => '9FCPZ3',
            '--execute-cancel' => true,
            '--confirm' => 'PREVIEW_OPTION_PNR',
            '--shape' => 'hitit_exact_from_sample_v2',
        ])
            ->expectsOutputToContain('doOrderCancelCommit execution is blocked')
            ->assertFailed();
    }

    public function test_preview_and_commit_locks_are_separate(): void
    {
        $root = storage_path('app/diagnostics/pia-ndc/lock-test-'.uniqid('', true));
        $lock = new PiaNdcCancelExecutionLock($root);

        $previewPath = $lock->acquire('preview', 1, '9FCPZ3', 'PK');
        $commitPath = $lock->acquire('commit', 1, '9FCPZ3', 'PK');

        $this->assertNotSame($previewPath, $commitPath);
        $this->assertFileExists($previewPath);
        $this->assertFileExists($commitPath);

        File::deleteDirectory($root);
    }

    public function test_stale_locks_expire_and_clear(): void
    {
        $root = storage_path('app/diagnostics/pia-ndc/lock-test-'.uniqid('', true));
        $lock = new PiaNdcCancelExecutionLock($root);
        $path = $lock->lockPath('commit', 19, '9FCPZ3', 'PK');
        File::ensureDirectoryExists(dirname($path));
        file_put_contents($path, '{"kind":"commit"}');
        touch($path, time() - ((PiaNdcCancelExecutionLock::TTL_MINUTES + 1) * 60));

        $this->assertTrue($lock->isStale($path));
        $removed = $lock->clearStaleLocks(19);
        $this->assertContains($path, $removed);
        $this->assertFileDoesNotExist($path);

        File::deleteDirectory($root);
    }

    public function test_failed_commit_lock_does_not_block_preview_after_ttl(): void
    {
        $root = storage_path('app/diagnostics/pia-ndc/lock-test-'.uniqid('', true));
        $lock = new PiaNdcCancelExecutionLock($root);
        $commitPath = $lock->lockPath('commit', 19, '9FCPZ3', 'PK');
        File::ensureDirectoryExists(dirname($commitPath));
        file_put_contents($commitPath, '{"kind":"commit"}');
        touch($commitPath, time() - ((PiaNdcCancelExecutionLock::TTL_MINUTES + 1) * 60));

        $previewPath = $lock->acquire('preview', 19, '9FCPZ3', 'PK');
        $this->assertFileExists($previewPath);

        File::deleteDirectory($root);
    }

    public function test_hitit_exact_shape_builds_without_payment_tags(): void
    {
        $builder = new PiaNdcXmlBuilder;
        $xml = $builder->buildCancelDiagnosticRequest([
            'agency_id' => 'SELENS',
            'agency_name' => 'NDC GATEWAY',
            'currency' => 'PKR',
            'language_code' => 'EN',
            'owner_code' => 'PK',
        ], '9FCPZ3', 'PK', 'hitit_exact_from_sample_v2');

        $this->assertStringContainsString('IATA_OrderChangeRQ', $xml);
        $this->assertStringContainsString('CancelOrder', $xml);
        $this->assertStringNotContainsString('PaymentFunctions', $xml);
        $this->assertStringNotContainsString('AccountableDoc', $xml);
    }

    public function test_support_message_excludes_dry_run_probe_and_legacy_false_cancelled(): void
    {
        $connection = $this->piaConnection();
        $this->seedDiagnosticSummaries($connection->id, '9FCPZ3');
        $this->seedProbeAndLegacyDiagnostics($connection->id, '9FCPZ3');

        $outputRoot = storage_path('app/diagnostics/pia-ndc/cancel-evidence-filter-test/'.$connection->id);
        File::deleteDirectory($outputRoot);

        $this->artisan('pia-ndc:cancel-evidence-report', [
            '--connection' => $connection->id,
            '--order-id' => '9FCPZ3',
            '--output' => $outputRoot.'/9FCPZ3/report-run',
        ])->assertSuccessful();

        $supportText = file_get_contents($outputRoot.'/9FCPZ3/report-run/support_message.txt') ?: '';
        $report = json_decode(file_get_contents($outputRoot.'/9FCPZ3/report-run/report.json') ?: '', true);

        $this->assertStringNotContainsString('probe_shape_variant', $supportText);
        $this->assertStringNotContainsString('cancellation_status=cancelled', $supportText);
        $this->assertStringContainsString('shape=current_order_change_cancel_order', $supportText);

        $legacyRows = array_values(array_filter(
            is_array($report['cancel_attempts'] ?? null) ? $report['cancel_attempts'] : [],
            fn ($row) => is_array($row) && ($row['legacy_known_bad_diagnostic'] ?? false) === true,
        ));
        $this->assertNotEmpty($legacyRows);
        $this->assertTrue($legacyRows[0]['ignored_for_support_message'] ?? false);

        File::deleteDirectory($outputRoot);
        $this->cleanupSeedDiagnostics($connection->id);
    }

    public function test_preview_variants_cannot_run_with_cancel_option_pnr(): void
    {
        $connection = $this->piaConnection();

        foreach ([
            'hitit_cancel_preview_sample_exact',
            'hitit_cancel_preview_sample_exact_with_contact_info',
            'hitit_cancel_preview_sample_exact_with_orderref_owner_attr',
        ] as $shape) {
            $this->artisan('pia-ndc:order-cancel-diagnostic', [
                '--connection' => $connection->id,
                '--order-id' => '9FCPZ3',
                '--execute-cancel' => true,
                '--confirm' => 'CANCEL_OPTION_PNR',
                '--shape' => $shape,
            ])
                ->expectsOutputToContain('PREVIEW_OPTION_PNR')
                ->assertFailed();
        }
    }

    public function test_support_message_documents_r11f_preview_variants(): void
    {
        $connection = $this->piaConnection();
        $this->seedDiagnosticSummaries($connection->id, '9FCPZ3');

        $outputRoot = storage_path('app/diagnostics/pia-ndc/cancel-evidence-r11f/'.$connection->id);
        File::deleteDirectory($outputRoot);

        $this->artisan('pia-ndc:cancel-evidence-report', [
            '--connection' => $connection->id,
            '--order-id' => '9FCPZ3',
            '--output' => $outputRoot.'/9FCPZ3/report-run',
        ])->assertSuccessful();

        $supportText = file_get_contents($outputRoot.'/9FCPZ3/report-run/support_message.txt') ?: '';
        $this->assertStringContainsString('R11F preview variants', $supportText);
        $this->assertStringContainsString('hitit_cancel_preview_sample_exact_with_contact_info', $supportText);
        $this->assertStringContainsString('hitit_cancel_preview_sample_exact_with_orderref_owner_attr', $supportText);
        $this->assertStringContainsString('doOrderCancelCommit was not attempted', $supportText);
        $this->assertStringContainsString('supplier_called=false', $supportText);

        File::deleteDirectory($outputRoot);
        $this->cleanupSeedDiagnostics($connection->id);
    }

    public function test_preview_live_execute_uses_preview_lock_and_preview_status(): void
    {
        $connection = $this->piaConnection();
        $diagRoot = storage_path('app/diagnostics/pia-ndc/order-cancel/'.$connection->id);
        File::deleteDirectory($diagRoot);
        $this->clearCancelExecutionLocks();

        $retrieveXml = file_get_contents(base_path('tests/Fixtures/pia-ndc/doOrderRetrieve_OW_res.xml'));
        $previewXml = file_get_contents(base_path('tests/Fixtures/pia-ndc/doOrderCancelPreview_OW_res.xml'));

        Http::fake([
            'example.test/*' => Http::sequence()
                ->push($retrieveXml, 200, ['Content-Type' => 'text/xml; charset=utf-8'])
                ->push($previewXml, 200, ['Content-Type' => 'text/xml; charset=utf-8']),
        ]);

        $this->artisan('pia-ndc:order-cancel-diagnostic', [
            '--connection' => $connection->id,
            '--order-id' => '9FCPZ3',
            '--owner-code' => 'PK',
            '--execute-cancel' => true,
            '--confirm' => 'PREVIEW_OPTION_PNR',
            '--shape' => 'hitit_cancel_preview_sample_exact',
        ])
            ->expectsOutputToContain('success=true')
            ->expectsOutputToContain('cancel_preview_status=preview')
            ->expectsOutputToContain('supplier_called=true')
            ->assertSuccessful();

        $folders = File::directories($diagRoot);
        $summary = json_decode(file_get_contents($folders[0].'/summary.json') ?: '', true);
        $this->assertIsArray($summary);
        $this->assertArrayNotHasKey('cancellation_status', $summary);
        $this->assertSame('preview', $summary['cancel_preview_status'] ?? null);
        $this->assertStringContainsString('order-cancel-preview-locks', (string) ($summary['execution_lock_path'] ?? ''));

        File::deleteDirectory($diagRoot);
        $this->clearCancelExecutionLocks();
    }

    private function seedProbeAndLegacyDiagnostics(int $connectionId, string $orderId): void
    {
        $probeDir = storage_path('app/diagnostics/pia-ndc/order-cancel-probe/'.$connectionId.'/probe-1/hitit_cancel_preview_sample_exact');
        File::ensureDirectoryExists($probeDir);
        file_put_contents($probeDir.'/summary.json', json_encode([
            'order_id' => $orderId,
            'shape' => 'hitit_cancel_preview_sample_exact',
            'operation' => 'cancel_preview',
            'dry_run' => true,
            'supplier_called' => false,
            'http_status' => 500,
            'soap_fault_string' => 'java.lang.NullPointerException',
            'cancellation_status' => 'failed',
        ], JSON_UNESCAPED_SLASHES));

        $legacyDir = storage_path('app/diagnostics/pia-ndc/order-cancel/'.$connectionId.'/legacy-bad-1');
        File::ensureDirectoryExists($legacyDir);
        file_put_contents($legacyDir.'/summary.json', json_encode([
            'order_id' => $orderId,
            'shape' => 'current_order_change_cancel_order',
            'operation' => 'cancel_commit',
            'dry_run' => false,
            'supplier_called' => true,
            'http_status' => 500,
            'soap_fault_string' => 'java.lang.NullPointerException',
            'cancellation_status' => 'cancelled',
        ], JSON_UNESCAPED_SLASHES));

        $liveFailedDir = storage_path('app/diagnostics/pia-ndc/order-cancel/'.$connectionId.'/live-failed-1');
        File::ensureDirectoryExists($liveFailedDir);
        file_put_contents($liveFailedDir.'/summary.json', json_encode([
            'order_id' => $orderId,
            'shape' => 'current_order_change_cancel_order',
            'operation' => 'cancel_commit',
            'dry_run' => false,
            'supplier_called' => true,
            'http_status' => 500,
            'soap_fault_string' => 'java.lang.NullPointerException',
            'cancellation_status' => 'failed',
        ], JSON_UNESCAPED_SLASHES));
    }

    private function clearCancelExecutionLocks(): void
    {
        foreach ([
            'order-cancel-commit-locks',
            'order-cancel-preview-locks',
            'order-cancel-locks',
        ] as $subdir) {
            File::deleteDirectory(storage_path('app/diagnostics/pia-ndc/'.$subdir));
        }
    }

    private function seedDiagnosticSummaries(int $connectionId, string $orderId, bool $includePii = false): void
    {
        $retrieveDir = storage_path('app/diagnostics/pia-ndc/order-retrieve/'.$connectionId.'/evidence-retrieve-1');
        File::ensureDirectoryExists($retrieveDir);
        file_put_contents($retrieveDir.'/summary.json', json_encode([
            'order_id' => $orderId,
            'http_status' => 200,
            'success' => true,
            'segment_count' => 1,
            'ticket_numbers' => [],
            'has_blocking_ticket_numbers' => false,
            'payment_time_limit' => '2026-06-27T00:18:06+05:00',
            'passenger_name' => $includePii ? 'JOHN DOE' : null,
        ], JSON_UNESCAPED_SLASHES));

        $cancelDir = storage_path('app/diagnostics/pia-ndc/order-cancel/'.$connectionId.'/evidence-cancel-1');
        File::ensureDirectoryExists($cancelDir);
        file_put_contents($cancelDir.'/summary.json', json_encode([
            'order_id' => $orderId,
            'shape' => 'current_order_change_cancel_order',
            'operation' => 'cancel_commit',
            'soap_action' => 'doOrderCancelCommit',
            'dry_run' => false,
            'supplier_called' => true,
            'http_status' => 500,
            'success' => false,
            'provider_error_code' => 'supplier_http_error',
            'soap_fault_code' => 'S:Server',
            'soap_fault_string' => 'java.lang.NullPointerException',
            'cancellation_status' => 'failed',
            'contact_email' => $includePii ? 'passport@secret.test' : null,
        ], JSON_UNESCAPED_SLASHES));
        file_put_contents($cancelDir.'/request.xml', '<IATA_OrderChangeRQ><Request><CancelOrder/></Request></IATA_OrderChangeRQ>');
    }

    private function cleanupSeedDiagnostics(int $connectionId): void
    {
        File::deleteDirectory(storage_path('app/diagnostics/pia-ndc/order-retrieve/'.$connectionId));
        File::deleteDirectory(storage_path('app/diagnostics/pia-ndc/order-cancel/'.$connectionId));
        File::deleteDirectory(storage_path('app/diagnostics/pia-ndc/order-cancel-probe/'.$connectionId));
    }

    private function piaConnection(): SupplierConnection
    {
        return SupplierConnection::factory()->create([
            'provider' => SupplierProvider::PiaNdc,
            'base_url' => 'https://example.test/cranendc/v20.1/CraneNDCService',
            'credentials' => [
                'username' => 'test-user',
                'password' => 'test-pass',
                'agency_id' => '187570',
                'agency_name' => 'NDC GATEWAY',
                'owner_code' => 'PK',
                'currency' => 'PKR',
                'language_code' => 'EN',
            ],
            'is_active' => true,
        ]);
    }
}
