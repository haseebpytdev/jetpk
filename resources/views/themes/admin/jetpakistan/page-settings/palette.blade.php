@extends(client_layout('dashboard', 'admin'))

@section('title', 'Theme palette')

@section('page-header')
    <div class="jp-between">
        <div>
            <p class="jp-backlink"><a href="{{ client_route('admin.page-settings.index') }}">← Page settings</a></p>
            <h1>Logo palette generator</h1>
            <p>Generate recommended colors from the client logo. Approval required before live theme changes.</p>
        </div>
    </div>
@endsection

@section('content')
    @if (session('status'))
        <div class="jp-alert jp-alert--info">{{ session('status') }}</div>
    @endif

    <div class="jp-card">
        <form method="post" action="{{ client_route('admin.page-settings.palette.generate') }}" class="jp-stack">
            @csrf
            <label class="jp-label">Logo path (relative to public/)</label>
            <input aria-label="Logo path (relative to public/)" class="jp-input" name="logo_path" value="{{ $logoPath }}">
            <button type="submit" class="jp-btn jp-btn--sm">Generate from logo</button>
        </form>
    </div>

    @if ($palette)
        <div class="jp-card">
            <h2 class="jp-card__title">Draft palette</h2>
            <div class="jp-palette-grid">
                @foreach (['primary','secondary','accent','background','surface','text','muted'] as $key)
                    <div class="jp-palette-swatch">
                        <span class="jp-palette-swatch__chip" style="background:{{ $palette->{$key} }}"></span>
                        <span>{{ ucfirst($key) }}<br><code>{{ $palette->{$key} }}</code></span>
                    </div>
                @endforeach
            </div>
            @if (is_array($palette->palette_json['contrast_warnings'] ?? null))
                @foreach ($palette->palette_json['contrast_warnings'] as $warning)
                    <div class="jp-alert jp-alert--warn">{{ $warning }}</div>
                @endforeach
            @endif
            @if ($palette->approved_at)
                <p class="jp-muted">Approved {{ $palette->approved_at->diffForHumans() }}</p>
            @else
                <form method="post" action="{{ client_route('admin.page-settings.palette.apply') }}">
                    @csrf
                    <button type="submit" class="jp-btn">Approve & apply to branding</button>
                </form>
            @endif
        </div>
    @else
        <x-themes.admin.jetpakistan.components.empty-state title="No palette generated yet" message="Upload a logo and run Generate from logo." />
    @endif
@endsection
