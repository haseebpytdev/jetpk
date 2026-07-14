@extends(client_layout('dashboard', 'admin'))

@section('title', 'Agent application review')

@php
    $statusLabel = match ((string) $application->status) {
        'pending' => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'needs_more_info' => 'Needs info',
        default => ucfirst(str_replace('_', ' ', (string) $application->status)),
    };
    $canReview = ! in_array((string) $application->status, ['approved', 'rejected'], true);
@endphp

@section('page-header')
    <div class="jp-between">
        <div>
            <p class="jp-cell-sub"><a href="{{ client_route('admin.agent-applications.index') }}">Agent applications</a></p>
            <h1>Review application</h1>
            <p>{{ $application->first_name }} {{ $application->last_name }} · {{ $application->company_name }}</p>
        </div>
        <a href="{{ client_route('admin.agent-applications.index') }}" class="jp-btn jp-btn--sm jp-btn--ghost">Back to queue</a>
    </div>
@endsection

@section('content')
@include('themes.admin.jetpakistan.partials.flash')

@if ($needsAgencyRepair ?? false)
    <div class="jp-alert jp-alert--warn">
        This application was approved before agency onboarding was completed. Run <code>php artisan ota:diagnose-agency-links</code> and reconcile agencies on the server.
    </div>
@endif

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 16px;">
    <div class="jp-card">
        <div class="jp-card__head"><h2 class="jp-card__title">Application details</h2></div>
        <p><strong>Name:</strong> {{ $application->first_name }} {{ $application->last_name }}</p>
        <p><strong>Email:</strong> {{ $application->email }}</p>
        <p><strong>Mobile:</strong> {{ display_unknown($application->mobile) }}</p>
        <p><strong>Company:</strong> {{ $application->company_name }}</p>
        <p><strong>Business type:</strong> {{ $application->business_type }}</p>
        <p><strong>Location:</strong> {{ $application->city }}, {{ $application->country }}</p>
        <p><strong>Address:</strong> {{ $application->office_address }}</p>
        <p><strong>Website:</strong> {{ display_unknown($application->website) }}</p>
        <p><strong>CNIC:</strong> {{ display_unknown($application->cnic) }}</p>
        <p><strong>NTN:</strong> {{ display_unknown($application->ntn) }}</p>
        <p><strong>IATA:</strong> {{ display_unknown($application->iata_number) }}</p>
        <p><strong>Years in business:</strong> {{ display_unknown($application->years_in_business) }}</p>
        <p><strong>Expected volume:</strong> {{ display_unknown($application->expected_booking_volume) }}</p>
        <p><strong>Services:</strong> {{ is_array($application->services_interested) ? implode(', ', $application->services_interested) : display_unknown() }}</p>
        <p><strong>Applicant notes:</strong> {{ display_unknown($application->notes) }}</p>
        <p><strong>Status:</strong> <span class="jp-badge-pill">{{ $statusLabel }}</span></p>
        @if ($application->reviewed_at)
            <p><strong>Reviewed:</strong> {{ $application->reviewed_at->format('Y-m-d H:i') }} @if($application->reviewer) by {{ $application->reviewer->name }} @endif</p>
        @endif
        @if ($application->internal_note)
            <p><strong>Internal note:</strong> {{ $application->internal_note }}</p>
        @endif
    </div>

    <div class="jp-card" data-testid="ota-agent-application-preview-actions">
        <div class="jp-card__head"><h2 class="jp-card__title">Review actions</h2></div>
        @if ($canReview)
            <form method="POST" action="{{ client_route('admin.agent-applications.approve', $application) }}" style="margin-bottom: 16px;">
                @csrf
                @method('PATCH')
                <label class="jp-label" for="approve-note">Internal note (optional)</label>
                <textarea id="approve-note" class="jp-input" name="internal_note" rows="2" placeholder="Internal note (optional)" style="width: 100%; margin-bottom: 8px;"></textarea>
                <button class="jp-btn" type="submit" style="width: 100%;">Approve and create agent account</button>
            </form>
            <form method="POST" action="{{ client_route('admin.agent-applications.needs-more-info', $application) }}" style="margin-bottom: 16px;">
                @csrf
                @method('PATCH')
                <label class="jp-label" for="needs-info-note">Information required</label>
                <textarea id="needs-info-note" class="jp-input" name="internal_note" rows="2" placeholder="Describe what the applicant should provide" required style="width: 100%; margin-bottom: 8px;"></textarea>
                <button class="jp-btn jp-btn--outline" type="submit" style="width: 100%;">Mark needs more info</button>
            </form>
            <form method="POST" action="{{ client_route('admin.agent-applications.reject', $application) }}">
                @csrf
                @method('PATCH')
                <label class="jp-label" for="reject-note">Reason (optional)</label>
                <textarea id="reject-note" class="jp-input" name="internal_note" rows="2" placeholder="Reason (optional)" style="width: 100%; margin-bottom: 8px;"></textarea>
                <button class="jp-btn jp-btn--ghost" type="submit" style="width: 100%;">Reject application</button>
            </form>
        @else
            <p class="jp-cell-sub">This application has already been {{ $statusLabel }}. No further review actions are available.</p>
        @endif
    </div>
</div>
@endsection
