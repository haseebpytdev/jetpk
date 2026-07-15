<?php

namespace App\Http\Controllers\Staff;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\AgentCommissionEntry;
use App\Models\Booking;
use App\Services\Reports\BookingReportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Staff portal platform reports (permission-gated).
 */
class ReportsController extends Controller
{
    public function __construct(
        protected BookingReportService $bookingReportService,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewPlatformReports', Booking::class);

        $report = $this->bookingReportService->build($request->user(), $request);
        $commissionTotals = $this->commissionTotals($request);

        $tab = $request->string('tab')->toString();
        $allowedTabs = ['overview', 'sales', 'payments', 'bookings', 'suppliers', 'agents', 'routes', 'refunds', 'documents', 'exports'];
        if (! in_array($tab, $allowedTabs, true)) {
            $tab = 'overview';
        }

        return view('dashboard.admin.reports', array_merge($report, [
            'commissionTotals' => $commissionTotals,
            'activeTab' => $tab,
            'allowedTabs' => $allowedTabs,
            'bookingStatusOptions' => array_map(fn ($status): string => $status->value, BookingStatus::cases()),
            'reportsTitle' => 'Platform Reports',
            'reportsExportRoute' => 'staff.reports.export',
            'reportsIndexRoute' => 'staff.reports.index',
            'reportsSupplierDiagnosticsRoute' => null,
            'reportsScope' => 'platform',
        ]));
    }

    public function export(Request $request, string $type): StreamedResponse
    {
        Gate::authorize('exportPlatformReports', Booking::class);

        $allowedTypes = ['sales', 'payments', 'bookings', 'agents', 'refunds', 'supplier_diagnostics', 'documents'];
        if (! in_array($type, $allowedTypes, true)) {
            abort(404);
        }

        $report = $this->bookingReportService->build($request->user(), $request);
        $rows = $this->bookingReportService->exportRows($type, $report);

        $filename = sprintf('reports-%s-%s.csv', $type, now()->format('Ymd-His'));

        return response()->stream(static function () use ($rows): void {
            $handle = fopen('php://output', 'wb');
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    /**
     * @return array{approved: float, paid: float}
     */
    protected function commissionTotals(Request $request): array
    {
        $commissionQuery = AgentCommissionEntry::query();

        return [
            'approved' => (float) (clone $commissionQuery)->where('status', 'approved')->sum('commission_amount'),
            'paid' => (float) (clone $commissionQuery)->where('status', 'paid')->sum('commission_amount'),
        ];
    }
}
