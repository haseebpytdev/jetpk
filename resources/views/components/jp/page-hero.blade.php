@props([
    'kicker' => null,
    'title',
    'description' => null,
    'id' => null,
])

<header @class(['jp-page-hero', $attributes->get('class')]) @if($id) id="{{ $id }}" @endif {{ $attributes->except('class') }}>
  @if($kicker)
    <p class="jp-page-hero__kicker">{{ $kicker }}</p>
  @endif
  <h1 class="jp-page-hero__title">{{ $title }}</h1>
  @if($description)
    <p class="jp-page-hero__desc">{{ $description }}</p>
  @endif
  {{ $slot }}
</header>
