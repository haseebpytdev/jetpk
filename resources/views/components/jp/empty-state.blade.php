@props([
    'title',
    'description' => null,
    'icon' => null,
    'actionLabel' => null,
    'actionUrl' => null,
])

<div {{ $attributes->class(['jp-empty']) }} role="status">
  @if($icon)
    <div class="jp-empty__icon" aria-hidden="true"><x-jp.icon :name="$icon" /></div>
  @endif
  <h2 class="jp-empty__title">{{ $title }}</h2>
  @if($description)
    <p class="jp-empty__desc">{{ $description }}</p>
  @endif
  @if($actionLabel && $actionUrl)
    <a href="{{ $actionUrl }}" class="jp-btn jp-btn--primary">{{ $actionLabel }}</a>
  @endif
  {{ $slot }}
</div>
