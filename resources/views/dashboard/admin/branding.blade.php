@extends(client_layout('dashboard', 'admin'))

@section('title', 'Branding')

@section('page-header')
    <div class="jp-between">
        <div class="col">
            <div class="page-pretitle">Branding</div>
            <h1 class="jp-page-title">Branding settings</h1>
            <div class="text-secondary mt-1">Client preview values from <code>config/ota-client.php</code> — all fields are read-only here.</div>
        </div>
    </div>
@endsection

@section('content')
    @php $c = $client ?? []; @endphp
    <div class="jp-alert jp-alert--info mb-4">
        <i class="ti ti-info-circle me-2"></i>Production would persist tenant branding per domain and inject CSS tokens at runtime.
    </div>
    <div class="jp-card">
        <div class="jp-card__body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="jp-label">Agency name</label>
                    <input type="text" class="jp-control" disabled value="{{ $c['agency_name'] ?? '' }}">
                </div>
                <div class="col-md-6">
                    <label class="jp-label">Logo text</label>
                    <input type="text" class="jp-control" disabled value="{{ $c['logo_text'] ?? '' }}">
                </div>
                <div class="col-md-6">
                    <label class="jp-label">Primary color</label>
                    <input type="text" class="jp-control" disabled value="{{ $c['primary_color'] ?? '' }}">
                </div>
                <div class="col-md-6">
                    <label class="jp-label">Domain</label>
                    <input type="text" class="jp-control" disabled value="{{ $c['domain_preview'] ?? '' }}">
                </div>
                <div class="col-md-4">
                    <label class="jp-label">Support phone</label>
                    <input type="text" class="jp-control" disabled value="{{ $c['support_phone'] ?? '' }}">
                </div>
                <div class="col-md-4">
                    <label class="jp-label">WhatsApp</label>
                    <input type="text" class="jp-control" disabled value="{{ $c['support_whatsapp'] ?? '' }}">
                </div>
                <div class="col-md-4">
                    <label class="jp-label">Email</label>
                    <input type="email" class="jp-control" disabled value="{{ $c['support_email'] ?? '' }}">
                </div>
                <div class="col-12">
                    <label class="jp-label">Footer text</label>
                    <textarea class="jp-control" rows="2" disabled>{{ $c['footer_text'] ?? '' }}</textarea>
                </div>
                <div class="col-md-4">
                    <label class="jp-label">Facebook</label>
                    <input type="url" class="jp-control" disabled value="{{ $c['social_facebook'] ?? '' }}">
                </div>
                <div class="col-md-4">
                    <label class="jp-label">LinkedIn</label>
                    <input type="url" class="jp-control" disabled value="{{ $c['social_linkedin'] ?? '' }}">
                </div>
                <div class="col-md-4">
                    <label class="jp-label">Instagram</label>
                    <input type="url" class="jp-control" disabled value="{{ $c['social_instagram'] ?? '' }}">
                </div>
            </div>
            <div class="mt-4">
                <button type="button" class="jp-btn jp-btn--primary btn-planned-action" disabled>Save branding @include('components.planned-hint')</button>
            </div>
        </div>
    </div>
@endsection

