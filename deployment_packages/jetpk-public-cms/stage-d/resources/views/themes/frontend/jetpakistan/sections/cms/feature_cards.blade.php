@php
    /** @var array<string, mixed> $section */
    /** @var \App\Services\Client\ClientPageRenderer $renderer */
@endphp
@php $items = $renderer->enabledItems($section['items'] ?? []); @endphp
@if ($items !== [])
  <div class="jp-page-grid jp-page-grid--3">
    @foreach ($items as $card)
      <x-jp.card :title="(string) ($card['title'] ?? $card['heading'] ?? '')">
        <p>{{ $card['body'] ?? $card['text'] ?? '' }}</p>
      </x-jp.card>
    @endforeach
  </div>
@endif
