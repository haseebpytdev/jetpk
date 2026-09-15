@extends(client_layout('dashboard', 'admin'))

@section('title', 'Search Engine Verification')

@section('page-header')
    <div class="jp-between"><div class="col"><div class="page-pretitle"><a href="{{ route('admin.seo.overview') }}">SEO Management</a></div><h1 class="jp-page-title">Search Engine Verification</h1></div></div>
@endsection

@section('content')
    @include('dashboard.admin.seo.partials.nav', ['seoNav' => 'verification'])
    @if (session('status'))<div class="jp-alert jp-alert--success">{{ session('status') }}</div>@endif
    <div class="jp-alert jp-alert--info small">Precedence: published CMS value → environment fallback → none. Only one verification meta tag renders on the public site.</div>
    <form method="POST" action="{{ route('admin.seo.verification.update') }}" class="jp-card">
        @csrf @method('PATCH')
        <label class="jp-label">Google site verification token</label>
        <input class="jp-control" name="verification_google" value="{{ old('verification_google', $verification['google']) }}">
        <div class="form-hint">Effective live: {{ $verification['effective_google'] ?: ($verification['env_google'] ?: 'not set') }}</div>
        <label class="jp-label mt-3">Bing site verification token</label>
        <input class="jp-control" name="verification_bing" value="{{ old('verification_bing', $verification['bing']) }}">
        <div class="form-hint">Effective live: {{ $verification['effective_bing'] ?: ($verification['env_bing'] ?: 'not set') }}</div>
        <button type="submit" class="jp-btn jp-btn--primary mt-3">Save draft</button>
    </form>
    <form method="POST" action="{{ route('admin.seo.verification.publish') }}" class="mt-2">@csrf<button type="submit" class="jp-btn jp-btn--outline">Publish verification settings</button></form>
@endsection
