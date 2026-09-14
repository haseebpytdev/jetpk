@extends('layouts.dashboard')

@section('title', 'Ask JetPakistan')

@section('content')
    <div class="mx-auto max-w-4xl space-y-6 p-6">
        <div>
            <p class="text-sm"><a href="{{ route('admin.settings.index') }}" class="text-sky-700 hover:underline">← Settings</a></p>
            <h1 class="mt-2 text-2xl font-semibold text-slate-900">Ask JetPakistan</h1>
            <p class="mt-1 text-sm text-slate-600">
                Safe operational controls for internal QA. Environment policy is the hard ceiling — admin cannot override forbidden capabilities.
            </p>
        </div>

        @if (session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                AI assistant settings saved.
            </div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <ul class="mb-0 list-disc pl-4">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="post" action="{{ route('admin.settings.ai-assistant.update') }}" class="space-y-6">
            @csrf
            @method('PATCH')

            <section class="rounded-xl border border-slate-200 bg-white p-5">
                <h2 class="text-lg font-semibold text-slate-900">Safe controls</h2>
                <p class="mt-1 text-sm text-slate-600">Each toggle shows configured (admin) and effective (runtime) state.</p>

                <div class="mt-4 space-y-4">
                    @foreach ($controlMatrix as $field => $control)
                        <div class="flex flex-col gap-2 border-b border-slate-100 pb-4 last:border-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <div class="font-medium text-slate-900">{{ $control['label'] }}</div>
                                <div class="mt-1 text-xs text-slate-500">
                                    Configured:
                                    <span class="font-semibold {{ $control['configured'] ? 'text-emerald-700' : 'text-slate-600' }}">
                                        {{ $control['configured'] ? 'ON' : 'OFF' }}
                                    </span>
                                    · Effective:
                                    <span class="font-semibold {{ $control['effective'] ? 'text-emerald-700' : 'text-slate-600' }}">
                                        {{ $control['effective'] ? 'ON' : 'OFF' }}
                                    </span>
                                    @if ($control['configured'] && ! $control['effective'] && $control['blocked_by'])
                                        <span class="text-amber-700">— blocked by {{ str_replace('_', ' ', $control['blocked_by']) }}</span>
                                    @endif
                                </div>
                            </div>
                            <div class="form-check form-switch">
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    role="switch"
                                    name="{{ $field }}"
                                    value="1"
                                    id="{{ $field }}"
                                    @checked(old($field, $control['configured']))
                                >
                                <label class="form-check-label text-sm text-slate-700" for="{{ $field }}">Enable</label>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-5">
                    <button type="submit" class="inline-flex items-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                        Save settings
                    </button>
                </div>
            </section>
        </form>

        <section class="rounded-xl border border-slate-200 bg-white p-5">
            <h2 class="text-lg font-semibold text-slate-900">Runtime status</h2>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Effective mode</dt>
                    <dd class="mt-1 text-lg font-medium text-slate-900">{{ $status['mode'] ?? 'off' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Runtime</dt>
                    <dd class="mt-1 text-lg font-medium text-slate-900">{{ !empty($status['runtime_on']) ? 'ON' : 'OFF' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Gateway</dt>
                    <dd class="mt-1">{{ $health['gateway'] ?? 'n/a' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Public beta</dt>
                    <dd class="mt-1 text-red-700 font-medium">NOT AUTHORIZED</dd>
                </div>
            </dl>
        </section>

        <section class="rounded-xl border border-red-200 bg-red-50 p-5">
            <h2 class="text-lg font-semibold text-red-900">Write capabilities — permanently locked</h2>
            <p class="mt-1 text-sm text-red-800">These cannot be enabled from the admin dashboard in this phase.</p>
            <ul class="mt-4 grid gap-2 sm:grid-cols-2">
                @foreach ($hardLockedWrites as $cap)
                    <li class="flex items-center justify-between rounded-lg border border-red-200 bg-white px-3 py-2 text-sm">
                        <span>{{ $cap['label'] }}</span>
                        <span class="font-semibold text-red-700">DISABLED / NOT AUTHORIZED</span>
                    </li>
                @endforeach
            </ul>
        </section>

        <p class="text-sm text-slate-600">
            Public customer activation remains blocked while mode is <code>off</code> or <code>internal_canary</code>.
            Internal canary requires authenticated platform admin or staff with Support View.
        </p>
    </div>
@endsection
