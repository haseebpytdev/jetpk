@extends('layouts.developer')

@section('title', 'Platform Admin Accounts')

@section('page-header')
    <h1 class="ota-dev-cp-page-title h2 mb-1">Platform Admin accounts</h1>
    <p class="text-secondary mb-0">
        Create and hand off Platform Admin credentials for this deployment.
        Staff, agents, customers, and agencies are managed inside the OTA Admin Panel.
    </p>
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($errors->has('user'))
        <div class="alert alert-danger">{{ $errors->first('user') }}</div>
    @endif

    @if (session('dev_cp_temp_password'))
        <div class="alert alert-warning" role="alert">
            <strong>Temporary password (shown once)</strong> for
            <code>{{ session('dev_cp_temp_password_email') }}</code>:
            <span class="user-select-all fw-bold">{{ session('dev_cp_temp_password') }}</span>
            <div class="small mt-2 mb-0">Store securely and share with the client. They must change it on first login.</div>
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-header"><h3 class="card-title mb-0">Create Platform Admin</h3></div>
        <div class="card-body">
            <form method="POST" action="{{ route('dev.cp.users.store') }}" class="row g-3">
                @csrf
                <div class="col-md-4">
                    <label class="form-label" for="name">Name</label>
                    <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name') }}" required maxlength="255">
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="email">Email</label>
                    <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror"
                           value="{{ old('email') }}" required maxlength="255">
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">Create account</button>
                </div>
            </form>
        </div>
    </div>

    <form method="GET" class="row g-2 mb-3">
        <div class="col-auto">
            <select name="status" class="form-select form-select-sm">
                <option value="">All statuses</option>
                @foreach (['active', 'inactive'] as $status)
                    <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $status }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-auto"><button type="submit" class="btn btn-sm btn-outline-primary">Filter</button></div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-vcenter card-table table-sm">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Must change pwd</th>
                        <th class="w-1">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td>{{ $user->name }}</td>
                            <td>{{ $user->email }}</td>
                            <td>{{ $user->status?->value ?? '—' }}</td>
                            <td>{{ ($user->must_change_password ?? false) ? 'Yes' : 'No' }}</td>
                            <td>
                                <div class="d-flex flex-wrap gap-1">
                                    <form method="POST" action="{{ route('dev.cp.users.reset-password', $user) }}"
                                          onsubmit="return confirm('Reset password for {{ $user->email }}? A new temporary password will be shown once.');">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-warning">Reset password</button>
                                    </form>
                                    @if ($user->status?->value === 'active')
                                        @if ($activePlatformAdminCount > 1)
                                            <form method="POST" action="{{ route('dev.cp.users.status', $user) }}">
                                                @csrf
                                                <input type="hidden" name="status" value="inactive">
                                                <button type="submit" class="btn btn-sm btn-outline-secondary">Deactivate</button>
                                            </form>
                                        @endif
                                    @else
                                        <form method="POST" action="{{ route('dev.cp.users.status', $user) }}">
                                            @csrf
                                            <input type="hidden" name="status" value="active">
                                            <button type="submit" class="btn btn-sm btn-outline-success">Reactivate</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-secondary">No platform admin accounts yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $users->links() }}</div>
    </div>
@endsection
