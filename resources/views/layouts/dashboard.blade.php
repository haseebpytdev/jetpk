@php
    use App\Support\Branding\BrandDisplayResolver;

    $tabler = asset('vendor/tabler');
    $dbSettings = $agencySettings ?? ($publicBranding['settings'] ?? null);
    $dashProductName = BrandDisplayResolver::displayName($dbSettings, auth()->user());
    $brandCssVariables = $brandCssVariables ?? BrandDisplayResolver::cssVariables($dbSettings);
    $dashFaviconUrl = null;
    if (is_client_preview()) {
        $previewDashBranding = \App\Support\Client\ClientPreviewLayoutBranding::apply(
            $dashProductName,
            '',
            $brandCssVariables,
            null,
            false,
            null,
            [],
            [],
        );
        $dashProductName = $previewDashBranding['brandName'];
        $brandCssVariables = $previewDashBranding['brandCssVariables'];
        $dashFaviconUrl = $previewDashBranding['faviconUrl'] ?? null;
        $clientThemeMeta = $previewDashBranding['clientThemeMeta'] ?? [];
    } else {
        $clientThemeMeta = [];
    }
    $pageTitleSection = trim($__env->yieldContent('title'));
    $documentTitle = $pageTitleSection !== ''
        ? BrandDisplayResolver::pageTitle($pageTitleSection, $dashProductName)
        : $dashProductName;
    /**
     * Sidebar area: URL prefix first (each portal lives under its own path).
     * If the path has no known prefix (future shared dashboard routes), fall back to the
     * signed-in user's account type so staff/agent/customer never default to the admin menu.
     */
    $relativePath = client_relative_path();
    $urlArea = match (true) {
        str_starts_with($relativePath, 'admin') => 'admin',
        str_starts_with($relativePath, 'staff') => 'staff',
        str_starts_with($relativePath, 'agent') => 'agent',
        str_starts_with($relativePath, 'customer') => 'customer',
        str_starts_with($relativePath, 'guest') => 'guest',
        request()->is('admin*') => 'admin',
        request()->is('staff*') => 'staff',
        request()->is('agent*') => 'agent',
        request()->is('customer*') => 'customer',
        request()->is('guest*') => 'guest',
        default => null,
    };

    if ($urlArea !== null) {
        $dashArea = $urlArea;
    } elseif (auth()->check()) {
        $u = auth()->user();
        $dashArea = match (true) {
            $u->isPlatformAdmin() => 'admin',
            $u->isStaff() => 'staff',
            $u->isAgent() => 'agent',
            $u->isCustomer() => 'customer',
            default => 'customer',
        };
    } else {
        $dashArea = 'guest';
    }

    $dashHomeUrl = match ($dashArea) {
        'staff' => client_route('staff.dashboard'),
        'agent' => client_route('agent.dashboard'),
        'customer' => client_route('customer.dashboard'),
        'guest' => client_route('home'),
        default => client_route('admin.dashboard'),
    };
    $dashSubtitle = match ($dashArea) {
        'staff' => 'Staff',
        'agent' => 'Agent',
        'customer' => 'Customer',
        'guest' => 'Guest access',
        default => 'Operator Console',
    };
    $isOpsConsole = in_array($dashArea, ['admin', 'staff'], true);
