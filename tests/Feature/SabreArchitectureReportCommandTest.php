<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SabreArchitectureReportCommandTest extends TestCase
{
    /** @var list<string> */
    private const FORBIDDEN_OUTPUT_FRAGMENTS = [
        'password',
        'client_secret',
        'access_token',
        'client_secret',
        'SABRE_CERT_',
        'SABRE_6MD8',
    ];

    public function test_command_registered(): void
    {
        Artisan::call('list');
        $this->assertStringContainsString('sabre:architecture-report', Artisan::output());
    }

    public function test_command_runs_successfully_in_text_mode(): void
    {
        $exit = Artisan::call('sabre:architecture-report');

        $this->assertSame(0, $exit);
        $output = Artisan::output();
        $this->assertStringContainsString('Sabre architecture report', $output);
        $this->assertStringContainsString('Capability matrix', $output);
        $this->assertStringContainsString('Prod gap audit alignment', $output);
        $this->assertNoSecretLikeOutput($output);
    }

    public function test_text_output_shows_gds_cancel_as_implemented_env_gated(): void
    {
        Artisan::call('sabre:architecture-report');
        $output = Artisan::output();

        $this->assertStringContainsString('gds_cancel', $output);
        $this->assertStringContainsString('pending_cancel_retrieve_confirmation', $output);
        $this->assertStringContainsString('Evidence pending', $output);
        $this->assertStringNotContainsString('gds_cancel', $this->extractNotImplementedSection($output));
    }

    public function test_text_output_shows_ticketing_implemented_not_disabled(): void
    {
        Artisan::call('sabre:architecture-report');
        $output = Artisan::output();

        $this->assertStringContainsString('gds_ticketing', $output);
        $this->assertStringContainsString('sabre:gds-issue-ticket', $output);
        $this->assertStringNotContainsString('gds_ticketing', $this->extractNotImplementedSection($output));
    }

    public function test_text_output_includes_ndc_env_gated_posture(): void
    {
        Artisan::call('sabre:architecture-report');
        $output = Artisan::output();

        $this->assertStringContainsString('ndc_reprice', $output);
        $this->assertStringContainsString('ndc_order_retrieve', $output);
        $this->assertStringContainsString('NDC posture', $output);
        $this->assertStringContainsString('env_gated', $output);
    }

    public function test_text_output_includes_diagnostics_diagnostic_only(): void
    {
        Artisan::call('sabre:architecture-report');
        $output = Artisan::output();

        $this->assertStringContainsString('diagnostics', $output);
        $this->assertStringContainsString('not customer-facing', $output);
    }

    public function test_json_output_contains_lanes_and_capabilities(): void
    {
        Artisan::call('sabre:architecture-report', ['--json' => true]);
        $output = Artisan::output();
        $report = $this->decodeJsonOutput($output);

        $this->assertSame('sabre_architecture_report_v2', $report['report_version']);
        $this->assertArrayHasKey('lanes', $report);
        $this->assertArrayHasKey('gds_cancellation', $report['lanes']);
        $this->assertArrayHasKey('capabilities', $report);
        $this->assertSame('yes', $report['capabilities']['gds_cancel']['code_implemented']);
        $this->assertSame('yes', $report['capabilities']['gds_ticketing']['code_implemented']);
        $this->assertSame('pending_cancel_retrieve_confirmation', $report['capabilities']['gds_cancel']['evidence']);
        $this->assertSame('diagnostic_only', $report['capabilities']['diagnostics']['status']);
        $this->assertNotEmpty($report['production_critical_files']);
        $this->assertNotEmpty($report['evidence_pending_capabilities']);
        $this->assertArrayHasKey('prod_gap_audit', $report);
        $this->assertSame(0, $report['prod_gap_audit']['fail']);
        $this->assertNoSecretLikeOutput($output);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonOutput(string $output): array
    {
        $decoded = json_decode(trim($output), true);
        $this->assertIsArray($decoded);

        return $decoded;
    }

    private function extractNotImplementedSection(string $output): string
    {
        $marker = 'Not implemented (disabled)';
        $pos = strpos($output, $marker);

        return $pos === false ? '' : substr($output, $pos);
    }

    private function assertNoSecretLikeOutput(string $output): void
    {
        $lower = strtolower($output);
        foreach (self::FORBIDDEN_OUTPUT_FRAGMENTS as $fragment) {
            $this->assertStringNotContainsString(
                strtolower($fragment),
                $lower,
                "Output must not contain secret-like fragment: {$fragment}",
            );
        }
    }
}
