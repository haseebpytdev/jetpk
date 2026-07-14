@extends('layouts.developer')

@section('title', 'Client Profiles')

@section('page-header')
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
        <div>
            <h1 class="ota-dev-cp-page-title h2 mb-1">Client profiles</h1>
            <p class="text-secondary mb-0">Manage deployment-level client profiles stored in the database.</p>
        </div>
        <a href="{{ route('dev.cp.clients.create') }}" class="btn btn-primary">Create client</a>
    </div>
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($errors->has('export'))
        <div class="alert alert-danger">{{ $errors->first('export') }}</div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table table-vcenter card-table table-sm">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Slug</th>
                        <th>Domain</th>
                        <th>Theme</th>
                        <th>Environment</th>
                        <th>Master</th>
                        <th>Active</th>
                        <th class="w-1">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($profiles as $profile)
                        <tr>
                            <td>{{ $profile->name }}</td>
                            <td><code>{{ $profile->slug }}</code></td>
                            <td>{{ $profile->domain ?? '—' }}</td>
                            <td>{{ $profile->active_frontend_theme }}</td>
                            <td>{{ $profile->environment }}</td>
                            <td>
                                @if ($profile->is_master_profile)
                                    <span class="badge bg-warning text-dark">Master</span>
                                @else
                                    <span class="text-secondary">—</span>
                                @endif
                            </td>
                            <td>
                                @if ($profile->is_active)
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-secondary">Inactive</span>
                                @endif
                            </td>
                            <td>
                                <div class="d-flex flex-wrap gap-1">
                                    <a href="{{ route('dev.cp.clients.edit', $profile) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                    <form method="POST" action="{{ route('dev.cp.clients.export', $profile) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">Export</button>
                                    </form>
                                    <button type="button" class="btn btn-sm btn-outline-secondary"
                                            data-bs-toggle="modal"
                                            data-bs-target="#duplicate-modal-{{ $profile->id }}">
                                        Duplicate
                                    </button>
                                </div>
                                @include('developer.clients.partials.duplicate-modal', ['profile' => $profile])
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-secondary">No client profiles yet. Create one or run <code>ota:sync-current-client-profile</code>.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($profiles->hasPages())
            <div class="card-footer">{{ $profiles->links() }}</div>
        @endif
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@endpush
