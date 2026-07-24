<?php

namespace Tests\Unit;

use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\SupplierConnection;
use App\Support\Sabre\Revalidation\SabreGdsRevalidationCanonicalSegmentSignature;
use App\Support\Sabre\Revalidation\SabreGdsRevalidationCanonicalSignatureRuntimePropagation;
use App\Support\Sabre\Revalidation\SabreGdsRevalidationResponseCandidateLinker;
use App\Support\Sabre\Revalidation\SabreGdsRevalidationSanitizedOutcomeContract;
use App\Support\Sabre\Scenario\SabreGdsLiveScenarioExactOfferEvidence;
use App\Support\Sabre\Scenario\SabreGdsLiveScenarioRevalidationOutcomeMapper;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SabreRevalidationOnlyProbeCanonicalDiagnosticsPersistenceCorrectionPhaseTest extends TestCase
{
    use RefreshDatabase;

    private const PERSISTED_KEY = SabreGdsRevalidationCanonicalSignatureRuntimePropagation::CANONICAL_LINKAGE_NORMALIZATION_DIAGNOSTICS_KEY;

    public function test_failed_linkage_probe_artifact_fields_include_canonical_block_via_mapper(): void
    {
        [$evidence, $canonical] = $this->buildEvidenceFromLinkageFixture(success: false);
        $mapper = app(SabreGdsLiveScenarioRevalidationOutcomeMapper::class);
        $artifactSlice = $mapper->extractScenarioResultFields($evidence);

        $this->assertFalse($artifactSlice['revalidation_success']);
        $this->assertCanonicalPersistenceOnArtifactSlice($artifactSlice, $canonical);
    }

    public function test_successful_linkage_probe_artifact_fields_include_canonical_block_via_mapper(): void
    {
        [$evidence, $canonical] = $this->buildEvidenceFromLinkageFixture(success: true);
        $mapper = app(SabreGdsLiveScenarioRevalidationOutcomeMapper::class);
        $artifactSlice = $mapper->extractScenarioResultFields($evidence);

        $this->assertTrue($artifactSlice['revalidation_success']);
        $this->assertCanonicalPersistenceOnArtifactSlice($artifactSlice, $canonical);
    }

    public function test_extract_scenario_result_fields_does_not_drop_digests_on_failure(): void
    {
        [$evidence] = $this->buildEvidenceFromLinkageFixture(success: false);
        $mapper = app(SabreGdsLiveScenarioRevalidationOutcomeMapper::class);
        $slice = $mapper->extractScenarioResultFields($evidence);

        $this->assertNotEmpty($slice['selected_segment_signature_digest']);
        $this->assertNotEmpty($slice['draft_segment_signature_digest']);
        $this->assertIsArray($slice['structurally_eligible_candidate_signature_digests']);
        $this->assertNotEmpty($slice['structurally_eligible_candidate_signature_digests']);
        $this->assertIsArray($slice['candidate_mismatch_categories']);
        $this->assertArrayHasKey(self::PERSISTED_KEY, $slice);
    }

    public function test_stored_signature_diagnostics_reads_probe_artifact_persisted_key(): void
    {
        Storage::fake('local');
        $runId = 'probe-canonical-persist-test-run';
        $block = [
            'canonical_signature_version' => SabreGdsRevalidationCanonicalSegmentSignature::VERSION,
            'selected_segment_signature_digest' => str_repeat('b', 64),
            'draft_segment_signature_digest' => str_repeat('c', 64),
            'selected_draft_signature_equal' => true,
            'structurally_eligible_candidate_signature_digests' => [str_repeat('d', 64)],
            'candidate_mismatch_categories' => ['segment_signature'],
            'fare_basis_applicability_match_count' => 0,
            'booking_class_compatibility_count' => 2,
        ];
        $artifact = [
            'run_id' => $runId,
            'revalidation_success' => false,
            self::PERSISTED_KEY => $block,
            'revalidation_diagnostics' => [
                self::PERSISTED_KEY => $block,
            ],
        ];
        Storage::disk('local')->put(
            'sabre-gds-revalidation-probes/'.$runId.'.json',
            json_encode($artifact, JSON_UNESCAPED_SLASHES),
        );

        Artisan::call('sabre:gds-scenario-revalidation-diagnostic', [
            '--stored-signature-diagnostics' => $runId,
        ]);
        $output = Artisan::output();

        $this->assertStringContainsString('mode=stored_signature_diagnostics_summary', $output);
        $this->assertStringContainsString('supplier_call_attempted=false', $output);
        $this->assertStringNotContainsString('legacy run or pre-propagation', $output);
        $this->assertStringContainsString('canonical_signature_diagnostics=', $output);
        $this->assertStringContainsString(str_repeat('b', 64), $output);
    }

    public function test_revalidation_diagnostics_nested_block_survives_evidence_extraction(): void
    {
        [$evidence, $canonical] = $this->buildEvidenceFromLinkageFixture(success: false);
        $diagnostics = $evidence['revalidation_diagnostics'] ?? null;
        $this->assertIsArray($diagnostics);
        $this->assertArrayHasKey(self::PERSISTED_KEY, $diagnostics);
        $this->assertSame(
            $canonical['selected_segment_signature_digest'],
            $diagnostics[self::PERSISTED_KEY]['selected_segment_signature_digest'] ?? null,
        );
    }

    /**
     * @param  array<string, mixed>  $artifactSlice
     * @param  array<string, mixed>  $canonical
     */
    protected function assertCanonicalPersistenceOnArtifactSlice(array $artifactSlice, array $canonical): void
    {
        $this->assertArrayHasKey(self::PERSISTED_KEY, $artifactSlice);
        $this->assertSame(
            $canonical['selected_segment_signature_digest'],
            $artifactSlice[self::PERSISTED_KEY]['selected_segment_signature_digest'] ?? null,
        );
        $this->assertSame(SabreGdsRevalidationCanonicalSegmentSignature::VERSION, $artifactSlice['canonical_signature_version'] ?? null);
        $this->assertNotEmpty($artifactSlice['fare_basis_presence_by_candidate'] ?? null);
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    protected function buildEvidenceFromLinkageFixture(bool $success): array
    {
        $fixturePath = base_path('tests/Fixtures/sabre/revalidation/http-200-informational-warning-31-candidates-linkage.json');
        $decoded = json_decode((string) file_get_contents($fixturePath), true);
        $conn = $this->seedSabreConnection();
        $snap = $decoded['shop_snap'] ?? $this->defaultShopSnap();
        $row = $decoded['shop_row'] ?? $this->defaultShopRow();
        $linkage = app(SabreGdsLiveScenarioExactOfferEvidence::class)->buildLinkageContext($conn, $snap, $row);
        $draft = $decoded['api_draft'];
        $draft = app(SabreGdsRevalidationCanonicalSignatureRuntimePropagation::class)->attachRuntimeToDraft(
            $draft,
            $conn,
            $snap,
            $row,
            null,
            $linkage,
        );
        $linker = app(SabreGdsRevalidationResponseCandidateLinker::class);
        $selectedContext = $linker->buildSelectedContextFromDraft($draft);
        $analysis = $linker->analyze($decoded['response'], $selectedContext, 31);
        if (! $success) {
            $analysis['exact_segment_signature_match_count'] = 0;
            $analysis['unique_usable_linkage_match_count'] = 0;
            $analysis['usable_fare_linkage'] = false;
            $analysis['linkage_failure_reason_code'] = 'no_exact_segment_signature_match';
        }
        $canonical = app(SabreGdsRevalidationCanonicalSignatureRuntimePropagation::class)->postResponseDiagnostics(
            $draft,
            $selectedContext,
            $analysis,
            $decoded['response'],
        );
        $outcome = SabreGdsRevalidationSanitizedOutcomeContract::wrap(array_merge([
            'success' => $success,
            'http_status' => 200,
            'usable_fare_linkage' => $success,
            'response_linkage_diagnostics' => array_merge($analysis, [
                'canonical_linkage_normalization' => $canonical,
            ]),
            'canonical_linkage_normalization' => $canonical,
            'revalidation_canonical_linkage_normalization' => $canonical,
        ], $success ? [] : [
            'reason_code' => 'sabre_revalidation_fare_linkage_missing',
            'linkage_failure_reason_code' => 'no_exact_segment_signature_match',
        ]), true, true);

        $evidence = app(SabreGdsLiveScenarioRevalidationOutcomeMapper::class)->mapToScenarioEvidence($outcome, [
            'selected_total' => 520.83,
            'selected_currency' => 'USD',
        ]);

        return [$evidence, $canonical];
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultShopSnap(): array
    {
        return [
            'validating_carrier' => 'QR',
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'DOH',
                    'carrier' => 'QR',
                    'operating_carrier' => 'QR',
                    'flight_number' => '615',
                    'departure_at' => '2026-09-01T02:15:00',
                    'arrival_at' => '2026-09-01T04:30:00',
                ],
            ],
            'raw_payload' => ['sabre_shop_context' => ['itinerary_ref' => '2', 'pricing_information_index' => 0]],
            'sabre_booking_context' => [
                'booking_classes_by_segment' => ['S'],
                'fare_basis_codes_by_segment' => ['SLOW1'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultShopRow(): array
    {
        return [
            'validating_carrier' => 'QR',
            'segment_count' => 1,
            'total_fare' => 569.73,
            'currency' => 'USD',
            'booking_classes_by_segment' => ['S'],
            'fare_basis_codes_by_segment' => ['SLOW1'],
        ];
    }

    protected function seedSabreConnection(): SupplierConnection
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();

        return SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', SupplierProvider::Sabre)
            ->firstOrFail();
    }
}
