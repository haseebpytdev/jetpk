<?php

namespace Tests\Unit;

use App\Support\Sabre\Scenario\SabreGdsQrUnticketedBookAndRetrieveLifecycle;
use App\Support\Sabre\Scenario\SabreGdsQrUnticketedBookAndRetrieveRevalidationHandoff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class SabreGdsQrUnticketedBookAndRetrieveProductionReadinessPhaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['suppliers.sabre.ticketing_enabled' => false]);
    }

    public function test_command_is_registered(): void
    {
        $this->assertSame(0, Artisan::call('sabre:gds-qr-unticketed-book-and-retrieve', [
            '--departure-date' => '2026-09-05',
            '--passenger-json' => $this->privatePassengerFixturePath(),
            '--plan' => true,
        ]));
    }

    public function test_plan_mode_shows_operation_limits(): void
    {
        Artisan::call('sabre:gds-qr-unticketed-book-and-retrieve', [
            '--departure-date' => '2026-09-05',
            '--passenger-json' => $this->privatePassengerFixturePath(),
        ]);

        $output = Artisan::output();
        $this->assertStringContainsString('search_planned=true', $output);
        $this->assertStringContainsString('revalidation_planned=true', $output);
        $this->assertStringContainsString('pnr_create_planned=true', $output);
        $this->assertStringContainsString('retrieve_planned=true', $output);
        $this->assertStringContainsString('cancellation_planned=false', $output);
        $this->assertStringContainsString('ticketing_planned=false', $output);
        $this->assertStringContainsString('maximum_pnr_create_calls=1', $output);
        $this->assertStringContainsString('automatic_pnr_create_retry=false', $output);
    }

    public function test_send_refuses_when_ticketing_enabled(): void
    {
        config(['suppliers.sabre.ticketing_enabled' => true]);

        $lifecycle = app(SabreGdsQrUnticketedBookAndRetrieveLifecycle::class);
        $gate = $lifecycle->evaluateGate([
            'passenger_json' => $this->privatePassengerFixturePath(),
            'preset' => 'qr-connecting',
            'confirm_production' => SabreGdsQrUnticketedBookAndRetrieveLifecycle::CONFIRM_PRODUCTION,
            'confirm_pnr_create' => SabreGdsQrUnticketedBookAndRetrieveLifecycle::CONFIRM_PNR_CREATE,
            'confirm_no_ticketing' => SabreGdsQrUnticketedBookAndRetrieveLifecycle::CONFIRM_NO_TICKETING,
        ], true);

        $this->assertFalse($gate['allowed']);
        $this->assertContains('ticketing_enabled', $gate['reasons']);
    }

    public function test_send_refuses_without_all_confirmation_tokens(): void
    {
        $lifecycle = app(SabreGdsQrUnticketedBookAndRetrieveLifecycle::class);
        $gate = $lifecycle->evaluateGate([
            'passenger_json' => $this->privatePassengerFixturePath(),
            'preset' => 'qr-connecting',
        ], true);

        $this->assertFalse($gate['allowed']);
        $this->assertContains('confirm_production_missing', $gate['reasons']);
        $this->assertContains('confirm_pnr_create_missing', $gate['reasons']);
        $this->assertContains('confirm_no_ticketing_missing', $gate['reasons']);
    }

    public function test_fezjfp_reference_is_refused(): void
    {
        $lifecycle = app(SabreGdsQrUnticketedBookAndRetrieveLifecycle::class);
        $this->assertTrue($lifecycle->containsDeniedLocator(['notes' => 'FEZJFP']));
    }

    public function test_revalidation_handoff_requires_unique_usable_linkage(): void
    {
        $handoff = app(SabreGdsQrUnticketedBookAndRetrieveRevalidationHandoff::class);
        $this->assertFalse($handoff->allowsPnrCreate([
            'revalidation_success' => true,
            'freshness_satisfied' => true,
            'revalidation_diagnostics' => [
                'unique_usable_linkage_match_count' => 0,
                'ambiguous_linkage_match_count' => 0,
                'pricing_complete' => true,
                'fare_basis_complete' => true,
                'usable_fare_linkage' => true,
            ],
        ]));
    }

    public function test_revalidation_handoff_allows_production_shaped_evidence(): void
    {
        $handoff = app(SabreGdsQrUnticketedBookAndRetrieveRevalidationHandoff::class);
        $this->assertTrue($handoff->allowsPnrCreate([
            'revalidation_success' => true,
            'freshness_satisfied' => true,
            'revalidation_diagnostics' => [
                'unique_usable_linkage_match_count' => 1,
                'ambiguous_linkage_match_count' => 0,
                'pricing_complete' => true,
                'fare_basis_complete' => true,
                'usable_fare_linkage' => true,
            ],
        ]));
    }

    private function privatePassengerFixturePath(): string
    {
        $relative = 'private/sabre/private-passenger-phase12-test.json';
        $absolute = storage_path('app/'.$relative);
        if (! is_dir(dirname($absolute))) {
            mkdir(dirname($absolute), 0700, true);
        }
        file_put_contents($absolute, json_encode([
            'title' => 'MR',
            'given_name' => 'Test',
            'surname' => 'Passenger',
            'gender' => 'M',
            'dob' => '1990-01-01',
            'nationality' => 'PK',
            'country' => 'PK',
            'passport_number' => 'AB1234567',
            'passport_issue_date' => '2020-01-01',
            'passport_expiry_date' => '2030-01-01',
            'phone' => '+920000000000',
            'email' => 'phase12-test@example.invalid',
        ], JSON_THROW_ON_ERROR));
        @chmod($absolute, 0600);

        return $absolute;
    }
}
