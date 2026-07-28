@php
    /** @var array<string, mixed> $section */
@endphp
@if (($section['heading'] ?? '') !== '' || ($section['body'] ?? '') !== '')
  <x-jp.card :title="(string) ($section['heading'] ?? '')">
    @if (($section['eyebrow'] ?? '') !== '')
      <p class="eyebrow">{{ $section['eyebrow'] }}</p>
    @endif
    @foreach (preg_split('/\r\n\r\n|\n\n/', (string) ($section['body'] ?? '')) ?: [] as $paragraph)
      @if (trim($paragraph) !== '')
        <p>{{ trim($paragraph) }}</p>
      @endif
    @endforeach
  </x-jp.card>
@endif
