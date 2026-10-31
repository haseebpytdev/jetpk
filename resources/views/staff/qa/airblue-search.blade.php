<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AirBlue QA Search — JetPakistan Staff</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 2rem; max-width: 40rem; color: #111; }
        .card { border: 1px solid #ddd; border-radius: 8px; padding: 1.25rem; margin-bottom: 1rem; }
        .ok { color: #0a7a2f; }
        .bad { color: #a11; }
        label { display: block; margin-top: .75rem; font-size: .9rem; }
        input { width: 100%; padding: .4rem .5rem; margin-top: .25rem; }
        button { margin-top: 1rem; padding: .55rem 1rem; cursor: pointer; }
        .meta { font-size: .85rem; color: #444; }
        .flash { padding: .75rem; margin-bottom: 1rem; border-radius: 6px; background: #f5f5f5; }
    </style>
</head>
<body>
    <h1>AirBlue QA Search</h1>
    <p class="meta">Staff / platform-admin only. Pins Zapways TEST connection {{ $connectionId }}. Does not expose sandbox inventory to the public allowlist.</p>

    @if (session('error'))
        <div class="flash bad">{{ session('error') }}</div>
    @endif
    @if (session('status'))
        <div class="flash ok">{{ session('status') }}</div>
    @endif

    <div class="card">
        <h2>Scope readiness</h2>
        @if ($readiness['allowed'] ?? false)
            <p class="ok">ALLOWED</p>
        @else
            <p class="bad">DENIED — {{ $readiness['reason'] ?? 'unknown' }}</p>
        @endif
        <ul class="meta">
            <li>connection_id: {{ $readiness['connection_id'] ?? '—' }}</li>
            <li>provider: {{ $readiness['provider'] ?? '—' }}</li>
            <li>environment: {{ $readiness['environment'] ?? '—' }}</li>
            <li>protocol: {{ $readiness['protocol'] ?? '—' }}</li>
            <li>test_host: {{ $readiness['test_host'] ?? '—' }}</li>
            <li>service_target: {{ $readiness['service_target'] ?? '—' }}</li>
        </ul>
        <p class="meta">QA session active: {{ $qaActive ? 'yes' : 'no' }}</p>
    </div>

    <div class="card">
        <h2>Start QA search</h2>
        <form method="post" action="{{ route('staff.qa.airblue-search.activate') }}">
            @csrf
            <label>From
                <input name="from" value="LHE" maxlength="3" required>
            </label>
            <label>To
                <input name="to" value="KHI" maxlength="3" required>
            </label>
            <label>Depart (YYYY-MM-DD)
                <input name="depart" value="{{ now()->addWeeks(3)->format('Y-m-d') }}" required>
            </label>
            <button type="submit" @disabled(!($readiness['allowed'] ?? false))>Activate QA session &amp; open results</button>
        </form>
        <form method="post" action="{{ route('staff.qa.airblue-search.deactivate') }}" style="margin-top:.75rem">
            @csrf
            <button type="submit">Clear QA session</button>
        </form>
    </div>

    <p class="meta">Hard stop before AirBook. Review only in this phase.</p>
</body>
</html>
