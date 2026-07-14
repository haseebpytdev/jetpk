{{-- Trust feature card --}}
@props(['icon', 'title', 'text'])
<div class="card trust">
  <div class="ic"><x-jp.icon :name="$icon" /></div>
  <h3>{{ $title }}</h3>
  <p>{{ $text }}</p>
</div>
