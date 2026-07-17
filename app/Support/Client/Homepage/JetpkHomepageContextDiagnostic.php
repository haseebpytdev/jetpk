<?php

namespace App\Support\Client\Homepage;

use App\Models\ClientProfile;
use App\Services\Client\CurrentClientContext;
use App\Support\Client\ClientPageKeys;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * JETPK-HOMEPAGE-CMS Task 14: source-level live diagnostic for the JetPK
 * homepage tenant-resolution question raised in
 * docs/JETPK_CMS_ROOT_CAUSE_ANALYSIS.md (H2/H4). Exists to be turned on for
 * a single production request, read the log line, then turned back off —
 * see docs/JETPK_CMS_LIVE_DIAGNOSTIC_GUIDE.md for the exact steps.
 *
 * Hard rules, enforced by this class's structure, not just by convention:
 *   1. The config flag check is the FIRST thing logIfEnabled() does — no
 *      query, no resolution, nothing runs before it if the flag is off.
 *      "No performance impact beyond a trivial conditional" when disabled.
 *   2. Only ever logs the fixed field set below. There is no code path in
 *      this class that logs an arbitrary array, a whole model, a whole
 *      request, or anything not explicitly named here.
 *   3. Only fires for a resolved profile whose active_frontend_theme is
 *      'jetpakistan' — "during a JetPakistan homepage request" is read
 *      literally, not as "any homepage request on this shared codebase."
 */
final class JetpkHomepageContextDiagnostic
{
    public function __construct(
        private readonly CurrentClientContext $clientContext,
    ) {}

    public function logIfEnabled(Request $request): void
    {
        if (! config('jetpk_homepage.context_diagnostic_enabled', false)) {
            return;
        }

        $profile = $this->clientContext->get();
        if ($profile === null || $profile->active_frontend_theme !== 'jetpakistan') {
            return;
        }

        Log::info('jetpk_cms_context_diagnostic', $this->safeFields($request, $profile));
    }

    /**
     * @return array<string, mixed>
     */
    private function safeFields(Request $request, ClientProfile $profile): array
    {
        $row = null;
        if (Schema::hasTable('client_page_settings')) {
            $row = \App\Models\ClientPageSetting::query()
                ->where('client_profile_id', $profile->id)
                ->where('page_key', ClientPageKeys::HOME)
                ->where('status', \App\Enums\ClientPageSettingStatus::Published)
                ->first();
        }

        $content = is_array($row?->content_json) ? $row->content_json : null;

        return [
            'request_host' => $request->getHost(),
            'resolved_client_profile_id' => $profile->id,
            'resolved_client_slug' => $profile->slug,
            'page_key' => ClientPageKeys::HOME,
            'published_row_status' => $row !== null ? 'found' : 'not_found',
            'published_row_id' => $row?->id,
            'published_row_client_profile_id' => $row?->client_profile_id,
            'content_exists' => $content !== null,
            'content_top_level_keys' => $content !== null ? array_keys($content) : [],
            'content_checksum' => $content !== null ? hash('sha256', json_encode($content, JSON_THROW_ON_ERROR)) : null,
            'schema_version' => HomepageContentNormalizer::SCHEMA_VERSION,
        ];
    }
}
