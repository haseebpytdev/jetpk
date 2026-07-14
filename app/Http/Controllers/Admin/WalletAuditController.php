<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\StreamsFinanceCsvExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ArchiveDuplicateWalletsRequest;
use App\Policies\WalletAuditPolicy;
use App\Services\Finance\Wallets\DuplicateWalletArchiveService;
use App\Services\Finance\Wallets\WalletAuditService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Platform-admin read-only duplicate wallet audit UI (Finance-Reports-14).
 */
class WalletAuditController extends Controller
{
    use StreamsFinanceCsvExport;

    public function __construct(
        protected WalletAuditService $audit,
        protected DuplicateWalletArchiveService $archive,
        protected WalletAuditPolicy $policy,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($this->policy->view($request->user()), 403);

        $onlyDuplicates = $request->boolean('only_duplicates');
        $onlyCandidates = $request->boolean('only_candidates');
        $agencyId = $request->filled('agency_id') ? (int) $request->input('agency_id') : null;

        $report = $this->audit->build(
            agencyId: $agencyId,
            onlyDuplicates: $onlyDuplicates,
            onlyCandidates: $onlyCandidates,
        );

        return view('dashboard.admin.finance.wallet-audit.index', [
            'report' => $report,
            'filters' => [
                'agency_id' => $agencyId,
                'only_duplicates' => $onlyDuplicates,
                'only_candidates' => $onlyCandidates,
            ],
            'pageTitle' => 'Wallet Audit',
            'pageSubtitle' => 'Duplicate wallet classification; archive zero-balance duplicates after explicit confirmation.',
            'canArchive' => $this->policy->archive($request->user()),
        ]);
    }

    public function archivePreview(Request $request): View|RedirectResponse
    {
        abort_unless($this->policy->archive($request->user()), 403);

        $agencyId = $request->filled('agency_id') ? (int) $request->input('agency_id') : null;
        if ($agencyId === null) {
            return redirect()
                ->route('admin.finance.wallet-audit.index')
                ->with('error', 'Select an agency (agency_id) before opening archive preview.');
        }

        $preview = $this->archive->preview($agencyId);

        return view('dashboard.admin.finance.wallet-audit.archive-preview', [
            'preview' => $preview,
            'agencyId' => $agencyId,
            'pageTitle' => 'Archive duplicate wallets',
            'pageSubtitle' => 'Agency #'.$agencyId.' — review eligible zero-balance duplicates before archiving.',
        ]);
    }

    public function archive(ArchiveDuplicateWalletsRequest $request): RedirectResponse
    {
        $agencyId = (int) $request->validated('agency_id');
        $reason = (string) $request->validated('reason');

        $batch = $this->archive->archiveEligibleForAgency(
            agency: $agencyId,
            actor: $request->user(),
            reason: $reason,
            dryRun: false,
            request: $request,
        );

        return redirect()
            ->route('admin.finance.wallet-audit.index', ['agency_id' => $agencyId])
            ->with('status', 'wallet-archive-complete')
            ->with('wallet_archive_archived', $batch['archived_count'])
            ->with('wallet_archive_skipped', $batch['skipped_count']);
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($this->policy->view($request->user()), 403);

        $onlyDuplicates = $request->boolean('only_duplicates');
        $onlyCandidates = $request->boolean('only_candidates');
        $agencyId = $request->filled('agency_id') ? (int) $request->input('agency_id') : null;

        return $this->streamFinanceCsv(
            $this->audit->csvRows($agencyId, $onlyDuplicates, $onlyCandidates),
            'wallet-audit',
        );
    }
}
