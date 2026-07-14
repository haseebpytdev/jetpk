@props([
    'id',
    'title' => null,
    'size' => 'md',
])

<div
  {{ $attributes->class(['jp-modal', 'jp-modal--'.$size]) }}
  id="{{ $id }}"
  role="dialog"
  aria-modal="true"
  @if($title) aria-labelledby="{{ $id }}-title" @endif
  hidden
>
  <div class="jp-modal__backdrop" data-jp-modal-close></div>
  <div class="jp-modal__panel">
    @if($title)
      <header class="jp-modal__header">
        <h2 class="jp-modal__title" id="{{ $id }}-title">{{ $title }}</h2>
        <button type="button" class="jp-modal__close" data-jp-modal-close aria-label="Close">&times;</button>
      </header>
    @endif
    <div class="jp-modal__body">
      {{ $slot }}
    </div>
  </div>
</div>
