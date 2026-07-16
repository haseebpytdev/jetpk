@extends(client_layout('mobile-app', 'mobile'))

@section('title', 'Add traveler')

@section('mobile_app_title', 'Add traveler')

@section('mobile_app_back')
    <a href="{{ route('customer.travelers.index') }}" class="ota-mobile-app__back-btn" aria-label="Back to travelers">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
    </a>
@endsection

@section('content')
    <div class="ota-mobile-customer" data-testid="ota-mobile-customer-travelers-create">
        @if ($errors->any())
            @include('mobile.components.alert', ['type' => 'danger', 'message' => $errors->first()])
        @endif

        <form method="post" action="{{ route('customer.travelers.store') }}" class="ota-mobile-customer__form" data-testid="saved-traveler-form">
            @csrf
            @include('mobile.customer.travelers._form')
            <div class="ota-mobile-customer__actions">
                <button type="submit" class="ota-mobile-customer__btn ota-mobile-customer__btn--primary ota-mobile-customer__btn--block">Save traveler</button>
                <a href="{{ route('customer.travelers.index') }}" class="ota-mobile-customer__btn ota-mobile-customer__btn--secondary ota-mobile-customer__btn--block">Cancel</a>
            </div>
        </form>
    </div>
@endsection
