@extends(client_layout('dashboard', 'admin'))

@section('title', 'Settings')

@section('page-header')
    <div class="jp-between">
        <div>
            <h1>Settings</h1>
            <p>Brand, content, communications, commerce, integrations, and operations.</p>
        </div>
        @if (current_client_slug())
            <a href="{{ client_route('admin.page-settings.index') }}" class="jp-btn jp-btn--sm jp-btn--primary">Page settings</a>
        @endif
    </div>
@endsection

@section('content')
    @foreach ($groups as $groupTitle => $cards)
        @if ($cards !== [])
            <section class="jp-settings-hub-group">
                <h2 class="jp-settings-hub-group__title">{{ $groupTitle }}</h2>
                <div class="jp-settings-grid">
                    @foreach ($cards as $card)
                        <a href="{{ client_route($card['route']) }}" class="jp-settings-card">
                            <div class="jp-between jp-settings-card__head">
                                <h3 class="jp-settings-card__title">{{ $card['title'] }}</h3>
                                @if (! empty($card['badge']))
                                    <span class="jp-status-badge">{{ $card['badge'] }}</span>
                                @endif
                            </div>
                            <p class="jp-settings-card__desc">{{ $card['description'] }}</p>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    @endforeach
@endsection
