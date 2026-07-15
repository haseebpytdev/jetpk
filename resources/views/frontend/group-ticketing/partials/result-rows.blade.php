@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\GroupInventory> $results */
    /** @var \Illuminate\Support\Collection<int, array<string, mixed>> $cards */
@endphp
@foreach ($results as $inventory)
    @include('frontend.group-ticketing.partials.result-row', ['card' => $cards->get($inventory->id, [])])
@endforeach
