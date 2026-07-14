<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Client preview') — Master preview</title>
    <style>
        :root {
            color-scheme: light;
            font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
            line-height: 1.5;
        }
        body {
            margin: 0;
            background: #f8fafc;
            color: #0f172a;
        }
        .preview-banner {
            background: #7c2d12;
            color: #fff7ed;
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
            text-align: center;
        }
        .preview-shell {
            max-width: 960px;
            margin: 0 auto;
            padding: 1.5rem 1rem 2rem;
        }
        .preview-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            padding: 1.25rem;
            margin-bottom: 1rem;
        }
        .preview-card h1 {
            margin: 0 0 0.5rem;
            font-size: 1.5rem;
        }
        .preview-card h2 {
            margin: 1.25rem 0 0.5rem;
            font-size: 1rem;
        }
        .preview-meta {
            color: #475569;
            margin: 0 0 1rem;
        }
        .preview-nav {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        .preview-nav a {
            display: inline-block;
            padding: 0.375rem 0.75rem;
            border-radius: 999px;
            border: 1px solid #cbd5e1;
            color: #0f172a;
            text-decoration: none;
            font-size: 0.875rem;
        }
        .preview-nav a.is-active {
            background: #0f172a;
            border-color: #0f172a;
            color: #fff;
        }
        .preview-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }
        .preview-table th,
        .preview-table td {
            border-bottom: 1px solid #e2e8f0;
            padding: 0.5rem 0.25rem;
            text-align: left;
            vertical-align: top;
        }
        .preview-table th {
            width: 40%;
            color: #475569;
            font-weight: 600;
        }
        code {
            background: #f1f5f9;
            padding: 0.125rem 0.375rem;
            border-radius: 0.25rem;
            font-size: 0.8125rem;
        }
        .flag-true { color: #15803d; }
        .flag-false { color: #b91c1c; }
        .preview-swatch {
            display: inline-block;
            width: 1rem;
            height: 1rem;
            border-radius: 0.25rem;
            border: 1px solid #cbd5e1;
            vertical-align: middle;
            margin-right: 0.375rem;
        }
        .preview-brand-asset {
            margin-top: 0.5rem;
        }
        .preview-brand-asset img {
            max-width: 160px;
            max-height: 48px;
            object-fit: contain;
        }
    </style>
    @isset($brandingResolver)
        @if ($brandingResolver->faviconUrl())
            <link rel="icon" href="{{ $brandingResolver->faviconUrl() }}">
        @endif
    @endisset
</head>
<body>
    <div class="preview-banner">
        Master preview mode — context only. Theme, modules, and suppliers are not applied to production routes yet.
    </div>
    <div class="preview-shell">
        @yield('content')
    </div>
</body>
</html>
