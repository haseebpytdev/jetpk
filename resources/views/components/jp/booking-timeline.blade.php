@props([
    'steps' => [],
    'current' => null,
])

<ol {{ $attributes->class(['jp-timeline']) }} aria-label="Booking progress">
  @foreach($steps as $index => $step)
    @php
      $key = is_array($step) ? ($step['key'] ?? (string) $index) : (string) $index;
      $label = is_array($step) ? ($step['label'] ?? '') : $step;
      $stepKeys = collect($steps)->map(fn ($s, $i) => is_array($s) ? ($s['key'] ?? (string) $i) : (string) $i)->values();
      $currentIndex = $current !== null ? $stepKeys->search((string) $current) : false;
      $thisIndex = $stepKeys->search($key);
      $isCurrent = $currentIndex !== false && $thisIndex === $currentIndex;
      $isComplete = $currentIndex !== false && $thisIndex !== false && $thisIndex < $currentIndex;
    @endphp
    <li @class([
      'jp-timeline__step',
      'jp-timeline__step--current' => $isCurrent,
      'jp-timeline__step--complete' => $isComplete,
    ])>
      <span class="jp-timeline__marker" aria-hidden="true"></span>
      <span class="jp-timeline__label">{{ $label }}</span>
    </li>
  @endforeach
</ol>
