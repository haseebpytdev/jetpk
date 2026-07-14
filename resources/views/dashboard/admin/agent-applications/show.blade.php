@extends(client_layout('dashboard', 'admin'))

@section('title', 'Agent application review')

@php
    $statusBadgeClass = match ((string) $application->status) {
        'pending' => 'badge-soft-warning',
        'approved' => 'badge-soft-success',
        'rejected' => 'badge-soft-danger',
        'needs_more_info' => 'badge-soft-purple',
        default => 'badge-soft-neutral',
    };
    $statusLabel = match ((string) $application->status) {
        'pending' => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'needs_more_info' => 'Needs info',
        default => ucfirst(str_replace('_', ' ', (string) $application->status)),
    };
    $canReview = ! in_array((string) $application->status, ['approved', 'rejected'], true);
@endphp

@push('styles')
<style>
    .badge-soft-warning { background: #fef3c7; color: #78350f !important; border: 1px solid #fcd34d; }
    .badge-soft-success { background: #dcfce7; color: #14532d !important; border: 1px solid #86efac; }
    .badge-soft-danger { background: #fee2e2; color: #7f1d1d !important; border: 1px solid #fca5a5; }
    .badge-soft-purple { background: #ede9fe; color: #5b21b6 !important; border: 1px solid #c4b5fd; }
    .badge-soft-neutral { background: #e5e7eb; color: #374151 !important; border: 1px solid #d1d5db; }
    .application-review-actions .btn { min-height: 42px; }
</style>
@endpush

@section('page-header')
    <div class="jp-between">
        <div class="col">
            <div class="page-pretitle"><a href="{{ route('admin.agent-applications.index') }}" class="text-secondary">Agent applications</a></div>
            <h1 class="jp-page-title">Review application</h1>
        </div>
        <div class="col-auto ms-auto">
            <a href="{{ route('admin.agent-applications.index') }}" class="jp-btn jp-btn--ghost btn-sm">Back to queue</a>
        </div>
    </div>
@endsection

@section('content')
    @if (session('status') === 'application-approved')
        <div class="jp-alert jp-alert--success">Application approved. Agency owner account created and notification email sent.</div>
    @elseif (session('status') === 'application-rejected')
        <div class="jp-alert jp-alert--warn">Application rejected. Applicant notification email sent.</div>
    @elseif (session('status') === 'application-needs-info')
        <div class="jp-alert jp-alert--info">Marked as needs more info. Applicant notification email sent.</div>
    @elseif (session('status') === 'already-approved')
        <div class="alert alert-secondary">This application was already approved.</div>
    @endif
    @if ($needsAgencyRepair ?? false)
        <div class="jp-alert jp-alert--warn">
            This application was approved before agency onboarding was completed. Agency/owner linkage may be missing or still pointing at the platform default agency.
            Run <code>php artisan ota:diagnose-agency-links</code>, then <code>php artisan ota:reconcile-agencies --dry-run</code> and <code>--force</code> on the server to repair safely.
        </div>
    @endif
    @if ($errors->any())
        <div class="jp-alert jp-alert--danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="jp-card">
                <div class="jp-card__head"><h3 class="jp-card__title">Application details</h3></div>
                <div class="jp-card__body">
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
                    <p><strong>Status:</strong>
                        <span class="badge {{ $statusBadgeClass }}" data-testid="ota-agent-application-status-{{ $application->status }}">{{ $statusLabel }}</span>
                    </p>
                    @if ($application->reviewed_at)
                        <p><strong>Reviewed:</strong> {{ $application->reviewed_at->format('Y-m-d H:i') }} @if($application->reviewer) by {{ $application->reviewer->name }} @endif</p>
                    @endif
                    @if ($application->internal_note)
                        <p><strong>Internal note:</strong> {{ $application->internal_note }}</p>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="jp-card" data-testid="ota-agent-application-preview-actions">
                <div class="jp-card__head"><h3 class="jp-card__title">Review actions</h3></div>
                <div class="card-body application-review-actions">
                    @if ($canReview)
                        <form method="POST" action="{{ route('admin.agent-applications.approve', $application) }}" class="mb-3">
                            @csrf
                            @method('PATCH')
                            <label class="jp-label" for="approve-note">Internal note (optional)</label>
                            <textarea id="approve-note" class="jp-control mb-2" name="internal_note" rows="2" placeholder="Internal note (optional)"></textarea>
                            <button class="btn btn-success w-100" type="submit">Approve and create agent account</button>
                        </form>
                        <form method="POST" action="{{ route('admin.agent-applications.needs-more-info', $application) }}" class="mb-3">
                            @csrf
                            @method('PATCH')
                            <label class="jp-label" for="needs-info-note">Information required</label>
                            <textarea id="needs-info-note" class="jp-control mb-2" name="internal_note" rows="2" placeholder="Describe what the applicant should provide" required></textarea>
                            <button class="btn btn-warning w-100" type="submit">Mark needs more info</button>
                        </form>
                        <form method="POST" action="{{ route('admin.agent-applications.reject', $application) }}">
                            @csrf
                            @method('PATCH')
                            <label class="jp-label" for="reject-note">Reason (optional)</label>
                            <textarea id="reject-note" class="jp-control mb-2" name="internal_note" rows="2" placeholder="Reason (optional)"></textarea>
                            <button class="jp-btn jp-btn--danger w-100" type="submit">Reject application</button>
                        </form>
                    @else
                        <p class="text-secondary mb-0">This application has already been {{ $statusLabel }}. No further review actions are available.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
