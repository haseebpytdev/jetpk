@extends('layouts.dashboard')

@section('title', 'Ask JetPakistan')

@section('content')
    <div class="mx-auto max-w-3xl space-y-6 p-6">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Ask JetPakistan</h1>
            <p class="mt-1 text-sm text-slate-600">Read-only assistant status. Mode is server-authoritative via environment configuration.</p>
        </div>

        <dl class="grid gap-4 rounded-xl border border-slate-200 bg-white p-5 sm:grid-cols-2">
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Mode</dt>
                <dd class="mt-1 text-lg font-medium text-slate-900">{{ $status['mode'] ?? 'off' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Language engine</dt>
                <dd class="mt-1 text-lg font-medium text-slate-900">{{ $status['language_engine'] ?? 'hybrid_model_free' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Flight tool</dt>
                <dd class="mt-1">{{ !empty($status['flight_tool']) ? 'Enabled' : 'Disabled' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Group tool</dt>
                <dd class="mt-1">{{ !empty($status['group_tool']) ? 'Enabled' : 'Disabled' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Knowledge</dt>
                <dd class="mt-1">{{ !empty($status['knowledge']) ? 'Enabled' : 'Disabled' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Human handoff</dt>
                <dd class="mt-1">{{ !empty($status['human_handoff']) ? 'Enabled' : 'Disabled' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Local LLM required</dt>
                <dd class="mt-1">{{ !empty($status['local_llm_required']) ? 'Yes' : 'No' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Gateway</dt>
                <dd class="mt-1">{{ $health['gateway'] ?? 'n/a' }}</dd>
            </div>
        </dl>

        <p class="text-sm text-slate-600">
            Public customer activation remains off while mode is <code>off</code> or <code>internal_canary</code>.
            Internal canary requires authenticated Staff with Support View (or Platform Admin).
        </p>
    </div>
@endsection
