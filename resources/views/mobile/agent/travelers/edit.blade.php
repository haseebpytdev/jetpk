@extends(client_layout('mobile-app', 'mobile'))

@section('title', 'Edit traveler')

@section('mobile_app_title', 'Edit traveler')

@section('mobile_app_back')
    <a href="{{ route('agent.travelers.index') }}" class="ota-mobile-app__back-btn" aria-label="Back to travelers">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
    </a>
@endsection

@section('content')
    <div class="ota-mobile-agent" data-testid="ota-mobile-agent-travelers-edit">
        @unless ($traveler->isComplete())
            @include('mobile.components.alert', ['type' => 'warning', 'message' => 'This profile is incomplete. Add missing fields before ticketing.'])
        @endunless
        @if ($errors->any())
            @include('mobile.components.alert', ['type' => 'danger', 'message' => $errors->first()])
        @endif

        <form method="post" action="{{ route('agent.travelers.update', $traveler) }}" class="ota-mobile-agent__form" data-testid="saved-traveler-form">
            @csrf
            @method('PATCH')
            @include('mobile.agent.travelers._form')
            <div class="ota-mobile-agent__actions">
                <button type="submit" class="ota-mobile-agent__btn ota-mobile-agent__btn--primary ota-mobile-agent__btn--block">Save changes</button>
                <a href="{{ route('agent.travelers.index') }}" class="ota-mobile-agent__btn ota-mobile-agent__btn--secondary ota-mobile-agent__btn--block">Cancel</a>
            </div>
        </form>
    </div>
@endsection
