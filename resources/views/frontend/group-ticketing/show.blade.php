@extends(client_layout('frontend', 'frontend'))

@section('title', $inventory->title.' — Group Ticketing')

@section('content')
    <section class="ota-section ota-group-ticketing-detail">
        <div class="ota-container ota-container--narrow">
            <header class="ota-section-head">
                <p class="ota-section-kicker">Group package</p>
                <h1 class="ota-section-title">{{ e($inventory->title) }}</h1>
            </header>

            @include('frontend.group-ticketing.partials.result-card', ['card' => $card])

            <div class="ota-group-detail-actions mt-4">
                <a href="{{ client_route('group-ticketing.search') }}" class="ota-btn ota-btn-secondary">Back to search</a>
            </div>
        </div>
    </section>
@endsection