@endphp
<!doctype html>
<html @class(['ui-version-v1' => ($currentUiVersion ?? 'v1') === 'v1', 'ui-version-v2' => ($currentUiVersion ?? 'v1') === 'v2']) lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
    <meta http-equiv="X-UA-Compatible" content="ie=edge"/>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $documentTitle }}</title>
    @if(is_client_preview() && ($clientThemeMeta['admin_theme'] ?? '') !== '')
        <meta name="ota-client-slug" content="{{ current_client_slug() }}">
        <meta name="ota-client-admin-theme" content="{{ $clientThemeMeta['admin_theme'] }}">
        <meta name="ota-client-asset-profile" content="{{ $clientThemeMeta['asset_profile'] ?? '' }}">
    @endif

    {{-- Tabler core (from @tabler/core dist, copied to public/vendor/tabler) --}}
    <link href="{{ $tabler }}/css/tabler.min.css" rel="stylesheet"/>
    <link href="{{ $tabler }}/css/tabler-flags.min.css" rel="stylesheet"/>
    <link rel="stylesheet" href="{{ ui_asset('css/ota-design-system.css') }}" />
    @if ($isOpsConsole)
        <link rel="stylesheet" href="{{ ui_asset('css/ota-admin-console.css') }}" />
    @endif
    <style>
        :root {
            @foreach ($brandCssVariables as $cssVar => $cssValue)
            {{ $cssVar }}: {{ $cssValue }};
            @endforeach
        }
        /* Slightly larger body copy than Tabler defaults */
        body { font-size: 16px; line-height: 1.55; }
        .text-muted, small { font-size: 0.9rem; }
    </style>

    {{-- Icons: vendorize into public/vendor/tabler/icons by copying @tabler/icons-webfont dist when offline --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.40.0/dist/tabler-icons.min.css"/>

    <style>
        /* Consistent disabled controls */
        .page .btn:disabled,
        .page .btn.disabled,
        .page fieldset:disabled .btn {
            opacity: 1;
            background: #e2e8f0 !important;
            border-color: #cbd5e1 !important;
            color: #475569 !important;
            cursor: not-allowed !important;
            box-shadow: none !important;
        }
        .page .form-control:disabled,
        .page .form-select:disabled {
            opacity: 0.65;
            cursor: not-allowed;
        }
        .ops-admin-banner {
            border-left: 4px solid var(--tblr-warning, #f59e0b);
        }
        /* Operator console — branded polish */
        .ota-sidebar-refined .navbar-brand {
            padding-bottom: 0.75rem;
            margin-bottom: 0.25rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }
        .ota-sidebar-refined .nav-link {
            border-radius: 8px;
            margin: 0.06rem 0.3rem;
            padding: 0.38rem 0.55rem !important;
            font-size: 0.8125rem;
            line-height: 1.35;
            min-width: 0;
            overflow-wrap: anywhere;
        }
        .ota-sidebar-compact > .nav-item {
            margin-bottom: 0.02rem;
        }
        .ota-sidebar-refined .nav-link.ota-sidebar-group {
            font-size: 0.8125rem;
            font-weight: 600;
            padding: 0.34rem 0.5rem !important;
        }
        .ota-sidebar-refined .nav-link.ota-sidebar-parent-active:not(.active) {
            background: rgba(37, 99, 235, 0.12) !important;
            color: rgba(255, 255, 255, 0.92) !important;
        }
        .ota-sidebar-refined .nav-link-icon {
            width: 1.35rem;
            margin-right: 0.45rem;
            font-size: 1rem;
        }
        .ota-sidebar-refined .nav-link.ota-sidebar-utility {
            opacity: 0.82;
            font-size: 0.78rem;
        }
        .ota-sidebar-refined .nav-link.active {
            background: color-mix(in srgb, var(--brand-primary) 22%, transparent) !important;
            color: #fff !important;
        }
        .page .btn-primary,
        .page .btn.btn-primary {
            background-color: var(--brand-primary) !important;
            border-color: var(--brand-primary) !important;
        }
        .page .btn-primary:hover,
        .page .btn.btn-primary:hover {
            background-color: var(--brand-primary-dark) !important;
            border-color: var(--brand-primary-dark) !important;
        }
        .ota-sidebar-refined .nav-link:hover:not(.disabled) {
            background: rgba(255, 255, 255, 0.06);
        }
        .ota-submenu {
            margin: 0 0 0.15rem 1.65rem;
            padding: 0.05rem 0 0.1rem 0.55rem;
            list-style: none;
            border-left: 1px solid rgba(255, 255, 255, 0.1);
        }
        .ota-submenu .nav-link {
            margin: 0.01rem 0;
            padding: 0.22rem 0.45rem !important;
            font-size: 0.75rem;
            border-radius: 6px;
            color: rgba(255, 255, 255, 0.68);
        }
        .ota-submenu .nav-link.active {
            background: rgba(14, 165, 233, 0.2) !important;
            color: #fff !important;
        }
        .ota-submenu-nested {
            list-style: none;
        }
        .ota-submenu .ota-submenu-toggle {
            display: flex;
            align-items: center;
            cursor: pointer;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.78);
        }
        .ota-submenu .ota-submenu-toggle .nav-link-title {
            flex: 1;
            min-width: 0;
        }
        .ota-submenu .ota-submenu-toggle .ota-nav-caret {
            font-size: 0.82rem;
        }
        .ota-submenu-sub {
            margin-left: 0.35rem;
            padding-left: 0.5rem;
            border-left: 1px solid rgba(255, 255, 255, 0.08);
        }
        .ota-submenu-sub .nav-link {
            font-size: 0.71875rem;
            padding: 0.18rem 0.4rem 0.18rem 0.55rem !important;
            color: rgba(255, 255, 255, 0.62);
            position: relative;
        }
        .ota-submenu-sub .nav-link::before {
            content: '';
            position: absolute;
            left: 0.15rem;
            top: 50%;
            width: 3px;
            height: 3px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.35);
            transform: translateY(-50%);
        }
        .ota-submenu-sub .nav-link.active::before {
            background: rgba(14, 165, 233, 0.9);
        }
        .ota-sidebar-refined .nav-link[data-bs-toggle="collapse"] {
            display: flex;
            align-items: center;
            cursor: pointer;
        }
        .ota-sidebar-refined .nav-link[data-bs-toggle="collapse"] .nav-link-title {
            flex: 1;
            min-width: 0;
        }
        .ota-nav-caret {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-left: auto;
            flex-shrink: 0;
            font-size: 0.88rem;
            line-height: 1;
            opacity: 0.7;
            transition: transform 0.18s ease;
        }
        .ota-sidebar-refined .nav-link[aria-expanded="true"] .ota-nav-caret,
        .ota-submenu .ota-submenu-toggle[aria-expanded="true"] .ota-nav-caret {
            transform: rotate(180deg);
        }
        .ota-sidebar-pill {
            display: inline-flex;
            align-items: center;
            padding: 0.12rem 0.45rem;
            border-radius: 999px;
            font-size: 0.58rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            background: rgba(37, 99, 235, 0.22);
            color: rgba(255, 255, 255, 0.72);
            border: 1px solid rgba(255, 255, 255, 0.14);
            flex-shrink: 0;
        }
        .ota-sidebar-section {
            padding: 0.5rem 0.75rem 0.2rem;
            margin-top: 0.35rem;
            font-size: 0.62rem;
            font-weight: 700;
            letter-spacing: 0.11em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.58);
        }
        .ota-sidebar-section:first-child {
            margin-top: 0;
        }
        .ota-admin-welcome {
            border: 1px solid rgba(37, 99, 235, 0.2);
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.06) 0%, rgba(14, 165, 233, 0.05) 100%);
            box-shadow: 0 4px 24px rgba(15, 23, 42, 0.06);
        }
        .ota-admin-welcome--compact {
            box-shadow: 0 2px 14px rgba(15, 23, 42, 0.05);
        }
        .ota-admin-welcome-body {
            padding: 22px 24px !important;
        }
        .ota-admin-welcome .card-title,
        .ota-admin-welcome-title {
            font-weight: 800;
            letter-spacing: -0.02em;
        }
        .ota-admin-welcome-title {
            font-size: 1.05rem;
            line-height: 1.3;
        }
        .ota-monthly-overview-head {
            letter-spacing: -0.01em;
            color: var(--tblr-body-color, #1e293b);
        }
        .ota-welcome-avatar {
            width: 2.65rem !important;
            height: 2.65rem !important;
            min-width: 2.65rem !important;
        }
        .ota-admin-welcome--compact .ota-welcome-avatar {
            width: 2.35rem !important;
            height: 2.35rem !important;
            min-width: 2.35rem !important;
        }
        .ota-welcome-avatar .ti {
            font-size: 1.35rem !important;
        }
        .ota-admin-welcome--compact .ota-welcome-avatar .ti {
            font-size: 1.15rem !important;
        }
        .ota-kpi-card {
            border: 1px solid var(--tblr-border-color, rgba(98, 105, 118, 0.16));
            border-top: 3px solid #2563eb;
            box-shadow: 0 2px 12px rgba(15, 23, 42, 0.04);
        }
        .ota-kpi-accent-amber {
            border-top-color: #f59e0b !important;
        }
        .ota-kpi-accent-emerald {
            border-top-color: #10b981 !important;
        }
        .ota-kpi-accent-violet {
            border-top-color: #8b5cf6 !important;
        }
        .ota-kpi-card .text-secondary {
            font-size: 0.75rem;
            line-height: 1.5;
            font-weight: 400;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .ota-kpi-card .h2 {
            font-weight: 800;
            letter-spacing: -0.02em;
        }
        .ota-admin-table thead th {
            font-size: 0.68rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            font-weight: 700;
            color: var(--tblr-secondary, #62748e);
            border-bottom-width: 2px;
        }
        .ota-admin-table tbody tr:hover {
            background: rgba(37, 99, 235, 0.04);
        }
        .ota-admin-click-row {
            cursor: pointer;
            transition: background-color 0.15s ease;
        }
        .ota-admin-click-row:hover {
            background: #f8fafc;
        }
        .ota-admin-click-row:focus-visible {
            outline: 2px solid rgba(59, 130, 246, 0.45);
            outline-offset: -2px;
        }
        .ota-admin-quick .ota-quick-action-card {
            border: 1px solid var(--tblr-border-color, rgba(98, 105, 118, 0.16));
            transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
            cursor: pointer;
        }
        .ota-quick-action-link {
            display: block;
            outline: none;
        }
        .ota-quick-action-link:focus-visible .ota-quick-action-card {
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.35);
        }
        .ota-admin-quick a:hover .ota-quick-action-card {
            transform: translateY(-3px);
            box-shadow: 0 12px 32px rgba(15, 23, 42, 0.14);
            border-color: rgba(37, 99, 235, 0.45);
        }
        .ota-admin-quick a:active .ota-quick-action-card {
            transform: translateY(-1px);
        }
        .ota-admin-quick .ota-quick-icon {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.12), rgba(14, 165, 233, 0.1));
            color: #2563eb;
        }
        .ota-recent-card-head {
            padding-top: 1.1rem !important;
            padding-bottom: 0.85rem !important;
            padding-left: 1.25rem;
            padding-right: 1.25rem;
        }
        .ota-recent-head {
            margin: 0;
            padding: 0;
        }
        .ota-recent-head-title {
            display: block;
            font-size: 1rem;
            font-weight: 600;
            margin: 0 0 0.5rem;
            letter-spacing: 0;
            line-height: 1.4;
            color: var(--tblr-body-color, #1e293b);
        }
        .ota-recent-head-sub {
            line-height: 1.5;
            font-size: 0.875rem;
            font-weight: 400;
            margin: 0;
        }
        .ota-recent-head-sub-line {
            display: inline-block;
            margin-right: 0.35rem;
        }
        .ota-recent-source {
            display: inline-block;
            font-weight: 800;
            font-size: 0.82rem;
            letter-spacing: 0.02em;
            color: var(--tblr-body-color, #1e293b);
            background: rgba(37, 99, 235, 0.08);
            border: 1px solid rgba(37, 99, 235, 0.18);
            border-radius: 6px;
            padding: 0.12rem 0.45rem;
        }
        .ota-recent-bookings-card .table-responsive {
            border-top: 1px solid var(--tblr-border-color, rgba(98, 105, 118, 0.12));
        }
        .ota-recent-code {
            font-size: 0.8rem;
            padding: 0.1rem 0.35rem;
            border-radius: 4px;
            background: rgba(37, 99, 235, 0.08);
            color: inherit;
        }
        .ota-supplier-status {
            display: inline-flex;
            align-items: center;
            padding: 0.28rem 0.65rem;
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.03em;
            border: 1px solid #e2e8f0;
            white-space: nowrap;
            background: #f1f5f9;
            color: #334155;
        }
        .ota-supplier-status--unknown {
            background: #f1f5f9;
            color: #475569;
            border-color: #cbd5e1;
        }
        .ota-supplier-status--pending {
            background: #fef3c7;
            color: #78350f;
            border-color: #fcd34d;
        }
        .ota-supplier-status--not-configured {
            background: #f1f5f9;
            color: #475569;
            border-color: #e2e8f0;
        }
        .ota-supplier-status--live {
            background: #d1fae5;
            color: #065f46;
            border-color: #6ee7b7;
        }
        .ota-supplier-status--configured {
            background: #e0f2fe;
            color: #075985;
            border-color: #7dd3fc;
        }
        .ota-supplier-status--connected {
            background: #ecfdf5;
            color: #14532d;
            border-color: #6ee7b7;
        }
        .ota-bstat {
            display: inline-flex;
            align-items: center;
            padding: 0.28rem 0.6rem;
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            border: 1px solid #e2e8f0;
            background: #f1f5f9;
            color: #334155;
        }
        .ota-bstat--confirmed {
            background: #dbeafe;
            color: #1e3a8a;
            border-color: #3b82f6;
        }
        .ota-bstat--pending {
            background: #fef9c3;
            color: #78350f;
            border-color: #eab308;
        }
        .ota-bstat--ticketed {
            background: #d1fae5;
            color: #14532d;
            border-color: #22c55e;
        }
        .ota-bstat--cancelled {
            background: #fee2e2;
            color: #7f1d1d;
            border-color: #ef4444;
        }
        .ota-bstat--muted {
            background: #f1f5f9;
            color: #475569;
            border-color: #e2e8f0;
        }
        /* Admin overview — compact above-the-fold zone */
        .ota-admin-page-head .page-title {
            font-size: 1.35rem;
        }
        /* Unified dashboard overview — visual system (admin first; reuse on other portals) */
        .page-body:has(.ota-dash-overview) {
            background: #f1f5f9;
        }
        .page-body:has(.ota-dash-overview) .container-xl {
            padding-top: 0.15rem;
        }
        .ota-dash-overview {
            max-width: 100%;
        }
        .ota-dash-overview-head .page-title {
            font-size: 1.5rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: #0f172a;
        }
        .ota-dash-overview-subtitle {
            font-size: 0.9rem;
            color: #64748b;
            margin-top: 0.2rem;
        }
        .ota-dash-notice {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem 1rem;
            flex-wrap: wrap;
            padding: 0.6rem 1rem;
            border-radius: 10px;
            border: 1px solid #fde68a;
            background: #fffbeb;
            color: #92400e;
            font-size: 0.84rem;
            line-height: 1.45;
        }
        .ota-dash-notice__text {
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
            flex: 1 1 16rem;
            min-width: 0;
        }
        .ota-dash-notice__text i {
            flex-shrink: 0;
            margin-top: 0.12rem;
            font-size: 1rem;
            color: #d97706;
        }
        .ota-dash-notice__badge {
            flex-shrink: 0;
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            padding: 0.28rem 0.6rem;
            border-radius: 999px;
            background: #dbeafe;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
        }
        .ota-dash-section-title {
            font-size: 1rem;
            line-height: 1.4;
            font-weight: 600;
            color: #0f172a;
            letter-spacing: 0;
        }
        .ota-dash-action-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 0.75rem;
            max-width: 100%;
        }
        .ota-dash-action-card {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
            padding: 0.85rem 0.9rem;
            min-height: 9.5rem;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            border-left-width: 1px;
            background: #fff;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
            text-decoration: none;
            color: inherit;
            min-width: 0;
            transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
        }
        .ota-dash-action-card:hover {
            color: inherit;
            text-decoration: none;
            transform: translateY(-1px);
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
        }
        .ota-dash-action-card--priority {
            border-left: 4px solid #f59e0b;
        }
        .ota-dash-action-card--hot {
            border-color: #fcd34d;
            border-left-color: #f59e0b;
            border-left-width: 4px;
            background: #fff;
            box-shadow: 0 4px 14px rgba(245, 158, 11, 0.14);
        }
        .ota-dash-action-card__icon {
            width: 2rem;
            height: 2rem;
            border-radius: 9px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.05rem;
        }
        .ota-dash-action-card--amber .ota-dash-action-card__icon { background: rgba(245, 158, 11, 0.14); color: #b45309; }
        .ota-dash-action-card--violet .ota-dash-action-card__icon { background: rgba(139, 92, 246, 0.14); color: #6d28d9; }
        .ota-dash-action-card--emerald .ota-dash-action-card__icon { background: rgba(16, 185, 129, 0.14); color: #047857; }
        .ota-dash-action-card--blue .ota-dash-action-card__icon { background: rgba(37, 99, 235, 0.14); color: #1d4ed8; }
        .ota-dash-action-card--rose .ota-dash-action-card__icon { background: rgba(244, 63, 94, 0.14); color: #be123c; }
        .ota-dash-action-card--teal .ota-dash-action-card__icon { background: rgba(20, 184, 166, 0.14); color: #0f766e; }
        .ota-dash-action-card--danger .ota-dash-action-card__icon { background: rgba(239, 68, 68, 0.14); color: #b91c1c; }
        .ota-dash-action-card--muted .ota-dash-action-card__icon { background: rgba(100, 116, 139, 0.14); color: #475569; }
        .ota-dash-action-card__count {
            font-size: 1.45rem;
            font-weight: 800;
            line-height: 1.1;
            letter-spacing: -0.02em;
            color: #0f172a;
        }
        .ota-dash-action-card__label {
            font-size: 1rem;
            line-height: 1.4;
            font-weight: 600;
            letter-spacing: 0;
            color: #1e293b;
        }
        .ota-dash-action-card__helper {
            margin: 0;
            font-size: 0.875rem;
            color: #64748b;
            line-height: 1.5;
            font-weight: 400;
        }
        .ota-dash-action-card__cta {
            align-self: flex-start;
            margin-top: auto;
            font-size: 0.72rem;
            font-weight: 600;
            padding: 0.28rem 0.65rem;
            border-radius: 8px;
            pointer-events: none;
        }
        .ota-dash-action-card__cta--primary {
            background: #f97316;
            border: 1px solid #ea580c;
            color: #fff;
        }
        .ota-dash-action-card__cta--soft {
            background: #fff7ed;
            border: 1px solid #fdba74;
            color: #c2410c;
        }
        .ota-dash-shortcuts {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            max-width: 100%;
        }
        .ota-dash-shortcut {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.45rem 0.85rem;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            background: #fff;
            color: #334155;
            font-size: 0.78rem;
            font-weight: 600;
            text-decoration: none;
            max-width: 100%;
            min-width: 0;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        }
        .ota-dash-shortcut:hover {
            color: #1d4ed8;
            border-color: #cbd5e1;
            background: #fff;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06);
            text-decoration: none;
        }
        .ota-dash-shortcut i { font-size: 1rem; color: #64748b; }
        .ota-dash-panel {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
            background: #fff;
        }
        .ota-dash-panel .card-header {
            background: transparent;
        }
        .ota-dash-activity-item {
            display: flex;
            align-items: flex-start;
            gap: 0.65rem;
            padding: 0.55rem 0;
            border-bottom: 1px solid rgba(148, 163, 184, 0.2);
            min-width: 0;
        }
        .ota-dash-activity-item:last-child { border-bottom: 0; }
        .ota-dash-activity-time {
            font-size: 0.75rem;
            line-height: 1.5;
            font-weight: 400;
            color: #94a3b8;
        }
        .ota-dash-activity-icon {
            width: 2rem;
            height: 2rem;
            border-radius: 10px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            color: #475569;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 1rem;
        }
        .ota-dash-activity-label {
            display: block;
            font-size: 1rem;
            line-height: 1.4;
            font-weight: 600;
            letter-spacing: 0;
            color: #0f172a;
            overflow-wrap: anywhere;
        }
        .ota-dash-activity-meta {
            display: block;
            font-size: 0.75rem;
            line-height: 1.5;
            font-weight: 400;
            color: #64748b;
            overflow-wrap: anywhere;
        }
        .ota-dash-status-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 0.55rem 0;
            border-bottom: 1px solid rgba(148, 163, 184, 0.2);
            min-width: 0;
        }
        .ota-dash-status-row:last-of-type { border-bottom: 0; }
        .ota-dash-status-label {
            font-size: 1rem;
            line-height: 1.4;
            font-weight: 600;
            letter-spacing: 0;
            color: #0f172a;
        }
        .ota-dash-status-meta {
            font-size: 0.75rem;
            line-height: 1.5;
            font-weight: 400;
            color: #64748b;
        }
        .ota-dash-status-badge {
            flex-shrink: 0;
            font-size: 0.68rem;
            font-weight: 700;
            padding: 0.2rem 0.55rem;
            border-radius: 999px;
            border: 1px solid transparent;
            white-space: nowrap;
        }
        .ota-dash-status-badge--good { background: #d1fae5; color: #065f46; border-color: #6ee7b7; }
        .ota-dash-status-badge--warn { background: #fef3c7; color: #92400e; border-color: #fcd34d; }
        .ota-dash-status-badge--muted { background: #f1f5f9; color: #475569; border-color: #e2e8f0; }
        @media (max-width: 1399.98px) {
            .ota-dash-action-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
        }
        @media (max-width: 1199.98px) {
            .ota-dash-action-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }
        @media (max-width: 991.98px) {
            .ota-dash-action-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        @media (max-width: 575.98px) {
            .ota-dash-action-grid {
                grid-template-columns: minmax(0, 1fr);
            }
            .ota-dash-shortcut {
                flex: 1 1 calc(50% - 0.25rem);
                justify-content: center;
            }
        }
        .ota-admin-overview {
            max-width: 100%;
        }
        .ota-admin-detail {
            padding-top: 0.25rem;
            border-top: 1px solid var(--tblr-border-color, rgba(98, 105, 118, 0.14));
        }
        /* Operations command banner */
        .ota-command-banner {
            background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 55%, #0c4a6e 100%);
            border: 1px solid rgba(15, 23, 42, 0.2);
            border-radius: 14px;
            color: #e2e8f0;
            box-shadow: 0 18px 36px rgba(15, 23, 42, 0.18);
            padding: 22px 26px;
        }
        .ota-command-banner--compact {
            padding: 14px 18px;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.14);
        }
        .ota-command-banner--compact .ota-cb-headline {
            font-size: 1.15rem;
            margin-bottom: 0.35rem;
        }
        .ota-command-banner .ota-cb-title {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: rgba(255, 255, 255, 0.65);
            font-weight: 700;
            margin: 0 0 0.35rem;
        }
        .ota-command-banner .ota-cb-headline {
            font-size: 1.45rem;
            font-weight: 800;
            color: #fff;
            margin: 0 0 0.5rem;
            letter-spacing: -0.02em;
        }
        .ota-command-banner .ota-cb-summary {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem 1rem;
            color: rgba(226, 232, 240, 0.92);
            font-size: 0.92rem;
            margin: 0;
        }
        .ota-command-banner .ota-cb-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.14);
            border-radius: 999px;
            padding: 0.2rem 0.7rem;
            font-size: 0.82rem;
        }
        .ota-command-banner .ota-cb-chip i { font-size: 0.95rem; }
        .ota-command-banner .ota-cb-chip--good { background: rgba(16, 185, 129, 0.18); border-color: rgba(110, 231, 183, 0.35); color: #ecfdf5; }
        .ota-command-banner .ota-cb-chip--warn { background: rgba(245, 158, 11, 0.18); border-color: rgba(252, 211, 77, 0.4); color: #fffbeb; }
        .ota-command-banner .ota-cb-chip--alert { background: rgba(239, 68, 68, 0.18); border-color: rgba(252, 165, 165, 0.4); color: #fef2f2; }
        .ota-command-banner .btn {
            font-weight: 600;
        }
        .ota-command-banner .ota-cb-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            max-width: 100%;
            min-width: 0;
        }
        .ota-command-banner .ota-cb-actions .btn {
            flex: 1 1 auto;
            max-width: 100%;
            white-space: normal;
        }
        /* Operational KPI cards */
        .ota-op-kpi {
            border: 1px solid var(--tblr-border-color, rgba(98, 105, 118, 0.16));
            border-radius: 12px;
            background: #fff;
            transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
            display: block;
            text-decoration: none;
            color: inherit;
            height: 100%;
        }
        .ota-op-kpi:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 28px rgba(15, 23, 42, 0.1);
            color: inherit;
            text-decoration: none;
        }
        .ota-op-kpi .card-body { padding: 1rem 1.1rem; }
        .ota-op-kpi--compact .card-body {
            padding: 0.75rem 0.85rem;
            display: flex;
            flex-direction: column;
            min-height: 5.5rem;
        }
        .ota-op-kpi-row--primary > [class*="col-"],
        .ota-op-kpi-row--secondary > [class*="col-"] {
            display: flex;
        }
        .ota-op-kpi-row--primary .ota-op-kpi,
        .ota-op-kpi-row--secondary .ota-op-kpi {
            flex: 1 1 auto;
            width: 100%;
        }
        .ota-op-kpi--compact .ota-op-kpi-icon {
            width: 1.85rem;
            height: 1.85rem;
            font-size: 1rem;
            margin-bottom: 0.35rem;
        }
        .ota-op-kpi--compact .ota-op-kpi-count {
            font-size: 1.35rem;
        }
        .ota-op-kpi--compact .ota-op-kpi-helper {
            font-size: 0.72rem;
            margin-top: 0.15rem;
        }
        .ota-op-kpi-icon {
            width: 2.25rem; height: 2.25rem; border-radius: 10px;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 1.15rem; margin-bottom: 0.5rem;
        }
        .ota-op-kpi-label {
            font-size: 0.75rem; line-height: 1.5; text-transform: uppercase; letter-spacing: 0.04em;
            font-weight: 400; color: var(--tblr-secondary, #62748e);
        }
        .ota-op-kpi-count {
            font-size: 1.65rem; font-weight: 800; line-height: 1.1;
            letter-spacing: -0.02em; color: var(--tblr-body-color, #1e293b);
        }
        .ota-op-kpi-helper { font-size: 0.875rem; line-height: 1.5; font-weight: 400; color: var(--tblr-secondary, #62748e); }
        .ota-op-kpi--warning .ota-op-kpi-icon { background: rgba(245, 158, 11, 0.15); color: #b45309; }
        .ota-op-kpi--info    .ota-op-kpi-icon { background: rgba(14, 165, 233, 0.15); color: #0369a1; }
        .ota-op-kpi--primary .ota-op-kpi-icon { background: rgba(37, 99, 235, 0.15); color: #1d4ed8; }
        .ota-op-kpi--success .ota-op-kpi-icon { background: rgba(16, 185, 129, 0.15); color: #047857; }
        .ota-op-kpi--danger  .ota-op-kpi-icon { background: rgba(239, 68, 68, 0.15); color: #b91c1c; }
        .ota-op-kpi--muted   .ota-op-kpi-icon { background: rgba(100, 116, 139, 0.15); color: #475569; }
        /* Needs attention list */
        .ota-attn-card .list-group-item {
            border-left: 0; border-right: 0; border-radius: 0;
        }
        .ota-attn-card .list-group-item:first-child { border-top: 0; }
        .ota-attn-row {
            display: flex; align-items: center; gap: 0.85rem;
            padding: 0.55rem 0;
        }
        .ota-attn-count {
            min-width: 2.2rem; text-align: center;
            font-weight: 800; font-size: 1rem;
            background: rgba(37, 99, 235, 0.1); color: #1e3a8a;
            border-radius: 6px; padding: 0.2rem 0.4rem;
        }
        .ota-attn-count--zero { background: #f1f5f9; color: #64748b; }
        .ota-attn-label { font-weight: 600; color: var(--tblr-body-color, #1e293b); }
        .ota-attn-helper { font-size: 0.75rem; line-height: 1.5; font-weight: 400; color: var(--tblr-secondary, #62748e); }
        .ota-attn-row--compact {
            padding: 0.15rem 0;
            gap: 0.65rem;
        }
        .ota-attn-row--compact .ota-attn-count {
            min-width: 1.85rem;
            font-size: 0.9rem;
        }
        .ota-priority-alerts .list-group-item {
            border-left: 0;
            border-right: 0;
        }
        .ota-revenue-strip .card-header {
            display: flex;
            flex-wrap: wrap;
            align-items: baseline;
            justify-content: space-between;
            gap: 0.35rem 1rem;
        }
        .ota-revenue-strip-item {
            display: flex;
            flex-direction: column;
            gap: 0.15rem;
            padding: 0.35rem 0;
        }
        .ota-revenue-strip-label {
            font-size: 0.75rem;
            line-height: 1.5;
            font-weight: 400;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--tblr-secondary, #62748e);
        }
        .ota-revenue-strip-value {
            font-size: 0.95rem;
            font-weight: 800;
            letter-spacing: -0.01em;
        }
        .ota-admin-quick--compact .ota-quick-action-card .card-body {
            padding: 0.75rem 0.85rem;
            min-height: 5.25rem;
        }
        .ota-admin-quick--compact .ota-quick-icon {
            width: 2rem;
            height: 2rem;
            font-size: 1.1rem;
            margin-bottom: 0.35rem;
        }
        .ota-admin-quick--compact .fw-bold {
            font-size: 0.88rem;
        }
        .ota-admin-quick--compact .text-secondary.small {
            font-size: 0.72rem !important;
            line-height: 1.35;
        }
        .ota-admin-quick--secondary .ota-quick-action-card .card-body {
            padding: 0.65rem 0.75rem;
        }
        .ota-prov-card--compact {
            padding: 0.65rem 0.85rem;
        }
        /* Provider health pills */
        .ota-prov-status {
            display: inline-flex; align-items: center;
            padding: 0.22rem 0.55rem; border-radius: 999px;
            font-size: 0.7rem; font-weight: 700;
            letter-spacing: 0.03em; border: 1px solid #e2e8f0;
        }
        .ota-prov-status--connected { background: #d1fae5; color: #065f46; border-color: #6ee7b7; }
        .ota-prov-status--disabled  { background: #f1f5f9; color: #475569; border-color: #cbd5e1; }
        .ota-prov-status--error     { background: #fee2e2; color: #7f1d1d; border-color: #fca5a5; }
        .ota-prov-status--not_configured { background: #f8fafc; color: #64748b; border-color: #e2e8f0; }
        .ota-prov-card {
            border: 1px solid var(--tblr-border-color, rgba(98, 105, 118, 0.16));
            border-radius: 10px;
            padding: 0.85rem 1rem;
            background: #fff;
            display: flex; align-items: center; gap: 0.85rem;
        }
        .ota-prov-card + .ota-prov-card { margin-top: 0.5rem; }
        .ota-prov-card .ota-prov-meta { font-size: 0.75rem; line-height: 1.5; font-weight: 400; color: var(--tblr-secondary, #62748e); }
        .ota-prov-card .ota-prov-error {
            font-size: 0.74rem; color: #b91c1c; margin-top: 0.15rem; word-break: break-word;
        }
        .ota-empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            padding: 36px 16px 32px;
            text-align: center;
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
        /* OTA dashboard responsive foundation (Tabler operator console) */
        html {
            overflow-x: clip;
        }
        body.page {
            overflow-x: clip;
        }
        .page-wrapper,
        .page-body,
        .container-xl {
            max-width: 100%;
        }
        .page .table-responsive,
        .page .ota-r-table-wrap,
        .bookings-table-wrap,
        .staff-table-wrap,
        .admin-table-scroll,
        .agents-table-wrap {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            max-width: 100%;
        }
        .page .card-body:has(> table.table),
        .page .card-table {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            max-width: 100%;
        }
        .page .btn-list,
        .page .page-header .row,
        .page .card-header .row,
        .bookings-queue-tabs,
        .bookings-filters .row {
            flex-wrap: wrap;
        }
        .page .pagination {
            flex-wrap: wrap;
            justify-content: center;
            gap: 0.25rem;
        }
        .page .modal-dialog {
            max-width: min(32rem, calc(100vw - 2rem));
            margin-left: auto;
            margin-right: auto;
        }
        .page .text-truncate,
        .page .nav-link.text-truncate {
            min-width: 0;
        }
        @media (max-width: 767.98px) {
            .page-header .page-title {
                font-size: 1.35rem;
                word-break: break-word;
            }
            .page .card-header .btn,
            .page .page-header .btn {
                width: auto;
                max-width: 100%;
            }
            .page .row.g-3 > [class*="col-"],
            .page .row.g-2 > [class*="col-"],
            .page .row.g-4 > [class*="col-"] {
                min-width: 0;
            }
            .page .form-control,
            .page .form-select {
                max-width: 100%;
            }
        }
        /* Bootstrap row gutter bleed — prevent horizontal overflow in operator console */
        .page .container-xl > .row,
        .page .container-xl .row.g-4,
        .page .row.g-4.mb-4,
        .page .row.g-4 {
            margin-left: 0;
            margin-right: 0;
            max-width: 100%;
        }
        .page .container-xl > .row > [class*="col-"],
        .page .row.g-4 > [class*="col-"] {
            padding-left: calc(var(--bs-gutter-x, 1.5rem) * 0.5);
            padding-right: calc(var(--bs-gutter-x, 1.5rem) * 0.5);
        }
        .page .ota-cb-actions,
        .page .ota-cb-summary,
        .page .btn-list,
        .page .bookings-queue-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            max-width: 100%;
        }
        .page .ota-op-kpi-label,
        .page .ota-op-kpi-helper,
        .page .ota-op-kpi-count {
            overflow-wrap: anywhere;
            word-break: break-word;
        }
        .page .ota-op-kpi-count {
            font-size: clamp(1.25rem, 4vw, 1.65rem);
        }
        .page .card-header .flex-shrink-0 {
            flex-shrink: 1;
            min-width: 0;
            max-width: 100%;
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
            justify-content: flex-end;
        }
        .page .card-header .flex-shrink-0 .btn {
            max-width: 100%;
            white-space: normal;
        }
        .page .ota-admin-table td,
        .page .ota-admin-table th {
            min-width: 0;
            overflow-wrap: anywhere;
            word-break: break-word;
        }
        .page .ota-admin-table .btn {
            white-space: normal;
        }
        @media (max-width: 1399.98px) {
            .page .ota-admin-table th:nth-child(4),
            .page .ota-admin-table td:nth-child(4),
            .page .ota-admin-table th:nth-child(8),
            .page .ota-admin-table td:nth-child(8) {
                display: none;
            }
        }
        @media (max-width: 991.98px) {
            .page .ota-admin-table th:nth-child(3),
            .page .ota-admin-table td:nth-child(3),
            .page .ota-admin-table th:nth-child(7),
            .page .ota-admin-table td:nth-child(7) {
                display: none;
            }
        }
        .page .preview-actions,
        .page .booking-card-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
            max-width: 100%;
        }
        .page .preview-actions .btn,
        .page .booking-card-actions .btn {
            flex: 1 1 auto;
            min-width: min(100%, 5.5rem);
            max-width: 100%;
            white-space: normal;
        }
        /* Shared responsive utilities (operator console) */
        .page .ota-r-text-safe,
        .page .ota-r-text-safe td,
        .page .ota-r-text-safe th {
            overflow-wrap: anywhere;
            word-break: break-word;
            min-width: 0;
        }
        .page .ota-r-action-bar,
        .page .ota-r-table-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
            max-width: 100%;
            min-width: 0;
        }
        .page .ota-r-table-actions .btn,
        .page .ota-r-action-bar .btn {
            flex: 0 1 auto;
            max-width: 100%;
            white-space: normal;
        }
        .page .ota-r-form-grid > [class*="col-"] {
            min-width: 0;
        }
        .page .ota-admin-quick > [class*="col-"] {
            min-width: 0;
        }
        .page .ota-admin-quick .ota-quick-action-card .card-body {
            min-width: 0;
        }
        .page .ota-admin-quick .ota-quick-action-card .fw-bold,
        .page .ota-admin-quick .ota-quick-action-card .text-secondary {
            overflow-wrap: anywhere;
            word-break: break-word;
        }
        .page .ota-attn-row {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-start;
            gap: 0.5rem 0.75rem;
            min-width: 0;
        }
        .page .ota-attn-label,
        .page .ota-attn-helper {
            overflow-wrap: anywhere;
            word-break: break-word;
        }
        .page .card-header .d-flex.flex-wrap .btn + .btn {
            margin-left: 0 !important;
        }
        @media (max-width: 991.98px) {
            .page .ota-recent-bookings-card .ota-admin-table th:nth-child(2),
            .page .ota-recent-bookings-card .ota-admin-table td:nth-child(2),
            .page .ota-recent-bookings-card .ota-admin-table th:nth-child(4),
            .page .ota-recent-bookings-card .ota-admin-table td:nth-child(4),
            .page .ota-recent-bookings-card .ota-admin-table th:nth-child(8),
            .page .ota-recent-bookings-card .ota-admin-table td:nth-child(8) {
                display: none;
            }
        }
        @media (max-width: 767.98px) {
            .ota-command-banner {
                padding: 16px 18px;
            }
            .ota-command-banner .ota-cb-headline {
                font-size: 1.2rem;
            }
            .ota-command-banner .ota-cb-actions {
                width: 100%;
                flex-basis: 100%;
            }
            .ota-command-banner .ota-cb-actions .btn {
                flex: 1 1 calc(50% - 0.25rem);
            }
            .page .ota-admin-quick > [class*="col-"] {
                flex: 0 0 100%;
                max-width: 100%;
            }
            .page .card-header .flex-shrink-0,
            .page .d-flex.flex-wrap.justify-content-between .flex-shrink-0 {
                width: 100%;
                justify-content: flex-start;
            }
            .page .ota-admin-table .text-nowrap {
                white-space: normal;
            }
            .page .bookings-filters .col-12.mt-2,
            .page .reports-toolbar-actions {
                position: fixed;
                left: 0;
                right: 0;
                bottom: 0;
                z-index: 1040;
                margin: 0 !important;
                padding: 0.65rem 1rem calc(0.65rem + env(safe-area-inset-bottom, 0px));
                background: #fff;
                border-top: 1px solid rgba(98, 105, 118, 0.18);
                box-shadow: 0 -8px 24px rgba(15, 23, 42, 0.08);
                max-width: 100vw;
                box-sizing: border-box;
            }
            .page .bookings-filters,
            .page .reports-toolbar {
                padding-bottom: 4.25rem;
            }
            /* Admin bookings: reserve scroll room so card actions clear the fixed filter bar */
            .page:has([data-bookings-page]) .bookings-table-wrap {
                margin-bottom: calc(0.5rem + env(safe-area-inset-bottom, 0px));
            }
            .page .reports-toolbar-actions,
            .page .bookings-filters .col-12.mt-2 {
                display: flex;
                flex-wrap: wrap;
                gap: 0.5rem;
            }
            .page .reports-toolbar-actions .btn,
            .page .bookings-filters .col-12.mt-2 .btn {
                flex: 1 1 calc(50% - 0.25rem);
                max-width: 100%;
                box-sizing: border-box;
                white-space: normal;
            }
        }

        /* Admin user access control center */
        .ota-access-shell {
            max-width: 1240px;
            margin-left: auto;
            margin-right: auto;
            width: 100%;
        }
        .ota-access-header-card {
            border: 1px solid var(--tblr-border-color, rgba(98, 105, 118, 0.16));
            box-shadow: 0 2px 14px rgba(15, 23, 42, 0.05);
        }
        .ota-access-header-grid {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
        }
        .ota-access-avatar {
            flex: 0 0 auto;
            width: 3.5rem;
            height: 3.5rem;
            border-radius: 12px;
            background: linear-gradient(135deg, #2563eb 0%, #4f46e5 100%);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.1rem;
            letter-spacing: 0.04em;
        }
        .ota-access-header-main {
            flex: 1 1 auto;
            min-width: 0;
        }
        .ota-access-header-card .ota-access-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem 0.65rem;
            align-items: center;
        }
        .ota-access-header-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(100%, 14rem), 1fr));
            gap: 0.35rem 1rem;
            font-size: 0.82rem;
            color: var(--tblr-secondary, #62748e);
        }
        .ota-access-action-bar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.5rem 0.75rem;
            max-width: 100%;
        }
        .ota-access-action-bar--primary .btn,
        .ota-access-action-bar--primary form {
            flex: 0 0 auto;
        }
        .ota-access-action-bar--primary form .btn {
            width: auto;
        }
        .ota-access-action-item {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 0.15rem;
        }
        .ota-access-action-help {
            font-size: 0.72rem;
            color: var(--tblr-secondary, #62748e);
            line-height: 1.3;
            max-width: 16rem;
        }
        .ota-access-action-bar--danger {
            padding: 0.65rem 0.85rem;
            border: 1px solid rgba(220, 38, 38, 0.18);
            border-radius: 10px;
            background: rgba(254, 242, 242, 0.55);
        }
        .ota-access-action-bar--danger form {
            flex: 0 0 auto;
        }
        .ota-access-panel {
            border: 1px solid var(--tblr-border-color, rgba(98, 105, 118, 0.14));
        }
        .ota-access-control-card .ota-access-control-select {
            border-color: rgba(37, 99, 235, 0.25);
            background-color: rgba(37, 99, 235, 0.03);
        }
        .ota-access-matrix-body {
            max-width: 100%;
        }
        .ota-access-form-footer {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--tblr-border-color, rgba(98, 105, 118, 0.16));
        }
        .ota-access-form--customer-only .ota-access-form-identity-col {
            flex: 0 0 100%;
            max-width: 100%;
        }
        .ota-access-form--customer-only .ota-access-customer-note {
            margin-top: 0 !important;
        }
        .ota-access-customer-note .card-header {
            background: transparent;
        }
        .ota-access-customer-note .ota-access-customer-banner,
        .ota-access-customer-banner {
            border-left: 3px solid var(--tblr-secondary, #62748e);
            background: rgba(100, 116, 139, 0.08);
            padding: 0.75rem 0.9rem;
            border-radius: 0 8px 8px 0;
            font-size: 0.88rem;
            color: var(--tblr-body-color, #1e293b);
        }
        .ota-access-summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(100%, 12rem), 1fr));
            gap: 0.75rem 1rem;
        }
        .ota-access-summary-grid--compact {
            grid-template-columns: 1fr;
            gap: 0.65rem;
        }
        .ota-access-security-grid {
            grid-template-columns: repeat(auto-fit, minmax(min(100%, 10rem), 1fr));
        }
        .ota-access-summary-item {
            min-width: 0;
        }
        .ota-access-summary-label {
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--tblr-secondary, #62748e);
            margin-bottom: 0.15rem;
        }
        .ota-access-summary-value {
            overflow-wrap: anywhere;
            word-break: break-word;
        }
        .ota-access-module {
            border: 1px solid var(--tblr-border-color, rgba(98, 105, 118, 0.14));
            border-radius: 10px;
            padding: 0.85rem 1rem;
            margin-bottom: 0.75rem;
            max-width: 100%;
            background: rgba(248, 250, 252, 0.55);
        }
        .ota-access-module:last-child {
            margin-bottom: 0;
        }
        .ota-access-module__title {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--tblr-secondary, #62748e);
            margin-bottom: 0.65rem;
        }
        .ota-access-toggle-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 0.45rem 0;
            border-bottom: 1px solid rgba(98, 105, 118, 0.1);
            min-width: 0;
        }
        .ota-access-toggle-row:last-child {
            border-bottom: 0;
            padding-bottom: 0;
        }
        .ota-access-toggle-row--readonly-off {
            opacity: 0.82;
        }
        .ota-access-toggle-row__label {
            flex: 1 1 auto;
            min-width: 0;
            overflow-wrap: anywhere;
            word-break: break-word;
            font-size: 0.9rem;
        }
        .ota-access-toggle-row__control {
            flex: 0 0 auto;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .ota-access-toggle-row .form-check.form-switch {
            margin-bottom: 0;
            min-height: 1.25rem;
            padding-left: 2.5rem;
        }
        .ota-access-toggle-row .form-check-input {
            width: 2.5rem;
            height: 1.25rem;
            cursor: default;
        }
        .ota-access-toggle-row .form-check-input:not(:disabled) {
            cursor: pointer;
        }
        .ota-access-toggle-row .form-check-input:disabled {
            opacity: 1;
        }
        .ota-access-toggle-row--readonly-off .form-check-input:disabled {
            opacity: 0.45;
        }
        .ota-access-toggle-row--limited .form-check-input:checked {
            background-color: #f59e0b;
            border-color: #f59e0b;
        }
        .ota-access-state {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            white-space: nowrap;
        }
        .ota-access-state--allowed { color: #047857; }
        .ota-access-state--limited { color: #b45309; }
        .ota-access-state--blocked { color: #64748b; }
        .ota-access-matrix-note {
            font-size: 0.82rem;
            color: var(--tblr-secondary, #62748e);
        }
        .ota-access-readonly-banner {
            border-left: 3px solid var(--tblr-secondary, #62748e);
            background: rgba(100, 116, 139, 0.08);
            padding: 0.65rem 0.85rem;
            border-radius: 0 8px 8px 0;
            font-size: 0.85rem;
        }
        .ota-access-editable-banner {
            border-left: 3px solid #2563eb;
            background: rgba(37, 99, 235, 0.06);
            padding: 0.65rem 0.85rem;
            border-radius: 0 8px 8px 0;
            font-size: 0.85rem;
        }
        @media (max-width: 767.98px) {
            .ota-access-header-grid {
                flex-direction: column;
            }
            .ota-access-toggle-row {
                flex-wrap: wrap;
            }
            .ota-access-toggle-row__control {
                width: 100%;
                justify-content: space-between;
            }
            .ota-access-action-item {
                width: 100%;
            }
        }
    </style>
    @stack('styles')
    @php
        $uiLayerDashContexts = $uiLayerContexts ?? (in_array($dashArea, ['admin', 'staff'], true) ? [$dashArea] : ui_layer_contexts());
    @endphp
    @include('layouts.partials.ui-layer-styles', ['contexts' => $uiLayerDashContexts])
</head>
<body @class([
    'ota-admin-console ota-admin-compact ota-ops-console' => $isOpsConsole,
    'ui-v1' => ($currentUiVersion ?? 'v1') === 'v1',
    'ui-v2' => ($currentUiVersion ?? 'v1') === 'v2',
    'ui-preview-namespace' => $isUiPreviewNamespace ?? false,
])>
{{-- Theme script (Tabler color mode); keep before body content per Tabler docs --}}
<script src="{{ $tabler }}/js/tabler-theme.min.js"></script>

<div class="page"@if ($isOpsConsole) data-dash-area="{{ $dashArea }}"@endif>
    {{-- Sidebar nav is resolved by URL prefix and authenticated account type; see top PHP block in this layout --}}
    <aside class="navbar navbar-vertical navbar-expand-lg ota-sidebar-refined" data-bs-theme="dark">
        <div class="container-fluid">
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar-menu" aria-controls="sidebar-menu" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <h1 class="navbar-brand navbar-brand-autodark">
                <a href="{{ $dashHomeUrl }}" class="d-block lh-sm">
                    <span class="d-block">{{ $dashProductName }}</span>
                    <span class="d-block fw-normal text-secondary ota-text-xxs">{{ $dashSubtitle }}</span>
                </a>
            </h1>
            <div class="collapse navbar-collapse" id="sidebar-menu">
                @if ($dashArea === 'admin')
                    @include('layouts.partials.dashboard-sidebar-admin')
                @elseif ($dashArea === 'staff')
                    @include('layouts.partials.dashboard-sidebar-staff')
                @elseif ($dashArea === 'agent')
                    @include('layouts.partials.dashboard-sidebar-agent')
                @elseif ($dashArea === 'customer')
                    @include('layouts.partials.dashboard-sidebar-customer')
                @else
                    @include('layouts.partials.dashboard-sidebar-guest')
                @endif
                @if ($isOpsConsole)
                    <div class="px-3 py-3 mt-2 border-top border-secondary border-opacity-25">
                        <p class="text-secondary mb-0 ota-text-xxs">Operational access is scoped by account type, agency context, and policies.</p>
                    </div>
                @endif
            </div>
        </div>
    </aside>

    <div class="page-wrapper">
        <header class="navbar navbar-expand-md d-print-none border-bottom bg-body">
            <div class="container-xl py-2">
                <div class="navbar-nav flex-row align-items-center ms-md-auto gap-2 justify-content-end w-100">
                    @auth
                        <span class="nav-link text-secondary text-truncate py-1 px-0 me-auto small d-none d-md-inline" style="max-width: 14rem;" title="{{ Auth::user()->email }}">
                            {{ Auth::user()->name ?: Auth::user()->email }}
                        </span>
                    @endauth
                    <form method="POST" action="{{ route('logout') }}" class="d-inline-flex m-0">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger btn-sm">
                            <i class="ti ti-logout me-1"></i>Log out
                        </button>
                    </form>
                </div>
            </div>
        </header>
        @if ($isOpsConsole)
            <div class="container-xl pt-3 pb-0">
                <div class="alert alert-warning ops-admin-banner mb-3 py-2" role="alert">
                    <div class="d-flex align-items-center gap-2">
                        <i class="ti ti-info-circle fs-4"></i>
                        <span>Supplier connections and ticketing providers may still require final API onboarding. Manual review remains available.</span>
                    </div>
                </div>
            </div>
        @endif
        @hasSection('page-header')
            <div class="page-header d-print-none">
                <div class="container-xl">
                    @yield('page-header')
                </div>
            </div>
        @endif

        <main class="page-body">
            <div class="container-xl py-4">
                {{-- Dynamic: alerts, breadcrumbs, Livewire, etc. --}}
                @yield('content')
            </div>
        </main>
    </div>
</div>

<script src="{{ $tabler }}/js/tabler.min.js" defer></script>
<script>
    (function () {
        function rowInteractiveTarget(event) {
            var row = event.target.closest('tr.ota-admin-click-row[data-href]');
            if (!row) return null;
            if (event.target.closest('a, button, input, select, textarea, label, [data-no-row-nav]')) return null;
            return row;
        }
        document.addEventListener('click', function (event) {
            var row = rowInteractiveTarget(event);
            if (!row) return;
            window.location.assign(row.getAttribute('data-href'));
        });
        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter' && event.key !== ' ') return;
            var row = event.target.closest('tr.ota-admin-click-row[data-href]');
            if (!row || row !== document.activeElement) return;
            event.preventDefault();
            window.location.assign(row.getAttribute('data-href'));
        });
    })();
</script>
@stack('scripts')
@php
    $uiLayerDashContexts = $uiLayerContexts ?? (in_array($dashArea, ['admin', 'staff'], true) ? [$dashArea] : ui_layer_contexts());
@endphp
@include('layouts.partials.ui-layer-scripts', ['contexts' => $uiLayerDashContexts])
</body>
</html>
