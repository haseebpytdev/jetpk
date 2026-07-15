@extends(client_layout('dashboard', 'admin'))

@section('title', 'Reports & Analytics')

@push('styles')
<style>
    /* ============================================================
       Reports shell - consistent admin content shell
       ============================================================ */
    .reports-shell {
        max-width: 1440px;
        margin: 0 auto;
        padding: 28px 32px 48px;
    }

    /* ============================================================
       Filter card - gradient surface, 3 row hierarchy
       ============================================================ */
    .reports-toolbar {
        background: linear-gradient(180deg, #f8fbff 0%, #eef5ff 100%);
        border: 1px solid #d8e6fb;
        border-radius: 18px;
        padding: 20px 22px;
        box-shadow: 0 14px 40px rgba(15, 35, 65, 0.05);
    }
    .reports-toolbar-row { padding: 0.6rem 0; }
    .reports-toolbar-row:first-child { padding-top: 0; }
    .reports-toolbar-row:last-child { padding-bottom: 0; }
    .reports-toolbar-divider {
        height: 1px;
        background: #d6e4f7;
        margin: 0.2rem 0;
    }
    .reports-toolbar-section-label {
        font-size: 11px;
        text-transform: uppercase;
        font-weight: 800;
        letter-spacing: 0.08em;
        color: #475569;
        margin-bottom: 0.55rem;
    }
    .reports-presets {
        gap: 10px;
    }
    .reports-presets .btn {
        font-size: 0.82rem;
        padding: 0.4rem 0.8rem;
        font-weight: 700;
        min-height: 36px;
        background: #fff;
        border: 1px solid #cbd6e8;
        color: #1e293b;
    }
    .reports-presets .btn:hover {
        background: #eef5ff;
        border-color: #93b8ee;
    }
    .reports-presets .btn.active {
        background: #1d4ed8;
        color: #fff;
        border-color: #1d4ed8;
    }
    .reports-toolbar .reports-filter-grid {
        --bs-gutter-x: 12px;
        --bs-gutter-y: 12px;
        max-width: 100%;
    }
    .reports-toolbar .reports-filter-grid > [class*="col-"] {
        min-width: 0;
    }
    .reports-toolbar .jp-control,
    .reports-toolbar .jp-control {
        min-height: 42px;
        border-radius: 10px;
        border-color: #cbd6e8;
        background: #fff;
        max-width: 100%;
        min-width: 0;
        width: 100%;
        box-sizing: border-box;
    }
    .reports-toolbar .jp-label {
        font-size: 12px;
        font-weight: 700;
        color: #475569;
        margin-bottom: 0.3rem;
    }
    .reports-toolbar-actions {
        display: grid;
        gap: 10px;
    }
    @media (min-width: 576px) {
        .reports-toolbar-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
        }
        .reports-toolbar-actions .reports-action-spacer { flex: 1 1 auto; }
    }
    .reports-toolbar-actions .btn {
        font-weight: 700;
        min-height: 40px;
        border-radius: 10px;
    }
    .reports-toolbar-actions .btn.btn-outline-secondary {
        background: #fff;
        border-color: #cbd6e8;
        color: #1e293b;
    }
    .reports-toolbar-actions .btn[disabled] {
        background: #f1f5f9;
        border-color: #cbd5e1;
        color: #64748b;
        opacity: 1;
    }

    /* ============================================================
       Report tabs - desktop pills with soft active background, mobile horizontal scroll
       ============================================================ */
    .ota-rep-tabs {
        display: flex;
        gap: 6px;
        padding-bottom: 0;
        margin-bottom: 1rem;
        border-bottom: 1px solid #dbe5f1;
        flex-wrap: nowrap;
        overflow-x: auto;
        overflow-y: hidden;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
        -ms-overflow-style: none;
    }
    .ota-rep-tabs::-webkit-scrollbar { display: none; }
    .ota-rep-tabs .nav-item { flex: 0 0 auto; }
    .ota-rep-tabs .nav-link {
        font-weight: 700;
        font-size: 0.92rem;
        padding: 11px 16px;
        border: 1px solid transparent;
        border-radius: 12px 12px 0 0;
        color: #475569;
        white-space: nowrap;
        background: transparent;
        transition: background-color 0.12s ease, color 0.12s ease, border-color 0.12s ease;
    }
    .ota-rep-tabs .nav-link:hover {
        color: #1d4ed8;
        background: rgba(238, 245, 255, 0.7);
    }
    .ota-rep-tabs .nav-link.active {
        color: #1d4ed8;
        background: #eef5ff;
        border: 1px solid #bfdbfe;
        border-bottom-color: #eef5ff;
    }
    .ota-rep-tabs .nav-link:focus-visible {
        outline: 2px solid #1d4ed8;
        outline-offset: 2px;
    }
    /* 1024-768 landscape: tab row must wrap (audit viewport tabletLandscape1024) */
    @media (max-width: 1279.98px) {
        .ota-rep-tabs {
            flex-wrap: wrap !important;
            overflow-x: visible !important;
            max-width: 100%;
        }
        .ota-rep-tabs .nav-item {
            flex: 0 1 auto !important;
            min-width: 0;
            max-width: 100%;
        }
        .ota-rep-tabs .nav-link {
            max-width: 100%;
            white-space: normal;
        }
    }
    @media (max-width: 1023.98px) {
        .ota-rep-tabs .nav-item {
            flex: 1 1 calc(33.333% - 6px) !important;
        }
        .ota-rep-tabs .nav-link {
            width: 100%;
            text-align: center;
        }
        .reports-filter-grid > [class*="col-"] {
            width: 50% !important;
            flex: 0 0 50% !important;
            max-width: 50% !important;
        }
        .reports-toolbar-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(100%, 10rem), 1fr));
            gap: 0.5rem;
            max-width: 100%;
        }
        .reports-toolbar-actions .reports-action-spacer {
            display: none !important;
        }
        .reports-toolbar-actions .btn {
            width: 100%;
            max-width: 100%;
        }
    }
    @media (max-width: 767.98px) {
        .ota-rep-tabs {
            border-bottom: 0;
            gap: 8px;
            padding: 0.2rem 0 0.4rem;
            flex-wrap: wrap;
            overflow-x: visible;
            max-width: 100%;
        }
        .ota-rep-tabs .nav-item {
            flex: 1 1 calc(50% - 4px);
            min-width: 0;
            max-width: 100%;
        }
        .ota-rep-tabs .nav-link,
        .ota-rep-tabs .nav-link:focus,
        .ota-rep-tabs .nav-link:hover {
            border: 1px solid #cbd6e8;
            border-radius: 999px;
            padding: 8px 14px;
            background: #fff;
            color: #1e293b;
            font-size: 0.85rem;
            width: 100%;
            max-width: 100%;
            text-align: center;
            white-space: normal;
        }
        .ota-rep-tabs .nav-link.active {
            background: #1d4ed8;
            color: #fff;
            border-color: #1d4ed8;
            box-shadow: 0 4px 14px rgba(30, 58, 138, 0.25);
        }
    }

    /* ============================================================
       KPI tiles - premium card style with tabular numerics
       ============================================================ */
    .ota-kpi-tile {
        position: relative;
        border: 1px solid #dfe8f3;
        border-radius: 16px;
        padding: 18px;
        background: #fff;
        height: 100%;
        min-height: 104px;
        box-shadow: 0 10px 26px rgba(15, 35, 65, 0.045);
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .ota-kpi-tile .ota-kpi-tile-label {
        font-size: 11px;
        text-transform: uppercase;
        font-weight: 800;
        letter-spacing: 0.08em;
        color: #64748b;
    }
    .ota-kpi-tile .ota-kpi-tile-value {
        font-size: 24px;
        font-weight: 900;
        color: #071a34;
        letter-spacing: -0.02em;
        line-height: 1.1;
        margin-top: 4px;
        font-variant-numeric: tabular-nums;
        font-feature-settings: "tnum";
    }
    .ota-kpi-tile .ota-kpi-tile-helper {
        font-size: 13px;
        color: #64748b;
        margin-top: 2px;
    }
    .ota-kpi-tile-link {
        text-decoration: none;
        color: inherit;
        display: block;
        height: 100%;
    }
    .ota-kpi-tile-link:hover .ota-kpi-tile {
        transform: translateY(-2px);
        box-shadow: 0 16px 32px rgba(15, 35, 65, 0.08);
        border-color: #b9d2f3;
        transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
    }

    /* Section headings - Financial / Operational / Agents / Trends / Performance tables */
    .ota-report-section-heading {
        margin: 28px 0 12px;
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 1rem;
    }
    .ota-report-section-heading:first-child { margin-top: 0; }
    .ota-report-section-title {
        margin: 0;
        font-size: 1.05rem;
        font-weight: 900;
        letter-spacing: -0.01em;
        color: #071a34;
    }
    .ota-report-section-subtitle {
        margin-top: 0.15rem;
        font-size: 13px;
        color: #64748b;
    }
    .ota-report-section-eyebrow {
        font-size: 11px;
        text-transform: uppercase;
        font-weight: 800;
        letter-spacing: 0.08em;
        color: #1d4ed8;
        margin-bottom: 0.15rem;
    }

    /* ============================================================
       Cards & tables - rounded outer cards, sticky headers, currency right-aligned tabular
       ============================================================ */
    .ota-rep-card {
        border: 1px solid #dfe8f3;
        border-radius: 16px;
        background: #fff;
        overflow: hidden;
        box-shadow: 0 10px 26px rgba(15, 35, 65, 0.045);
    }
    .ota-rep-card .card-header {
        background: transparent;
        border-bottom: 1px solid #e8eef6;
        padding: 16px 18px;
    }
    .ota-rep-card .card-header .card-title {
        font-size: 16px;
        font-weight: 900;
        color: #071a34;
        letter-spacing: -0.01em;
    }
    .admin-table-scroll,
    .ota-rep-card .table-responsive,
    .ota-rep-card .admin-table-scroll {
        max-height: 520px;
        overflow-x: auto;
        overflow-y: auto;
        scrollbar-width: thin;
    }
    .ota-rep-table {
        margin-bottom: 0;
        min-width: 760px;
    }
    .ota-rep-table thead th {
        position: sticky;
        top: 0;
        z-index: 2;
        background: #f8fafc;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        font-weight: 800;
        color: #64748b;
        border-bottom: 1px solid #dbe5f1;
        padding: 12px 14px;
        white-space: nowrap;
    }
    .ota-rep-table tbody td {
        padding: 14px;
        vertical-align: middle;
        border-top: 1px solid #edf2f7;
        color: #0f172a;
    }
    .ota-rep-table tbody tr {
        transition: background-color 0.12s ease;
    }
    .ota-rep-table tbody tr:hover td {
        background-color: #f8fbff;
    }
    .ota-rep-table .text-end,
    .ota-rep-table .is-money,
    .ota-rep-table .is-number,
    .ota-num {
        text-align: right;
        font-variant-numeric: tabular-nums;
        font-feature-settings: "tnum";
        white-space: nowrap;
    }
    .ota-rep-table .ota-num-strong {
        font-weight: 700;
        color: #071a34;
    }
    .ota-prov-table {
        min-width: 1120px;
    }
    .ota-prov-table .ota-prov-status { font-size: 11px; padding: 0.22rem 0.6rem; border-radius: 999px; font-weight: 800; letter-spacing: 0.02em; }
    .ota-prov-table .ota-prov-status--connected { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
    .ota-prov-table .ota-prov-status--disabled  { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }
    .ota-prov-table .ota-prov-status--error     { background: #fee2e2; color: #7f1d1d; border: 1px solid #fca5a5; }
    .ota-prov-table .ota-prov-status--not_configured { background: #f8fafc; color: #64748b; border: 1px solid #e2e8f0; }
    .ota-pipeline-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.75rem; }
    @media (min-width: 768px) { .ota-pipeline-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
    @media (min-width: 1200px) { .ota-pipeline-grid { grid-template-columns: repeat(6, minmax(0, 1fr)); } }
    .ota-pipeline-tile {
        border: 1px solid #dfe8f3;
        border-radius: 12px;
        padding: 12px 14px;
        background: #fff;
        min-height: 76px;
    }
    .ota-pipeline-tile .pl-label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.06em; font-weight: 800; color: #64748b; }
    .ota-pipeline-tile .pl-value { font-size: 22px; font-weight: 900; color: #071a34; font-variant-numeric: tabular-nums; }

    /* ============================================================
       Empty states  premium icon + title + helper
       ============================================================ */
    .ota-empty {
        text-align: center;
        padding: 0;
        background: linear-gradient(180deg, #fafbfc 0%, #ffffff 100%);
        border-top: 0;
    }
    .ota-empty-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 6px;
        padding: 36px 16px 32px;
    }
    .ota-empty-state-icon {
        width: 56px;
        height: 56px;
        border-radius: 16px;
        background: linear-gradient(135deg, #eef5ff 0%, #dbeafe 100%);
        color: #1d4ed8;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 26px;
        margin-bottom: 6px;
    }
    .ota-empty-state-title {
        font-size: 0.98rem;
        font-weight: 800;
        color: #0f172a;
    }
    .ota-empty-state-help {
        font-size: 0.86rem;
        color: #64748b;
        max-width: 420px;
    }
    .ota-empty-state-action {
        margin-top: 6px;
    }
    .ota-rep-table tbody tr:has(td.ota-empty):hover td {
        background-color: transparent;
    }

    /* ============================================================
       Charts  equal min-height 320px, nice grid + value labels above bars
       ============================================================ */
    .ota-chart-svg {
        width: 100%;
        height: 240px;
        display: block;
        overflow: visible;
    }
    .ota-chart-grid line {
        stroke: #e2e8f0;
        stroke-width: 1;
        stroke-dasharray: 4 4;
    }
    .ota-chart-axis {
        stroke: #cbd5e1;
        stroke-width: 1;
    }
    .ota-chart-bar {
        transition: opacity 0.15s ease, transform 0.15s ease;
        transform-origin: center bottom;
    }
    .ota-chart-bar:hover {
        opacity: 0.85;
    }
    .ota-chart-axis-label {
        font-size: 11px;
        font-weight: 600;
        fill: #64748b;
        font-family: inherit;
        font-variant-numeric: tabular-nums;
    }
    .ota-chart-x-label {
        font-size: 11px;
        font-weight: 600;
        fill: #475569;
    }
    .ota-chart-value-label {
        font-size: 11px;
        font-weight: 800;
        fill: #0f172a;
        font-variant-numeric: tabular-nums;
        paint-order: stroke;
        stroke: #ffffff;
        stroke-width: 3;
        stroke-linejoin: round;
    }
    .ota-chart-empty {
        text-align: center;
        color: #94a3b8;
        padding: 2.25rem 1rem;
        font-size: 0.9rem;
        width: 100%;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 4px;
    }
    .ota-rep-chart-card {
        min-height: 320px;
        display: flex;
        flex-direction: column;
    }
    .ota-rep-chart-card .card-header {
        padding: 16px 18px;
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 0.15rem;
    }
    .ota-rep-chart-card .card-body {
        flex: 1 1 auto;
        display: flex;
        align-items: stretch;
        justify-content: stretch;
        padding: 16px 18px 18px;
    }
    .ota-rep-chart-card .ota-chart-svg { height: 240px; }
    .ota-rep-chart-subtitle {
        font-size: 13px;
        color: #64748b;
        font-weight: 600;
    }

    /* ============================================================
       Export center cards
       ============================================================ */
    .ota-export-card {
        border: 1px solid #dfe8f3;
        border-radius: 16px;
        padding: 18px;
        background: #fff;
        height: 100%;
        box-shadow: 0 10px 26px rgba(15, 35, 65, 0.045);
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .ota-export-card .ota-export-icon {
        width: 44px; height: 44px; border-radius: 12px;
        background: linear-gradient(135deg, rgba(37,99,235,0.12), rgba(14,165,233,0.10));
        color: #1d4ed8; display: flex; align-items: center; justify-content: center;
        font-size: 1.2rem; margin-bottom: 6px;
    }
    .ota-export-card .btn {
        align-self: flex-start;
        margin-top: auto;
        min-height: 38px;
    }

    /* Buttons  focus state + min height */
    .ota-rep-card .btn,
    .ota-export-card .btn,
    .reports-toolbar .btn {
        min-height: 36px;
        font-weight: 700;
        border-radius: 10px;
    }
    .ota-rep-card .btn:focus-visible,
    .ota-export-card .btn:focus-visible,
    .reports-toolbar .btn:focus-visible,
    .reports-toolbar .jp-control:focus-visible,
    .reports-toolbar .jp-control:focus-visible {
        outline: 2px solid #1d4ed8;
        outline-offset: 2px;
    }

    /* ============================================================
       Reports responsive contract
       Breakpoints:
         - >= 1500px: financial/operational 6 cols, agent 4 cols, document 5 cols
         - 1024-1499px: financial/operational 3 cols, agent 4 cols, document 3 cols
         - 641-1023px: KPI 2 cols, filters 3 cols, charts stacked
         - <= 640px: everything 1 col, action buttons full width
       ============================================================ */
    .ota-kpi-responsive-row > [class*="col-"] { flex: 0 0 auto; }

    @media (min-width: 1024px) and (max-width: 1499.98px) {
        .ota-kpi-responsive-row--six > [class*="col-"] { width: 33.3333%; }
        .ota-kpi-responsive-row--four > [class*="col-"] { width: 25%; }
        .ota-kpi-responsive-row--five > [class*="col-"] { width: 33.3333%; }
        .ota-report-chart-row > [class*="col-"] { width: 100%; }
    }

    @media (min-width: 1500px) {
        .ota-kpi-responsive-row--six > [class*="col-"] { width: 16.6667%; }
        .ota-kpi-responsive-row--four > [class*="col-"] { width: 25%; }
        .ota-kpi-responsive-row--five > [class*="col-"] { width: 20%; }
    }

    @media (min-width: 768px) and (max-width: 1023.98px) {
        .ota-kpi-responsive-row > [class*="col-"] { width: 50%; }
        .reports-filter-grid > [class*="col-"] { width: 33.3333%; }
        .ota-report-chart-row > [class*="col-"] { width: 100%; }
    }

    @media (min-width: 641px) and (max-width: 767.98px) {
        .ota-kpi-responsive-row > [class*="col-"] { width: 50%; }
        .reports-filter-grid > [class*="col-"] { width: 50%; }
        .ota-report-chart-row > [class*="col-"] { width: 100%; }
    }

    @media (max-width: 640px) {
        .reports-shell { padding: 16px; }
        .reports-toolbar { padding: 16px; border-radius: 14px; }
        .ota-kpi-responsive-row > [class*="col-"],
        .reports-filter-grid > [class*="col-"],
        .ota-report-chart-row > [class*="col-"] { width: 100%; }
        .reports-toolbar-actions {
            display: grid;
            grid-template-columns: 1fr;
        }
        .reports-toolbar-actions .btn { width: 100%; }
        .reports-presets .btn { flex: 1 1 auto; }
        .ota-rep-chart-card { min-height: 260px; }
        .ota-rep-chart-card .ota-chart-svg { height: 220px; }
    }

    @media (min-width: 992px) and (max-width: 1279.98px) {
        .reports-shell {
            padding-left: 16px;
            padding-right: 16px;
        }
        .reports-filter-grid > [class*="col-lg-2"] {
            flex: 0 0 33.333333%;
            max-width: 33.333333%;
            width: 33.333333%;
        }
        .reports-toolbar-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.5rem;
            max-width: 100%;
        }
        .reports-toolbar-actions .reports-action-spacer {
            display: none;
        }
        .reports-toolbar-actions .btn {
            flex: 1 1 auto;
            min-width: min(100%, 9.5rem);
            max-width: 100%;
            white-space: normal;
        }
        .reports-presets .btn {
            flex: 0 1 auto;
            max-width: 100%;
        }
    }

    @media (min-width: 641px) and (max-width: 900px) {
        .reports-shell { padding: 22px 20px 40px; }
    }
</style>
@endpush

@section('page-header')
    <div class="reports-shell pb-0" style="padding-top: 0;">
        <div class="jp-between ota-admin-page-header">
            <div class="col">
                <div class="page-pretitle">Analytics</div>
                <h1 class="jp-page-title">{{ $reportsTitle }}</h1>
                <div class="text-secondary mt-1">
                    Live booking, revenue, payment, supplier, agent, and route performance.
                </div>
            </div>
        </div>
    </div>
@endsection

@php
    $reportsExportRoute = $reportsExportRoute ?? 'admin.reports.export';
    $reportsIndexRoute = $reportsIndexRoute ?? 'admin.reports';
    $reportsSupplierDiagnosticsRoute = $reportsSupplierDiagnosticsRoute ?? 'admin.reports.supplier-diagnostics';
    $reportsScope = $reportsScope ?? 'platform';
    $reportsTitle = $reportsTitle ?? 'Platform Reports';

    if (! function_exists('ota_chart_nice_ticks')) {
        /**
         * Build a nice axis tick array (0, niceStep, 2*niceStep, ) that ends at or above $max.
         * Avoids ugly fractional ticks like 0.5, 1.5 for small integer datasets.
         *
         * @return array{ticks: array<int, float>, max: float}
         */
        function ota_chart_nice_ticks(float $max, int $targetCount = 4, bool $integerOnly = false): array
        {
            if ($max <= 0) {
                return ['ticks' => [0.0, 1.0], 'max' => 1.0];
            }
            $rawStep = $max / max(1, $targetCount);
            if ($integerOnly && $rawStep < 1) {
                $rawStep = 1.0;
            }
            $exp = (int) floor(log10($rawStep));
            $base = 10 ** $exp;
            $fraction = $rawStep / $base;
            if ($fraction <= 1) {
                $niceStep = 1 * $base;
            } elseif ($fraction <= 2) {
                $niceStep = 2 * $base;
            } elseif ($fraction <= 2.5) {
                $niceStep = 2.5 * $base;
            } elseif ($fraction <= 5) {
                $niceStep = 5 * $base;
            } else {
                $niceStep = 10 * $base;
            }
            if ($integerOnly && $niceStep < 1) {
                $niceStep = 1.0;
            }
            $niceMax = (float) (ceil($max / $niceStep) * $niceStep);
            if ($niceMax <= 0) {
                $niceMax = $niceStep;
            }
            $ticks = [];
            for ($v = 0.0; $v <= $niceMax + 0.000_001; $v += $niceStep) {
                $ticks[] = round($v, 6);
            }

            return ['ticks' => $ticks, 'max' => $niceMax];
        }
    }

    if (! function_exists('ota_render_bar_chart')) {
        /**
         * Premium inline SVG bar chart helper. Pure markup, no JS dependency.
         *
         * Improvements over the basic version:
         *  - Nice integer/Rs ticks (no overlapping 0.5 / 1.5 labels for small datasets).
         *  - Bar width is capped and bars are centered in their slot, so a single
         *    bar no longer fills the whole chart area.
         *  - Value labels are drawn above each bar.
         *  - Wider left padding when a "Rs " prefix is used so labels never clip.
         *  - Uses a vertical gradient + rounded corners + softer grid lines.
         *
         * @param  array<int, array{label:string, value:int|float}>  $series
         */
        function ota_render_bar_chart(array $series, string $valuePrefix = '', int $height = 240): string
        {
            if (empty($series)) {
                return '<div class="ota-chart-empty">'
                    .'<i class="ti ti-chart-bar mb-1" style="font-size:1.5rem;color:#94a3b8;"></i>'
                    .'<div>No data for selected filters.</div></div>';
            }

            $values = array_map(static fn ($r) => (float) ($r['value'] ?? 0), $series);
            $rawMax = max($values);
            $integerOnly = true;
            foreach ($values as $v) {
                if (floor($v) !== $v) {
                    $integerOnly = false;
                    break;
                }
            }

            $tickInfo = ota_chart_nice_ticks($rawMax, 4, $integerOnly);
            $ticks = $tickInfo['ticks'];
            $maxScale = $tickInfo['max'];

            $width = 720;
            $padLeft = $valuePrefix !== '' ? 78 : 56;
            $padRight = 28;
            $padTop = 28;
            $padBottom = 44;
            $chartWidth = $width - $padLeft - $padRight;
            $chartHeight = $height - $padTop - $padBottom;
            $count = count($series);
            $slot = $chartWidth / max(1, $count);

            $maxBarWidth = 64.0;
            $minBarWidth = 18.0;
            $barWidth = max($minBarWidth, min($maxBarWidth, $slot - 18));

            $gradientId = 'ota-bar-grad-'.substr(md5($valuePrefix.$height.$count.$maxScale), 0, 6);

            $svg = '<svg class="ota-chart-svg" viewBox="0 0 '.$width.' '.$height.'" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="xMidYMid meet" role="img" aria-label="Bar chart">';
            $svg .= '<defs>';
            $svg .= '<linearGradient id="'.$gradientId.'" x1="0" y1="0" x2="0" y2="1">';
            $svg .= '<stop offset="0%" stop-color="#60a5fa"/>';
            $svg .= '<stop offset="100%" stop-color="#1d4ed8"/>';
            $svg .= '</linearGradient>';
            $svg .= '</defs>';

            $svg .= '<g class="ota-chart-grid">';
            foreach ($ticks as $tickValue) {
                $y = $padTop + $chartHeight - ($chartHeight * ($tickValue / max($maxScale, 0.000_001)));
                $svg .= '<line x1="'.$padLeft.'" y1="'.number_format($y, 2, '.', '').'" x2="'.($width - $padRight).'" y2="'.number_format($y, 2, '.', '').'" />';
                $label = $valuePrefix.($integerOnly ? number_format((int) $tickValue) : number_format($tickValue, 0));
                $svg .= '<text class="ota-chart-axis-label" x="'.($padLeft - 10).'" y="'.number_format($y + 4, 2, '.', '').'" text-anchor="end">'.htmlspecialchars($label).'</text>';
            }
            $svg .= '</g>';

            $axisBottomY = $padTop + $chartHeight;
            $svg .= '<line class="ota-chart-axis" x1="'.$padLeft.'" y1="'.$padTop.'" x2="'.$padLeft.'" y2="'.$axisBottomY.'" />';
            $svg .= '<line class="ota-chart-axis" x1="'.$padLeft.'" y1="'.$axisBottomY.'" x2="'.($width - $padRight).'" y2="'.$axisBottomY.'" />';

            $labelStep = max(1, (int) ceil($count / 12));
            $i = 0;
            foreach ($series as $row) {
                $value = (float) ($row['value'] ?? 0);
                $h = $maxScale > 0 ? ($chartHeight * ($value / $maxScale)) : 0;
                $slotCenter = $padLeft + ($slot * $i) + ($slot / 2);
                $x = $slotCenter - $barWidth / 2;
                $y = $padTop + ($chartHeight - $h);
                $rawLabel = (string) ($row['label'] ?? '');
                $valueDisplay = $valuePrefix.($integerOnly ? number_format((int) $value) : number_format($value, 0));
                $title = htmlspecialchars($rawLabel.'  '.$valueDisplay);

                $svg .= '<g><title>'.$title.'</title>';
                $svg .= '<rect class="ota-chart-bar" fill="url(#'.$gradientId.')" x="'.number_format($x, 2, '.', '').'" y="'.number_format($y, 2, '.', '').'" width="'.number_format($barWidth, 2, '.', '').'" height="'.number_format(max(0, $h), 2, '.', '').'" rx="6" ry="6" />';

                if ($value > 0) {
                    $valueY = max($padTop + 14, $y - 8);
                    $svg .= '<text class="ota-chart-value-label" x="'.number_format($slotCenter, 2, '.', '').'" y="'.number_format($valueY, 2, '.', '').'" text-anchor="middle">'.htmlspecialchars($valueDisplay).'</text>';
                }

                if ($count <= 12 || $i % $labelStep === 0) {
                    $svg .= '<text class="ota-chart-axis-label ota-chart-x-label" x="'.number_format($slotCenter, 2, '.', '').'" y="'.($height - 16).'" text-anchor="middle">'.htmlspecialchars($rawLabel).'</text>';
                }

                $svg .= '</g>';
                $i++;
            }

            $svg .= '</svg>';

            return $svg;
        }
    }

    if (! function_exists('ota_money')) {
        function ota_money($value): string
        {
            return 'Rs '.number_format((int) round((float) $value));
        }
    }

    if (! function_exists('ota_active_tab_class')) {
        function ota_active_tab_class(string $tab, string $active): string
        {
            return $tab === $active ? 'active' : '';
        }
    }

    if (! function_exists('ota_tab_pane_class')) {
        function ota_tab_pane_class(string $tab, string $active): string
        {
            return 'tab-pane fade '.($tab === $active ? 'show active' : '');
        }
    }
@endphp

@section('content')
<div class="reports-shell" data-testid="ota-reports-shell">
    @php
        $sum = $summary ?? [];
        $f = $filters ?? [];
        $hasLiveData = (bool) ($hasLiveData ?? false);
        $financial = $financialKpis ?? [];
        $operational = $operationalKpis ?? [];
        $agentKpisData = $agentKpis ?? [];
        $salesByPeriodData = $salesByPeriod ?? collect();
        $paymentRowsData = $paymentRows ?? collect();
        $pipelineCounts = $bookingPipelineCounts ?? [];
        $pipelineRows = $bookingPipelineRows ?? collect();
        $supplierPerf = $supplierPerformance ?? collect();
        $agentPerf = $agentPerformance ?? collect();
        $routePerf = $routePerformance ?? collect();
        $refundKpisData = $refundKpis ?? [];
        $refundRowsData = $refundRows ?? collect();
        $documentKpisData = $documentKpis ?? [];
        $documentRowsData = $documentRows ?? collect();
        $salesTrend = $salesTrendChart ?? [];
        $bookingVolume = $bookingVolumeChart ?? [];
        $paymentChart = $paymentStatusChart ?? [];
        $statusOpts = $bookingStatusOptions ?? [];
        $tabsList = $allowedTabs ?? ['overview'];
        $current = $activeTab ?? 'overview';
    @endphp

    {{-- Reporting toolbar --}}
    <form method="GET" action="{{ route($reportsIndexRoute) }}" class="reports-toolbar mb-4" data-testid="ota-reports-toolbar">
        <input type="hidden" name="tab" value="{{ $current }}">

        {{-- Row 1: Quick range presets --}}
        <div class="reports-toolbar-row" data-testid="ota-reports-presets-row">
            <div class="reports-toolbar-section-label">Quick range</div>
            <div class="d-flex flex-wrap gap-2 reports-presets" data-testid="ota-reports-presets">
                @php $presets = [['today','Today'],['7d','7 days'],['30d','30 days'],['this_month','This month']]; @endphp
                @foreach ($presets as [$key, $label])
                    <button type="submit" name="preset" value="{{ $key }}" class="jp-btn jp-btn--ghost {{ ($f['preset'] ?? '') === $key ? 'active' : '' }}">{{ $label }}</button>
                @endforeach
                <button type="submit" name="preset" value="" class="jp-btn jp-btn--ghost {{ ($f['preset'] ?? '') === '' ? 'active' : '' }}">Custom</button>
            </div>
        </div>

        <div class="reports-toolbar-divider"></div>

        {{-- Row 2: Filter grid --}}
        <div class="reports-toolbar-row" data-testid="ota-reports-filters-row">
            <div class="reports-toolbar-section-label">Filters</div>
            <div class="jp-form-grid jp-form-grid--filter reports-filter-grid">
                <div class="col-6 col-md-4 col-lg-2">
                    <label class="jp-label small mb-1">From</label>
                    <input type="date" name="date_from" class="jp-control jp-control-sm" value="{{ $f['date_from'] ?? '' }}">
                </div>
                <div class="col-6 col-md-4 col-lg-2">
                    <label class="jp-label small mb-1">To</label>
                    <input type="date" name="date_to" class="jp-control jp-control-sm" value="{{ $f['date_to'] ?? '' }}">
                </div>
                <div class="col-6 col-md-4 col-lg-2">
                    <label class="jp-label small mb-1">Channel</label>
                    <select name="channel" class="jp-control jp-control-sm">
                        <option value="all" @selected(($f['channel'] ?? 'all') === 'all')>All</option>
                        <option value="direct" @selected(($f['channel'] ?? '') === 'direct')>Direct</option>
                        <option value="agent" @selected(($f['channel'] ?? '') === 'agent')>Agent</option>
                    </select>
                </div>
                <div class="col-6 col-md-4 col-lg-2">
                    <label class="jp-label small mb-1">Supplier</label>
                    <select name="supplier" class="jp-control jp-control-sm">
                        <option value="all" @selected(($f['supplier'] ?? 'all') === 'all')>All</option>
                        <option value="duffel" @selected(($f['supplier'] ?? '') === 'duffel')>Duffel</option>
                        <option value="sabre" @selected(($f['supplier'] ?? '') === 'sabre')>Sabre</option>
                        <option value="pia_ndc" @selected(($f['supplier'] ?? '') === 'pia_ndc')>PIA NDC</option>
                        <option value="airline_direct" @selected(($f['supplier'] ?? '') === 'airline_direct')>Airline direct</option>
                        <option value="none" @selected(($f['supplier'] ?? '') === 'none')>No supplier</option>
                    </select>
                </div>
                <div class="col-6 col-md-4 col-lg-2">
                    <label class="jp-label small mb-1">Status</label>
                    <select name="status" class="jp-control jp-control-sm">
                        <option value="">Any</option>
                        @foreach ($statusOpts as $opt)
                            <option value="{{ $opt }}" @selected(($f['status'] ?? '') === $opt)>{{ ucwords(str_replace('_',' ', $opt)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-4 col-lg-2">
                    <label class="jp-label small mb-1">Payment</label>
                    <select name="payment_status" class="jp-control jp-control-sm">
                        <option value="">Any</option>
                        @foreach (['unpaid','partial','paid','refunded'] as $ps)
                            <option value="{{ $ps }}" @selected(($f['payment_status'] ?? '') === $ps)>{{ ucfirst($ps) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="reports-toolbar-divider"></div>

        {{-- Row 3: Action buttons --}}
        <div class="reports-toolbar-row" data-testid="ota-reports-actions-row">
            <div class="reports-toolbar-actions ota-r-action-bar">
                <button type="submit" class="jp-btn jp-btn--primary">
                    <i class="ti ti-filter me-1"></i> Apply filters
                </button>
                <span class="reports-action-spacer d-none d-sm-block"></span>
                <a href="{{ route($reportsExportRoute, array_merge(['type' => 'sales'], request()->query())) }}" class="jp-btn jp-btn--ghost" data-testid="ota-export-sales">
                    <i class="ti ti-download me-1"></i> Export sales CSV
                </a>
                <a href="{{ route($reportsExportRoute, array_merge(['type' => 'payments'], request()->query())) }}" class="jp-btn jp-btn--ghost" data-testid="ota-export-payments">
                    <i class="ti ti-download me-1"></i> Export payments CSV
                </a>
                <span class="small text-secondary align-self-center" data-testid="ota-reports-pdf-unavailable">
                    PDF export not enabled yet. CSV exports remain available.
                </span>
            </div>
        </div>
    </form>

    @if (! $hasLiveData)
        <div class="jp-alert jp-alert--info mb-3" data-testid="ota-reports-empty">
            <i class="ti ti-info-circle me-2"></i>No live booking data yet for the selected filters.
        </div>
    @endif

    {{-- Tabs --}}
    <ul class="nav nav-tabs ota-rep-tabs mb-3" id="ota-report-tabs" role="tablist" data-testid="ota-reports-tabs">
        @foreach ($tabsList as $tk)
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ ota_active_tab_class($tk, $current) }}" id="tab-{{ $tk }}" data-bs-toggle="tab" data-bs-target="#pane-{{ $tk }}" type="button" role="tab" data-testid="ota-tab-{{ $tk }}">
                    {{ ucfirst(str_replace('_',' ', $tk)) }}
                </button>
            </li>
        @endforeach
    </ul>

    <div class="tab-content">
        {{-- ============================================================== --}}
        {{-- OVERVIEW TAB --}}
        {{-- ============================================================== --}}
        <div class="{{ ota_tab_pane_class('overview', $current) }}" id="pane-overview" role="tabpanel" data-testid="ota-pane-overview">
            <div class="ota-report-section-heading">
                <div>
                    <div class="ota-report-section-eyebrow">Financial</div>
                    <h2 class="ota-report-section-title ota-admin-section-title">Financial performance</h2>
                    <div class="ota-report-section-subtitle">Revenue, margin, refunds, and money still outstanding.</div>
                </div>
            </div>
            <div class="row g-3 mb-3 ota-kpi-responsive-row ota-kpi-responsive-row--six" data-testid="ota-financial-kpis">
                @php
                    $finCards = [
                        ['Gross sales', ota_money($financial['gross_sales'] ?? 0), 'All bookings, total ticket value'],
                        ['Net revenue', ota_money($financial['net_revenue'] ?? 0), 'Markup + fees - discount'],
                        ['Markup revenue', ota_money($financial['markup_revenue'] ?? 0), 'Operator margin'],
                        ['Service fees', ota_money($financial['service_fees'] ?? 0), 'Booking fees collected'],
                        ['Refund paid', ota_money($financial['refund_paid'] ?? 0), 'Cash refunded out'],
                        ['Outstanding balance', ota_money($financial['outstanding_balance'] ?? 0), 'Customer balance due'],
                    ];
                @endphp
                @foreach ($finCards as [$label, $value, $helper])
                    <div class="col-12 col-sm-6 col-md-4">
                        <div class="ota-kpi-tile">
                            <div class="ota-kpi-tile-label">{{ $label }}</div>
                            <div class="ota-kpi-tile-value">{{ $value }}</div>
                            <div class="ota-kpi-tile-helper">{{ $helper }}</div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="ota-report-section-heading">
                <div>
                    <div class="ota-report-section-eyebrow">Operational</div>
                    <h2 class="ota-report-section-title ota-admin-section-title">Operational workload</h2>
                    <div class="ota-report-section-subtitle">Booking queues, payment exposure, supplier handoff, and ticketing tasks.</div>
                </div>
            </div>
            <div class="row g-3 mb-3 ota-kpi-responsive-row ota-kpi-responsive-row--six" data-testid="ota-operational-kpis">
                @php
                    $opCards = [
                        ['Total bookings', number_format((int) ($operational['total_bookings'] ?? 0)), 'All bookings in period'],
                        ['Pending bookings', number_format((int) ($operational['pending_bookings'] ?? 0)), 'Awaiting follow-up'],
                        ['Unpaid / partial', number_format((int) ($operational['unpaid_partial_bookings'] ?? 0)), 'Need money collection'],
                        ['Supplier / PNR pending', number_format((int) ($operational['supplier_pnr_pending'] ?? 0)), 'Paid but PNR missing'],
                        ['Ticketing pending', number_format((int) ($operational['ticketing_pending'] ?? 0)), 'Ready to issue'],
                        ['Cancelled bookings', number_format((int) ($operational['cancelled_bookings'] ?? 0)), 'Cancelled in period'],
                    ];
                @endphp
                @foreach ($opCards as [$label, $value, $helper])
                    <div class="col-12 col-sm-6 col-md-4">
                        <div class="ota-kpi-tile">
                            <div class="ota-kpi-tile-label">{{ $label }}</div>
                            <div class="ota-kpi-tile-value">{{ $value }}</div>
                            <div class="ota-kpi-tile-helper">{{ $helper }}</div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="ota-report-section-heading">
                <div>
                    <div class="ota-report-section-eyebrow">Agents</div>
                    <h2 class="ota-report-section-title ota-admin-section-title">Agent performance</h2>
                    <div class="ota-report-section-subtitle">Agent sales and commission exposure for the selected period.</div>
                </div>
            </div>
            <div class="row g-3 mb-4 ota-kpi-responsive-row ota-kpi-responsive-row--four" data-testid="ota-agent-kpis">
                @php
                    $agentCards = [
                        ['Agent sales', ota_money($agentKpisData['agent_sales'] ?? 0), 'Sales by agents'],
                        ['Approved commission', ota_money($agentKpisData['approved_commission'] ?? 0), 'Ready to pay'],
                        ['Paid commission', ota_money($agentKpisData['paid_commission'] ?? 0), 'Paid out to agents'],
                        ['Pending commission', ota_money($agentKpisData['pending_commission'] ?? 0), 'Awaiting approval'],
                    ];
                @endphp
                @foreach ($agentCards as [$label, $value, $helper])
                    <div class="col-12 col-sm-6 col-lg-3">
                        <div class="ota-kpi-tile">
                            <div class="ota-kpi-tile-label">{{ $label }}</div>
                            <div class="ota-kpi-tile-value">{{ $value }}</div>
                            <div class="ota-kpi-tile-helper">{{ $helper }}</div>
                        </div>
                    </div>
                @endforeach
            </div>

            @php
                $grossThisPeriod = (float) ($financial['gross_sales'] ?? 0);
                $paymentTotalCount = (int) collect($paymentChart)->sum('value');
                $paidStatusRow = collect($paymentChart)->first(fn ($r) => strtolower((string) ($r['label'] ?? '')) === 'paid');
                $paidCount = (int) ($paidStatusRow['value'] ?? 0);
                $unpaidStatusRow = collect($paymentChart)->first(fn ($r) => strtolower((string) ($r['label'] ?? '')) === 'unpaid');
                $unpaidCount = (int) ($unpaidStatusRow['value'] ?? 0);
            @endphp
            <div class="ota-report-section-heading">
                <div>
                    <div class="ota-report-section-eyebrow">Charts</div>
                    <h2 class="ota-report-section-title ota-admin-section-title">Trend and payment mix</h2>
                    <div class="ota-report-section-subtitle">Visual snapshot of sales momentum and booking payment state.</div>
                </div>
            </div>
            <div class="row g-3 mb-4 ota-report-chart-row">
                <div class="col-lg-8">
                    <div class="ota-rep-card ota-rep-chart-card h-100" data-testid="ota-sales-trend-chart">
                        <div class="jp-card__head">
                            <h3 class="jp-card__title mb-0">Sales trend</h3>
                            <div class="ota-rep-chart-subtitle">{{ ota_money($grossThisPeriod) }} gross sales this period</div>
                        </div>
                        <div class="jp-card__body">{!! ota_render_bar_chart($salesTrend, 'Rs ') !!}</div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="ota-rep-card ota-rep-chart-card h-100" data-testid="ota-payment-status-chart">
                        <div class="jp-card__head">
                            <h3 class="jp-card__title mb-0">Payment status</h3>
                            <div class="ota-rep-chart-subtitle">
                                @if ($paymentTotalCount > 0)
                                    {{ number_format($paidCount) }} of {{ number_format($paymentTotalCount) }} bookings paid  {{ number_format($unpaidCount) }} unpaid
                                @else
                                    No payment activity in selected period
                                @endif
                            </div>
                        </div>
                        <div class="jp-card__body">{!! ota_render_bar_chart($paymentChart, '', 240) !!}</div>
                    </div>
                </div>
            </div>

            <div class="ota-report-section-heading">
                <div>
                    <div class="ota-report-section-eyebrow">Performance tables</div>
                    <h2 class="ota-report-section-title ota-admin-section-title">Routes, agents, and payments</h2>
                    <div class="ota-report-section-subtitle">Actionable tables for route performance, agent activity, and payment exposure.</div>
                </div>
            </div>
            <div class="row g-3 mb-4">
                <div class="col-lg-6">
                    <div class="ota-rep-card">
                        <div class="jp-card__head"><h3 class="jp-card__title mb-0">Top routes</h3></div>
                        <div class="table-responsive admin-table-scroll">
                            <table class="jp-table ota-rep-table ota-admin-table mb-0">
                                <thead><tr><th>Route</th><th class="text-end">Bookings</th><th class="text-end">Sales</th><th class="text-end">Avg ticket</th></tr></thead>
                                <tbody>
                                    @forelse ($topRoutes as $row)
                                        <tr>
                                            <td>{{ $row['route'] ?? '' }}</td>
                                            <td class="text-end">{{ number_format((int) ($row['bookings'] ?? 0)) }}</td>
                                            <td class="text-end">{{ ota_money($row['sales'] ?? 0) }}</td>
                                            <td class="text-end">{{ ota_money($row['average_ticket'] ?? 0) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="ota-empty" data-testid="ota-empty-top-routes">
                                            <div class="ota-empty-state">
                                                <div class="ota-empty-state-icon"><i class="ti ti-route"></i></div>
                                                <div class="ota-empty-state-title">No route data yet</div>
                                                <div class="ota-empty-state-help">Top routes will appear here once bookings include flight segments.</div>
                                            </div>
                                        </td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="ota-rep-card">
                        <div class="jp-card__head"><h3 class="jp-card__title mb-0">Top agents</h3></div>
                        <div class="table-responsive admin-table-scroll">
                            <table class="jp-table ota-rep-table ota-admin-table mb-0">
                                <thead><tr><th>Code</th><th>Agent</th><th class="text-end">Bookings</th><th class="text-end">Sales</th><th class="text-end">Commission</th></tr></thead>
                                <tbody>
                                    @forelse ($topAgents as $row)
                                        <tr>
                                            <td class="fw-semibold">{{ $row['agent_code'] ?? '' }}</td>
                                            <td>{{ $row['agent_name'] ?? '' }}</td>
                                            <td class="text-end">{{ number_format((int) ($row['bookings'] ?? 0)) }}</td>
                                            <td class="text-end">{{ ota_money($row['sales'] ?? 0) }}</td>
                                            <td class="text-end">{{ ota_money($row['commission'] ?? 0) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="ota-empty" data-testid="ota-empty-top-agents">
                                            <div class="ota-empty-state ota-r-text-safe">
                                                <div class="ota-empty-state-icon"><i class="ti ti-users"></i></div>
                                                <div class="ota-empty-state-title">No agent activity yet</div>
                                                <div class="ota-empty-state-help">Top performing agents will appear here once approved agents make bookings.</div>
                                            </div>
                                        </td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="ota-rep-card mb-2">
                <div class="jp-card__head"><h3 class="jp-card__title mb-0">Payment breakdown</h3></div>
                <div class="table-responsive admin-table-scroll">
                    <table class="jp-table ota-rep-table ota-admin-table mb-0">
                        <thead><tr><th>Status</th><th class="text-end">Count</th><th class="text-end">Amount</th></tr></thead>
                        <tbody>
                            @forelse ($paymentBreakdown as $row)
                                <tr>
                                    <td class="text-capitalize">{{ $row['status'] ?? '' }}</td>
                                    <td class="text-end">{{ number_format((int) ($row['count'] ?? 0)) }}</td>
                                    <td class="text-end">{{ ota_money($row['amount'] ?? 0) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="ota-empty" data-testid="ota-empty-payment-breakdown">
                                    <div class="ota-empty-state">
                                        <div class="ota-empty-state-icon"><i class="ti ti-cash-banknote"></i></div>
                                        <div class="ota-empty-state-title">No payments recorded yet</div>
                                        <div class="ota-empty-state-help">Once customers record payments, the breakdown will appear here.</div>
                                    </div>
                                </td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- ============================================================== --}}
        {{-- SALES TAB --}}
        {{-- ============================================================== --}}
        <div class="{{ ota_tab_pane_class('sales', $current) }}" id="pane-sales" role="tabpanel" data-testid="ota-pane-sales">
            <div class="ota-rep-card mb-3">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <div>
                        <h3 class="jp-card__title mb-0">Sales by period</h3>
                        <div class="ota-rep-chart-subtitle mt-1">{{ ota_money($financial['gross_sales'] ?? 0) }} gross sales  {{ number_format((int) ($operational['total_bookings'] ?? 0)) }} bookings</div>
                    </div>
                    <a href="{{ route($reportsExportRoute, array_merge(['type' => 'sales'], request()->query())) }}" class="jp-btn jp-btn--sm jp-btn--outline">
                        <i class="ti ti-download me-1"></i> CSV
                    </a>
                </div>
                <div class="jp-card__body">{!! ota_render_bar_chart($salesTrend, 'Rs ') !!}</div>
                <div class="table-responsive admin-table-scroll">
                    <table class="jp-table ota-rep-table ota-admin-table mb-0">
                        <thead>
                            <tr>
                                <th>Period</th>
                                <th class="text-end">Bookings</th>
                                <th class="text-end">Gross sales</th>
                                <th class="text-end">Base fare</th>
                                <th class="text-end">Markup</th>
                                <th class="text-end">Service fee</th>
                                <th class="text-end">Net revenue</th>
                                <th class="text-end">Avg ticket</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($salesByPeriodData as $row)
                                <tr>
                                    <td class="fw-semibold">{{ $row['period'] ?? '' }}</td>
                                    <td class="text-end">{{ number_format((int) ($row['bookings'] ?? 0)) }}</td>
                                    <td class="text-end">{{ ota_money($row['gross_sales'] ?? 0) }}</td>
                                    <td class="text-end">{{ ota_money($row['base_fare'] ?? 0) }}</td>
                                    <td class="text-end">{{ ota_money($row['markup'] ?? 0) }}</td>
                                    <td class="text-end">{{ ota_money($row['service_fee'] ?? 0) }}</td>
                                    <td class="text-end fw-semibold text-success">{{ ota_money($row['net_revenue'] ?? 0) }}</td>
                                    <td class="text-end">{{ ota_money($row['average_ticket'] ?? 0) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="ota-empty" data-testid="ota-empty-sales">
                                    <div class="ota-empty-state">
                                        <div class="ota-empty-state-icon"><i class="ti ti-chart-line"></i></div>
                                        <div class="ota-empty-state-title">No sales recorded</div>
                                        <div class="ota-empty-state-help">Try changing the date range or clearing filters to see sales activity.</div>
                                    </div>
                                </td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- ============================================================== --}}
        {{-- PAYMENTS TAB --}}
        {{-- ============================================================== --}}
        <div class="{{ ota_tab_pane_class('payments', $current) }}" id="pane-payments" role="tabpanel" data-testid="ota-pane-payments">
            <div class="row g-3 mb-3 ota-kpi-responsive-row ota-kpi-responsive-row--four">
                @php
                    $paymentTiles = [
                        ['Outstanding balance', ota_money($financial['outstanding_balance'] ?? 0), 'Money still due'],
                        ['Refund paid', ota_money($financial['refund_paid'] ?? 0), 'Cash refunded'],
                        ['Unpaid / partial', number_format((int) ($operational['unpaid_partial_bookings'] ?? 0)), 'Bookings with balance'],
                        ['Pending refunds', number_format((int) ($sum['pending_refund_count'] ?? 0)), 'Awaiting payout'],
                    ];
                @endphp
                @foreach ($paymentTiles as [$label, $value, $helper])
                    <div class="col-12 col-sm-6 col-lg-3">
                        <div class="ota-kpi-tile">
                            <div class="ota-kpi-tile-label">{{ $label }}</div>
                            <div class="ota-kpi-tile-value">{{ $value }}</div>
                            <div class="ota-kpi-tile-helper">{{ $helper }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="ota-rep-card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h3 class="jp-card__title mb-0">Payments report</h3>
                    <a href="{{ route($reportsExportRoute, array_merge(['type' => 'payments'], request()->query())) }}" class="jp-btn jp-btn--sm jp-btn--outline">
                        <i class="ti ti-download me-1"></i> CSV
                    </a>
                </div>
                <div class="table-responsive admin-table-scroll">
                    <table class="jp-table ota-rep-table ota-admin-table mb-0">
                        <thead>
                            <tr>
                                <th>Booking</th><th>Customer</th><th>Route</th>
                                <th class="text-end">Total</th><th class="text-end">Paid</th><th class="text-end">Balance</th>
                                <th>Status</th><th>Method</th><th>Created</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($paymentRowsData as $row)
                                @php $href = route('admin.bookings', ['queue' => 'all', 'preview' => $row['preview_query']]); @endphp
                                <tr>
                                    <td class="fw-semibold">{{ $row['booking_ref'] }}</td>
                                    <td>{{ $row['customer'] }}</td>
                                    <td>{{ $row['route'] }}</td>
                                    <td class="text-end">{{ ota_money($row['total']) }}</td>
                                    <td class="text-end">{{ ota_money($row['paid']) }}</td>
                                    <td class="text-end fw-semibold">{{ ota_money($row['balance']) }}</td>
                                    <td class="text-capitalize">{{ $row['payment_status'] }}</td>
                                    <td class="text-capitalize">{{ $row['method'] }}</td>
                                    <td class="text-secondary small">{{ $row['created_at'] }}</td>
                                    <td class="text-end">
                                        <a href="{{ $href }}" class="jp-btn jp-btn--sm jp-btn--outline">Open</a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="10" class="ota-empty" data-testid="ota-empty-payments">
                                    <div class="ota-empty-state">
                                        <div class="ota-empty-state-icon"><i class="ti ti-credit-card"></i></div>
                                        <div class="ota-empty-state-title">No payment activity yet</div>
                                        <div class="ota-empty-state-help">Booking-level payment rows show paid amount, balance, and method once recorded.</div>
                                    </div>
                                </td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- ============================================================== --}}
        {{-- BOOKINGS TAB --}}
        {{-- ============================================================== --}}
        <div class="{{ ota_tab_pane_class('bookings', $current) }}" id="pane-bookings" role="tabpanel" data-testid="ota-pane-bookings">
            <div class="ota-rep-card mb-3">
                <div class="jp-card__head"><h3 class="jp-card__title mb-0">Booking pipeline</h3></div>
                <div class="jp-card__body">
                    <div class="ota-pipeline-grid">
                        @foreach ($pipelineCounts as $statusKey => $count)
                            <div class="ota-pipeline-tile">
                                <div class="pl-label">{{ ucwords(str_replace('_',' ', (string) $statusKey)) }}</div>
                                <div class="pl-value">{{ number_format((int) $count) }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="ota-rep-card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h3 class="jp-card__title mb-0">Bookings report</h3>
                    <a href="{{ route($reportsExportRoute, array_merge(['type' => 'bookings'], request()->query())) }}" class="jp-btn jp-btn--sm jp-btn--outline">
                        <i class="ti ti-download me-1"></i> CSV
                    </a>
                </div>
                <div class="table-responsive admin-table-scroll">
                    <table class="jp-table ota-rep-table ota-admin-table mb-0">
                        <thead>
                            <tr><th>Booking</th><th>Customer</th><th>Route</th><th>Travel</th><th>Status</th><th>Payment</th><th>Supplier</th><th>Ticketing</th><th class="text-end">Amount</th><th class="text-end">Action</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($pipelineRows as $row)
                                @php $href = route('admin.bookings', ['queue' => 'all', 'preview' => $row['preview_query']]); @endphp
                                <tr>
                                    <td class="fw-semibold">{{ $row['booking_ref'] }}</td>
                                    <td>{{ $row['customer'] }}</td>
                                    <td>{{ $row['route'] }}</td>
                                    <td class="text-secondary small">{{ $row['travel_date'] }}</td>
                                    <td class="text-capitalize">{{ $row['status'] }}</td>
                                    <td class="text-capitalize">{{ $row['payment_status'] }}</td>
                                    <td class="text-capitalize">{{ $row['supplier_status'] }}</td>
                                    <td class="text-capitalize">{{ $row['ticketing_status'] }}</td>
                                    <td class="text-end">{{ ota_money($row['amount']) }}</td>
                                    <td class="text-end"><a href="{{ $href }}" class="jp-btn jp-btn--sm jp-btn--outline">Open</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="10" class="ota-empty" data-testid="ota-empty-bookings">
                                    <div class="ota-empty-state">
                                        <div class="ota-empty-state-icon"><i class="ti ti-ticket"></i></div>
                                        <div class="ota-empty-state-title">No bookings for selected filters</div>
                                        <div class="ota-empty-state-help">Adjust date range or status filters to see bookings in the pipeline.</div>
                                    </div>
                                </td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- ============================================================== --}}
        {{-- SUPPLIERS TAB --}}
        {{-- ============================================================== --}}
        <div class="{{ ota_tab_pane_class('suppliers', $current) }}" id="pane-suppliers" role="tabpanel" data-testid="ota-pane-suppliers">
            <div class="ota-rep-card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h3 class="jp-card__title mb-0">Supplier performance</h3>
                    <a href="{{ route($reportsExportRoute, array_merge(['type' => 'supplier_diagnostics'], request()->query())) }}" class="jp-btn jp-btn--sm jp-btn--outline">
                        <i class="ti ti-download me-1"></i> CSV
                    </a>
                </div>
                <div class="table-responsive admin-table-scroll">
                    <table class="jp-table ota-rep-table ota-admin-table ota-prov-table mb-0">
                        <thead>
                            <tr>
                                <th>Provider</th><th>Status</th>
                                <th class="text-end">Searches</th><th class="text-end">Successful</th>
                                <th class="text-end">Validation fails</th><th class="text-end">Offer unavailable</th>
                                <th class="text-end">Errors</th>
                                <th class="text-end">PNRs</th><th class="text-end">Tickets</th>
                                <th>Last success</th><th>Last error</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($supplierPerf as $row)
                                <tr data-testid="ota-supplier-perf-{{ strtolower((string) ($row['provider_key'] ?? '')) }}">
                                    <td class="fw-semibold">{{ $row['provider'] }}</td>
                                    <td><span class="ota-prov-status ota-prov-status--{{ $row['status'] }}">{{ $row['status_label'] }}</span></td>
                                    <td class="text-end">{{ number_format((int) ($row['searches'] ?? 0)) }}</td>
                                    <td class="text-end">{{ number_format((int) ($row['successful_searches'] ?? 0)) }}</td>
                                    <td class="text-end">{{ number_format((int) ($row['validation_failures'] ?? 0)) }}</td>
                                    <td class="text-end">{{ number_format((int) ($row['offer_unavailable'] ?? 0)) }}</td>
                                    <td class="text-end">{{ number_format((int) ($row['errors'] ?? 0)) }}</td>
                                    <td class="text-end">{{ number_format((int) ($row['pnr_created'] ?? 0)) }}</td>
                                    <td class="text-end">{{ number_format((int) ($row['ticketing_success'] ?? 0)) }}</td>
                                    <td class="text-secondary small">{{ $row['last_success_at'] ?? '' }}</td>
                                    <td class="text-secondary small">
                                        @if (! empty($row['last_error_message']))
                                            <div title="{{ $row['last_error_message'] }}">{{ $row['last_error_at'] ?? '' }}</div>
                                        @else
                                            
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        @if ((int) ($row['errors'] ?? 0) > 0)
                                            <a href="{{ route('admin.reports.supplier-diagnostics', ['provider' => $row['provider_key'], 'status' => 'errors']) }}" class="btn btn-sm btn-outline-danger mb-1" data-testid="ota-view-supplier-errors-{{ $row['provider_key'] }}">
                                                View errors
                                            </a>
                                        @endif
                                        @if (! empty($row['connection_id']))
                                            <a href="{{ route('admin.api-settings.edit', ['supplierConnection' => $row['connection_id']]) }}" class="jp-btn jp-btn--sm jp-btn--outline">Manage</a>
                                        @else
                                            <a href="{{ route('admin.api-settings') }}" class="jp-btn jp-btn--sm jp-btn--ghost">Set up</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- ============================================================== --}}
        {{-- AGENTS TAB --}}
        {{-- ============================================================== --}}
        <div class="{{ ota_tab_pane_class('agents', $current) }}" id="pane-agents" role="tabpanel" data-testid="ota-pane-agents">
            <div class="ota-rep-card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h3 class="jp-card__title mb-0">Agent performance</h3>
                    <a href="{{ route($reportsExportRoute, array_merge(['type' => 'agents'], request()->query())) }}" class="jp-btn jp-btn--sm jp-btn--outline">
                        <i class="ti ti-download me-1"></i> CSV
                    </a>
                </div>
                <div class="table-responsive admin-table-scroll">
                    <table class="jp-table ota-rep-table ota-admin-table mb-0">
                        <thead>
                            <tr>
                                <th>Code</th><th>Agent</th><th class="text-end">Bookings</th>
                                <th class="text-end">Gross sales</th><th class="text-end">Net revenue</th>
                                <th class="text-end">Approved comm.</th><th class="text-end">Paid comm.</th><th class="text-end">Pending comm.</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($agentPerf as $row)
                                <tr>
                                    <td class="fw-semibold">{{ $row['agent_code'] }}</td>
                                    <td>{{ $row['agent_name'] }}</td>
                                    <td class="text-end">{{ number_format((int) $row['bookings']) }}</td>
                                    <td class="text-end">{{ ota_money($row['gross_sales']) }}</td>
                                    <td class="text-end">{{ ota_money($row['net_revenue']) }}</td>
                                    <td class="text-end">{{ ota_money($row['approved_commission']) }}</td>
                                    <td class="text-end text-success">{{ ota_money($row['paid_commission']) }}</td>
                                    <td class="text-end">{{ ota_money($row['pending_commission']) }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('admin.commissions.show', ['agent' => $row['agent_id']]) }}" class="jp-btn jp-btn--sm jp-btn--outline">Open</a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="9" class="ota-empty" data-testid="ota-empty-agents">
                                    <div class="ota-empty-state ota-r-text-safe">
                                        <div class="ota-empty-state-icon"><i class="ti ti-users-group"></i></div>
                                        <div class="ota-empty-state-title">No agent activity yet</div>
                                        <div class="ota-empty-state-help">Approved bookings made by agents will appear here with sales and commission breakdown.</div>
                                        <a href="{{ route('admin.agents') }}" class="jp-btn jp-btn--sm jp-btn--outline ota-empty-state-action">Manage agents</a>
                                    </div>
                                </td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- ============================================================== --}}
        {{-- ROUTES TAB --}}
        {{-- ============================================================== --}}
        <div class="{{ ota_tab_pane_class('routes', $current) }}" id="pane-routes" role="tabpanel" data-testid="ota-pane-routes">
            <div class="ota-rep-card">
                <div class="jp-card__head"><h3 class="jp-card__title mb-0">Route performance</h3></div>
                <div class="table-responsive admin-table-scroll">
                    <table class="jp-table ota-rep-table ota-admin-table mb-0">
                        <thead>
                            <tr>
                                <th>Route</th><th class="text-end">Bookings</th>
                                <th class="text-end">Gross sales</th><th class="text-end">Net revenue</th>
                                <th class="text-end">Avg ticket</th><th>Top airline</th>
                                <th class="text-end">Cancellations</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($routePerf as $row)
                                @php $href = route('admin.bookings', ['queue' => 'all', 'route' => $row['route']]); @endphp
                                <tr>
                                    <td class="fw-semibold">{{ $row['route'] }}</td>
                                    <td class="text-end">{{ number_format((int) $row['bookings']) }}</td>
                                    <td class="text-end">{{ ota_money($row['gross_sales']) }}</td>
                                    <td class="text-end">{{ ota_money($row['net_revenue']) }}</td>
                                    <td class="text-end">{{ ota_money($row['average_ticket']) }}</td>
                                    <td>{{ $row['top_airline'] }}</td>
                                    <td class="text-end">{{ number_format((int) $row['cancellations']) }}</td>
                                    <td class="text-end"><a href="{{ $href }}" class="jp-btn jp-btn--sm jp-btn--outline">Open</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="ota-empty" data-testid="ota-empty-routes">
                                    <div class="ota-empty-state">
                                        <div class="ota-empty-state-icon"><i class="ti ti-plane-departure"></i></div>
                                        <div class="ota-empty-state-title">No route data yet</div>
                                        <div class="ota-empty-state-help">Once flight bookings are confirmed, route performance with sales and cancellation counts will appear here.</div>
                                    </div>
                                </td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- ============================================================== --}}
        {{-- REFUNDS TAB --}}
        {{-- ============================================================== --}}
        <div class="{{ ota_tab_pane_class('refunds', $current) }}" id="pane-refunds" role="tabpanel" data-testid="ota-pane-refunds">
            <div class="row g-3 mb-3 ota-kpi-responsive-row ota-kpi-responsive-row--six" data-testid="ota-refund-kpis">
                @php
                    $rTiles = [
                        ['Cancellation requests', number_format((int) ($refundKpisData['cancellation_requests'] ?? 0)), 'Awaiting decision'],
                        ['Cancelled bookings', number_format((int) ($refundKpisData['cancelled_bookings'] ?? 0)), 'In selected period'],
                        ['Refund pending', number_format((int) ($refundKpisData['refund_pending'] ?? 0)), 'Submitted refunds'],
                        ['Refund approved', number_format((int) ($refundKpisData['refund_approved'] ?? 0)), 'Awaiting payout'],
                        ['Refund paid', ota_money($refundKpisData['refund_paid'] ?? 0), 'Cash returned'],
                        ['Refund liability', ota_money($refundKpisData['refund_liability'] ?? 0), 'Outstanding refunds'],
                    ];
                @endphp
                @foreach ($rTiles as [$label, $value, $helper])
                    <div class="col-12 col-sm-6 col-md-4">
                        <div class="ota-kpi-tile">
                            <div class="ota-kpi-tile-label">{{ $label }}</div>
                            <div class="ota-kpi-tile-value">{{ $value }}</div>
                            <div class="ota-kpi-tile-helper">{{ $helper }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="ota-rep-card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h3 class="jp-card__title mb-0">Refunds &amp; cancellations</h3>
                    <a href="{{ route($reportsExportRoute, array_merge(['type' => 'refunds'], request()->query())) }}" class="jp-btn jp-btn--sm jp-btn--outline">
                        <i class="ti ti-download me-1"></i> CSV
                    </a>
                </div>
                <div class="table-responsive admin-table-scroll">
                    <table class="jp-table ota-rep-table ota-admin-table mb-0">
                        <thead>
                            <tr>
                                <th>Booking</th><th>Customer</th><th>Route</th>
                                <th class="text-end">Paid</th><th class="text-end">Refund</th>
                                <th>Refund status</th><th>Cancellation</th><th>Created</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($refundRowsData as $row)
                                @php $href = route('admin.bookings', ['queue' => 'all', 'preview' => $row['preview_query']]); @endphp
                                <tr>
                                    <td class="fw-semibold">{{ $row['booking_ref'] }}</td>
                                    <td>{{ $row['customer'] }}</td>
                                    <td>{{ $row['route'] }}</td>
                                    <td class="text-end">{{ ota_money($row['paid_amount']) }}</td>
                                    <td class="text-end">{{ ota_money($row['refund_amount']) }}</td>
                                    <td class="text-capitalize">{{ $row['refund_status'] }}</td>
                                    <td class="text-capitalize">{{ $row['cancellation_status'] }}</td>
                                    <td class="text-secondary small">{{ $row['created_at'] }}</td>
                                    <td class="text-end"><a href="{{ $href }}" class="jp-btn jp-btn--sm jp-btn--outline">Open</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="9" class="ota-empty" data-testid="ota-empty-refunds">
                                    <div class="ota-empty-state">
                                        <div class="ota-empty-state-icon"><i class="ti ti-receipt-refund"></i></div>
                                        <div class="ota-empty-state-title">No refund activity</div>
                                        <div class="ota-empty-state-help">Refund and cancellation rows will appear here once requests are submitted by customers or staff.</div>
                                    </div>
                                </td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- ============================================================== --}}
        {{-- DOCUMENTS TAB --}}
        {{-- ============================================================== --}}
        <div class="{{ ota_tab_pane_class('documents', $current) }}" id="pane-documents" role="tabpanel" data-testid="ota-pane-documents">
            <div class="row g-3 mb-3 ota-kpi-responsive-row ota-kpi-responsive-row--five" data-testid="ota-document-kpis">
                @php
                    $dTiles = [
                        ['Invoices generated', number_format((int) ($documentKpisData['invoices_generated'] ?? 0)), 'Issued in period'],
                        ['Invoices missing', number_format((int) ($documentKpisData['invoices_missing'] ?? 0)), 'Bookings w/o invoice'],
                        ['Receipts generated', number_format((int) ($documentKpisData['receipts_generated'] ?? 0)), 'Payment receipts'],
                        ['Itineraries generated', number_format((int) ($documentKpisData['itineraries_generated'] ?? 0)), 'Ticket itineraries'],
                        ['Failed documents', number_format((int) ($documentKpisData['failed_documents'] ?? 0)), 'Need regeneration'],
                    ];
                @endphp
                @foreach ($dTiles as [$label, $value, $helper])
                    <div class="col-12 col-sm-6 col-md-4">
                        <div class="ota-kpi-tile">
                            <div class="ota-kpi-tile-label">{{ $label }}</div>
                            <div class="ota-kpi-tile-value">{{ $value }}</div>
                            <div class="ota-kpi-tile-helper">{{ $helper }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="ota-rep-card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h3 class="jp-card__title mb-0">Documents</h3>
                    <a href="{{ route($reportsExportRoute, array_merge(['type' => 'documents'], request()->query())) }}" class="jp-btn jp-btn--sm jp-btn--outline">
                        <i class="ti ti-download me-1"></i> CSV
                    </a>
                </div>
                <div class="table-responsive admin-table-scroll">
                    <table class="jp-table ota-rep-table ota-admin-table mb-0">
                        <thead>
                            <tr><th>Booking</th><th>Document type</th><th>Status</th><th>Generated</th><th>Sent</th><th class="text-end">Action</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($documentRowsData as $row)
                                @php $href = route('admin.bookings', ['queue' => 'all', 'preview' => $row['preview_query']]); @endphp
                                <tr>
                                    <td class="fw-semibold">{{ $row['booking_ref'] }}</td>
                                    <td class="text-capitalize">{{ $row['document_type'] }}</td>
                                    <td class="text-capitalize">{{ $row['status'] }}</td>
                                    <td class="text-secondary small">{{ $row['generated_at'] }}</td>
                                    <td class="text-secondary small">{{ $row['sent_at'] }}</td>
                                    <td class="text-end"><a href="{{ $href }}" class="jp-btn jp-btn--sm jp-btn--outline">Open</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="ota-empty" data-testid="ota-empty-documents">
                                    <div class="ota-empty-state">
                                        <div class="ota-empty-state-icon"><i class="ti ti-file-text"></i></div>
                                        <div class="ota-empty-state-title">No documents generated yet</div>
                                        <div class="ota-empty-state-help">Generated invoices, receipts and itineraries from the booking module will be listed here.</div>
                                    </div>
                                </td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- ============================================================== --}}
        {{-- EXPORTS TAB --}}
        {{-- ============================================================== --}}
        <div class="{{ ota_tab_pane_class('exports', $current) }}" id="pane-exports" role="tabpanel" data-testid="ota-pane-exports">
            <div class="row g-3">
                @php
                    $exports = [
                        ['Sales CSV', 'Period totals: bookings, gross, base, markup, fees, net, avg', 'sales', 'ti-coin'],
                        ['Payments CSV', 'Per-booking totals, paid, balance, status, method', 'payments', 'ti-cash'],
                        ['Bookings CSV', 'Pipeline rows with status, payment, supplier, ticketing', 'bookings', 'ti-ticket'],
                        ['Agent commissions CSV', 'Agent sales and approved/paid/pending commissions', 'agents', 'ti-users'],
                        ['Refunds CSV', 'Refund rows: paid amount, refund amount, statuses', 'refunds', 'ti-receipt-refund'],
                        ['Supplier diagnostics CSV', 'Per-provider counts: searches, errors, PNRs, tickets', 'supplier_diagnostics', 'ti-plug-connected'],
                        ['Documents CSV', 'Generated documents per booking', 'documents', 'ti-file-text'],
                    ];
                @endphp
                @foreach ($exports as [$label, $helper, $type, $icon])
                    <div class="col-md-6 col-lg-4">
                        <div class="ota-export-card" data-testid="ota-export-card-{{ $type }}">
                            <div class="ota-export-icon"><i class="ti {{ $icon }}"></i></div>
                            <div class="fw-bold">{{ $label }}</div>
                            <div class="text-secondary small mt-1 mb-3">{{ $helper }}</div>
                            <a href="{{ route($reportsExportRoute, array_merge(['type' => $type], request()->query())) }}" class="jp-btn jp-btn--sm jp-btn--primary">
                                <i class="ti ti-download me-1"></i> Download CSV
                            </a>
                        </div>
                    </div>
                @endforeach
                <div class="col-12">
                    <p class="text-secondary small mb-0" data-testid="ota-export-card-pdf-note">
                        PDF reports are not enabled yet. Use CSV exports above for sales and payments data.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

