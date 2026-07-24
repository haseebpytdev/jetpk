<?php

namespace App\Support\Sabre\Scenario;

use App\Models\SupplierBookingAttempt;
use Illuminate\Support\Facades\Storage;

/**
 * Loads persisted Phase 14/15 artifacts and retrieve attempt safe evidence (no supplier HTTP).
 */
final class SabreGdsQrUnticketedPostCancelReplayEvidenceLoader
{
    /**
     * @return array<string, mixed>
     */
    public function load(
        string $priorCancellationLifecycleRunId,
        string $postCancelRetrieveLifecycleRunId,
        int $retrieveAttemptId,
    ): array {
        $priorArtifact = $this->loadJsonArtifact(
            SabreGdsQrUnticketedCancelLifecycle::ARTIFACT_DIRECTORY.'/'.$priorCancellationLifecycleRunId.'-send.json',
        );
        $retrieveArtifact = $this->loadJsonArtifact(
            SabreGdsQrUnticketedPostCancelRetrieveLifecycle::ARTIFACT_DIRECTORY.'/'.$postCancelRetrieveLifecycleRunId.'-send.json',
        );
        $attempt = SupplierBookingAttempt::query()->find($retrieveAttemptId);

        return [
            'prior_cancellation_artifact' => $priorArtifact,
            'post_cancel_retrieve_artifact' => $retrieveArtifact,
            'retrieve_attempt' => $attempt,
            'retrieve_attempt_safe_summary' => is_array($attempt?->safe_summary) ? $attempt->safe_summary : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function loadJsonArtifact(string $relativePath): ?array
    {
        if (! Storage::disk('local')->exists($relativePath)) {
            return null;
        }
        $decoded = json_decode((string) Storage::disk('local')->get($relativePath), true);

        return is_array($decoded) ? $decoded : null;
    }
}
