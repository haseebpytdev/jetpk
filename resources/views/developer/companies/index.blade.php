@extends('layouts.developer')

@section('title', 'Platform Companies')

@section('page-header')
    <h1 class="ota-dev-cp-page-title h2 mb-1">Platform companies</h1>
    <p class="text-secondary mb-0">Agency tenants — assign packages and per-company module entitlements.</p>
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Agency</th>
                        <th>Slug</th>
                        <th>Entitlements</th>
                        <th>Assign package</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($agencies as $agency)
                        <tr>
                            <td>{{ $agency->name }}</td>
                            <td><code>{{ $agency->slug }}</code></td>
                            <td>{{ $entitlementCounts[$agency->id] ?? 0 }}</td>
                            <td>
                                <form method="POST" action="{{ route('dev.cp.companies.package', $agency) }}" class="d-flex gap-2">
                                    @csrf
                                    <select name="package_id" class="form-select form-select-sm" required>
                                        <option value="">Select package…</option>
                                        @foreach ($packages as $package)
                                            <option value="{{ $package->id }}">{{ $package->label }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-primary">Apply</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-secondary">No agencies found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($agencies->hasPages())
            <div class="card-footer">{{ $agencies->links() }}</div>
        @endif
    </div>
@endsection
