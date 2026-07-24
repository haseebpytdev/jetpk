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
use Tests\TestCase;

class SabreRevalidationCanonicalSignatureRuntimePropagationCorrectionPhaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_runtime_attachment_makes_selected_and_draft_signatures_equal_before_http(): void
    {
        $conn = $this->seedSabreConnection();
        $snap = $this->shopSnap();
        $row = $this->shopRow();
        $linkage = app(SabreGdsLiveScenarioExactOfferEvidence::class)->buildLinkageContext($conn, $snap, $row);
        $draft = $this->draftFromSnap($snap);
        $draft = app(SabreGdsRevalidationCanonicalSignatureRuntimePropagation::class)->attachRuntimeToDraft(
            $draft,
            $conn,
            $snap,
            $row,
            null,
            $linkage,
        );

        $pre = app(SabreGdsRevalidationCanonicalSignatureRuntimePropagation::class)->preSupplierHttpDiagnostics($draft);
        $this->assertSame(SabreGdsRevalidationCanonicalSegmentSignature::VERSION, $pre['canonical_signature_version']);
        $this->assertTrue($pre['selected_draft_signature_equal']);
        $this->assertSame($pre['selected_segment_signature_digest'], $pre['draft_segment_signature_digest']);
    }

    public function test_linker_uses_canonical_runtime_rows_not_legacy_draft_only_signature(): void
    {
        $conn = $this->seedSabreConnection();
        $snap = $this->shopSnap();
        $row = $this->shopRow();
        $linkage = app(SabreGdsLiveScenarioExactOfferEvidence::class)->buildLinkageContext($conn, $snap, $row);
        $draft = $this->draftFromSnap($snap);
        $draft['segments'][0]['flight_number'] = '0615';
        $draft = app(SabreGdsRevalidationCanonicalSignatureRuntimePropagation::class)->attachRuntimeToDraft(
            $draft,
            $conn,
            $snap,
            $row,
            null,
            $linkage,
        );

        $context = app(SabreGdsRevalidationResponseCandidateLinker::class)->buildSelectedContextFromDraft($draft);
        $this->assertSame($linkage['segment_signature'], $context['segment_signature']);
    }

    public function test_post_response_diagnostics_survive_mapper_and_contract(): void
    {
        $fixturePath = base_path('tests/Fixtures/sabre/revalidation/http-200-informational-warning-31-candidates-linkage.json');
        $decoded = json_decode((string) file_get_contents($fixturePath), true);
        $draft = $decoded['api_draft'];
        $conn = $this->seedSabreConnection();
        $draft = app(SabreGdsRevalidationCanonicalSignatureRuntimePropagation::class)->attachRuntimeToDraft(
            $draft,
            $conn,
            $this->shopSnap(),
            $this->shopRow(),
            null,
            app(SabreGdsLiveScenarioExactOfferEvidence::class)->buildLinkageContext($conn, $this->shopSnap(), $this->shopRow()),
        );
        $linker = app(SabreGdsRevalidationResponseCandidateLinker::class);
        $selectedContext = $linker->buildSelectedContextFromDraft($draft);
        $analysis = $linker->analyze($decoded['response'], $selectedContext, 31);
        $canonical = app(SabreGdsRevalidationCanonicalSignatureRuntimePropagation::class)->postResponseDiagnostics(
            $draft,
            $selectedContext,
            $analysis,
            $decoded['response'],
        );

        $outcome = SabreGdsRevalidationSanitizedOutcomeContract::wrap([
            'success' => true,
            'http_status' => 200,
            'response_linkage_diagnostics' => array_merge($analysis, [
                'canonical_linkage_normalization' => $canonical,
            ]),
            'canonical_linkage_normalization' => $canonical,
        ], true, true);

        $evidence = app(SabreGdsLiveScenarioRevalidationOutcomeMapper::class)->mapToScenarioEvidence($outcome, [
            'selected_total' => 520.83,
            'selected_currency' => 'USD',
        ]);

        $this->assertSame(SabreGdsRevalidationCanonicalSegmentSignature::VERSION, $evidence['canonical_signature_version']);
        $this->assertNotEmpty($evidence['selected_segment_signature_digest']);
        $this->assertNotEmpty($evidence['structurally_eligible_candidate_signature_digests']);
        $this->assertIsArray($evidence['canonical_linkage_normalization']);
    }

    public function test_stale_legacy_signature_on_draft_does_not_override_canonical_runtime_digest(): void
    {
        $conn = $this->seedSabreConnection();
        $snap = $this->shopSnap();
        $row = $this->shopRow();
        $linkage = app(SabreGdsLiveScenarioExactOfferEvidence::class)->buildLinkageContext($conn, $snap, $row);
        $draft = $this->draftFromSnap($snap);
        $draft = app(SabreGdsRevalidationCanonicalSignatureRuntimePropagation::class)->attachRuntimeToDraft(
            $draft,
            $conn,
            $snap,
            $row,
            null,
            $linkage,
        );
        $draft[SabreGdsRevalidationCanonicalSignatureRuntimePropagation::RUNTIME_DRAFT_KEY]['selected_segment_signature_digest'] = str_repeat('a', 64);

        $context = app(SabreGdsRevalidationResponseCandidateLinker::class)->buildSelectedContextFromDraft($draft);
        $this->assertSame($linkage['segment_signature'], $context['segment_signature']);
        $this->assertNotSame(str_repeat('a', 64), $context['segment_signature']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function shopSnap(): array
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
                [
                    'origin' => 'DOH',
                    'destination' => 'JED',
                    'carrier' => 'QR',
                    'operating_carrier' => 'QR',
                    'flight_number' => '1184',
                    'departure_at' => '2026-09-01T06:00:00',
                    'arrival_at' => '2026-09-01T08:15:00',
                ],
            ],
            'raw_payload' => [
                'sabre_shop_context' => ['itinerary_ref' => '2', 'pricing_information_index' => 0],
            ],
            'sabre_booking_context' => [
                'booking_classes_by_segment' => ['S', 'S'],
                'fare_basis_codes_by_segment' => ['SLOW1', 'SLOW2'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function shopRow(): array
    {
        return [
            'validating_carrier' => 'QR',
            'segment_count' => 2,
            'total_fare' => 569.73,
            'currency' => 'USD',
            'booking_classes_by_segment' => ['S', 'S'],
            'fare_basis_codes_by_segment' => ['SLOW1', 'SLOW2'],
        ];
    }

    /**
     * @param  array<string, mixed>  $snap
     * @return array<string, mixed>
     */
    protected function draftFromSnap(array $snap): array
    {
        return [
            'provider' => 'sabre',
            'validating_carrier' => 'QR',
            'fare' => ['amount' => 569.73, 'currency' => 'USD'],
            'segments' => array_map(static function (array $segment): array {
                return array_merge($segment, [
                    'booking_class' => 'S',
                    'fare_basis_code' => $segment['origin'] === 'LHE' ? 'SLOW1' : 'SLOW2',
                    'segment_cabin_code' => 'Y',
                ]);
            }, $snap['segments']),
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
