@extends(client_layout('frontend', 'frontend'))

@section('title', 'Request travel consultation — '.($client['agency_name'] ?? 'OTA'))

@push('styles')
<style>
    .ota-rd-wrap { max-width: 720px; margin: 5rem auto 4rem; padding: 0 1rem; }
    .ota-rd-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 2rem; box-shadow: 0 12px 40px rgba(15,23,42,.08); }
    .ota-rd-card h1 { font-size: 1.65rem; font-weight: 800; margin: 0 0 .5rem; color: #0f172a; }
    .ota-rd-note { font-size: .9rem; color: #64748b; margin-bottom: 1.5rem; }
</style>
@endpush

@section('content')
    @php $c = $client ?? []; @endphp
    <div class="ota-rd-wrap">
        <div class="ota-rd-card">
            <h1>Request travel consultation</h1>
            <p class="ota-rd-note">Share your requirement and our team will reach out. You can also contact us by <a href="mailto:{{ $c['support_email'] ?? '' }}">email</a> or WhatsApp.</p>

            <form class="ota-rd-form" method="post" action="#" onsubmit="return false;">
                <div class="form-group">
                    <label class="control-label">Your name</label>
                    <input type="text" class="form-control" disabled placeholder="Full name">
                </div>
                <div class="form-group">
                    <label class="control-label">Agency name</label>
                    <input type="text" class="form-control" disabled placeholder="Travel agency / TMC">
                </div>
                <div class="row">
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label class="control-label">Phone / WhatsApp</label>
                            <input type="text" class="form-control" disabled placeholder="+92 …">
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label class="control-label">Email</label>
                            <input type="email" class="form-control" disabled placeholder="you@agency.com">
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label class="control-label">Required modules</label>
                    @foreach ($modules as $key => $label)
                        <div class="checkbox disabled">
                            <label><input type="checkbox" disabled> {{ $label }}</label>
                        </div>
                    @endforeach
                </div>
                <div class="form-group">
                    <label class="control-label">Do you already have Sabre / PIA / airline API access?</label>
                    <select class="form-control" disabled>
                        <option>Select…</option>
                        <option>Yes — credentials in hand</option>
                        <option>Not yet — need guidance</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="control-label">Message</label>
                    <textarea class="form-control" rows="4" disabled placeholder="Tell us about routes, volumes, and timelines."></textarea>
                </div>
                <button type="button" class="btn btn-lg btn-primary btn-block" disabled style="opacity:.65;">Submit request</button>
            </form>
        </div>
    </div>
@endsection
