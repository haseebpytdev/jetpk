@extends('layouts.developer')

@section('title', 'UI Layers')

@section('page-header')
    <h1 class="ota-dev-cp-page-title h2 mb-1">UI override layers</h1>
    <p class="text-secondary mb-0">
        Named CSS/JS layers load after base assets. Toggle without editing
        <code>ota-public.css</code>, <code>ota-admin-console.css</code>, or other base files.
    </p>
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <p class="mb-1">
                <strong>Global switch:</strong>
                {{ $globallyEnabled ? 'Enabled' : 'Disabled' }}
                (<code>OTA_UI_LAYERS_ENABLED</code>)
            </p>
            <p class="mb-0 text-secondary small">
                Manifest: <code>config/ui-layers.php</code> — layer order is ascending
                <code>order</code>, then key. Per-layer env:
                <code>OTA_UI_LAYER_{KEY}</code> overrides Dev CP when set.
            </p>
        </div>
    </div>

    <form method="POST" action="{{ route('dev.cp.ui-layers.update') }}">
        @csrf
        <div class="card mb-3">
            <div class="card-header">
                <h2 class="card-title mb-0">Registered layers</h2>
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table mb-0">
                    <thead>
                        <tr>
                            <th>Enabled</th>
                            <th>Key / order</th>
                            <th>Contexts</th>
                            <th>Assets</th>
                            <th>Rollback</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($layers as $entry)
                            @php
                                /** @var \App\Support\Ui\UiLayer $layer */
                                $layer = $entry['layer'];
                                $state = $entry['state'];
                            @endphp
                            <tr>
                                <td>
                                    <input type="hidden" name="layers[{{ $layer->key }}]" value="0">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        name="layers[{{ $layer->key }}]"
                                        value="1"
                                        @checked($state['effective_enabled'])
                                        @disabled($state['env_override'] !== null)
                                    >
                                    @if ($state['env_override'] !== null)
                                        <div class="small text-secondary">env locked</div>
                                    @endif
                                </td>
                                <td>
                                    <code>{{ $layer->key }}</code>
                                    <div class="small text-secondary">order {{ $layer->order }}</div>
                                    @if ($layer->description !== '')
                                        <div class="small">{{ $layer->description }}</div>
                                    @endif
                                    <div class="small text-secondary mt-1">
                                        effective:
                                        {{ $state['effective_enabled'] ? 'on' : 'off' }}
                                        · config {{ $state['config_default'] ? 'on' : 'off' }}
                                        @if ($state['db_row_exists'])
                                            · db {{ $state['db_override'] ? 'on' : 'off' }}
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    @foreach ($layer->contexts as $context)
                                        <span class="badge bg-blue-lt text-blue">{{ $context }}</span>
                                    @endforeach
                                    @if ($layer->suppliers !== [])
                                        <div class="small text-secondary mt-1">
                                            suppliers:
                                            <code>{{ implode(', ', $layer->suppliers) }}</code>
                                        </div>
                                    @endif
                                </td>
                                <td class="small">
                                    @forelse ($layer->css as $css)
                                        <div><code>{{ $css }}</code></div>
                                    @empty
                                    @endforelse
                                    @foreach ($layer->js as $js)
                                        <div><code>{{ $js }}</code></div>
                                    @endforeach
                                </td>
                                <td class="small text-secondary">{{ $layer->rollback }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Save layer toggles</button>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="card-header">
            <h2 class="card-title mb-0">Context map</h2>
        </div>
        <div class="card-body">
            <ul class="mb-0">
                @foreach ($contextLabels as $key => $label)
                    <li><code>{{ $key }}</code> — {{ $label }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endsection
