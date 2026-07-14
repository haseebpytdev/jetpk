{{--
  Breadcrumbs (new — Phase 0 gap). Phase 1 · JETPK-DASHBOARD-UI-FOUNDATION
  Pass :items as an array of ['label' => '...', 'href' => '...'|null].
  The last item (or any item without href) renders as the current page.
  Hrefs should be produced with client_route() by the caller to preserve tenancy.
--}}
@props([
    'items' => [],
    'ariaLabel' => 'Breadcrumb',
])

@if (! empty($items))
    <nav class="ota-dashboard-breadcrumbs" aria-label="{{ $ariaLabel }}">
        <ol class="ota-dashboard-breadcrumbs__list">
            @foreach ($items as $i => $item)
                @php $isLast = $loop->last; @endphp
                <li class="ota-dashboard-breadcrumbs__item">
                    @if (! $isLast && ! empty($item['href']))
                        <a class="ota-dashboard-breadcrumbs__link" href="{{ $item['href'] }}">{{ $item['label'] }}</a>
                        <span class="ota-dashboard-breadcrumbs__sep" aria-hidden="true">/</span>
                    @else
                        <span class="ota-dashboard-breadcrumbs__current" aria-current="page">{{ $item['label'] }}</span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif
