<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Developer Control Panel')</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            font-size: 16px;
            line-height: 1.5;
            color: #1e293b;
            background: #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .dev-cp-auth-card {
            width: 100%;
            max-width: 420px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 2rem;
            box-shadow: 0 4px 24px rgba(15, 23, 42, 0.06);
        }
        .dev-cp-auth-title {
            margin: 0 0 0.5rem;
            font-size: 1.35rem;
            font-weight: 600;
        }
        .dev-cp-auth-lead {
            margin: 0 0 0.75rem;
            color: #64748b;
            font-size: 0.95rem;
        }
        .dev-cp-auth-notes {
            margin: 0 0 1.25rem;
            padding-left: 1.25rem;
            color: #64748b;
            font-size: 0.875rem;
        }
        .dev-cp-auth-notes li { margin-bottom: 0.25rem; }
        .dev-cp-field { margin-bottom: 1rem; }
        .dev-cp-label {
            display: block;
            margin-bottom: 0.35rem;
            font-weight: 500;
            font-size: 0.9rem;
        }
        .dev-cp-input {
            width: 100%;
            padding: 0.55rem 0.75rem;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 1rem;
        }
        .dev-cp-input:focus {
            outline: 2px solid #3b82f6;
            outline-offset: 1px;
            border-color: #3b82f6;
        }
        .dev-cp-error {
            margin-top: 0.35rem;
            color: #b91c1c;
            font-size: 0.875rem;
        }
        .dev-cp-alert {
            margin-bottom: 1rem;
            padding: 0.75rem 1rem;
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 6px;
            color: #991b1b;
            font-size: 0.9rem;
        }
        .dev-cp-btn {
            width: 100%;
            margin-top: 0.5rem;
            padding: 0.65rem 1rem;
            background: #1e40af;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
        }
        .dev-cp-btn:hover { background: #1d4ed8; }
        .dev-cp-footnote {
            margin-top: 1.25rem;
            font-size: 0.8rem;
            color: #94a3b8;
            text-align: center;
        }
    </style>
</head>
<body>
    <main class="dev-cp-auth-card">
        @yield('content')
    </main>
</body>
</html>
