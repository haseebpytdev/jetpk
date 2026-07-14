@php
    use App\Support\Client\JetpkHomepageSectionData;

    $jpHome = app(JetpkHomepageSectionData::class);
    $items = $jpHome->featureBoardWithFallback();
@endphp
@if ($items !== [])
<div class="board reveal">
  <div class="wrap">
    @foreach ($items as $item)
      <div class="board-item"><span class="dot"></span><b>{{ $item['value'] ?? '' }}</b> {{ $item['label'] ?? '' }}</div>
    @endforeach
  </div>
</div>
@endif
