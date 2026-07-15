@props([
    'title',
    'description' => null,
    'backRoute' => null,
    'backLabel' => '← Back',
])
<div class="jp-between jp-page-header">
    <div>
        @if ($backRoute)
            <p class="jp-backlink"><a href="{{ $backRoute }}">{{ $backLabel }}</a></p>
        @endif
        <h1>{{ $title }}</h1>
        @if ($description)
            <p class="jp-muted">{{ $description }}</p>
        @endif
    </div>
    @if (isset($actions))
        <div class="jp-toolbar">{{ $actions }}</div>
    @endif
</div>
