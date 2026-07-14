@extends('layouts.developer')

@section('title', 'Dashboards Status')

@section('page-header')
    <h1 class="ota-dev-cp-page-title h2 mb-1">Dashboard status</h1>
    <p class="text-secondary mb-0">Portal module gates and enforcement summary.</p>
@endsection

@section('content')
    <div class="card">
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Portal</th>
                        <th>Module</th>
                        <th>Enabled</th>
                        <th>Route guarded</th>
                        <th>Backend enforced</th>
                        <th>Display</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($snapshot['portals'] ?? [] as $portal)
                        <tr>
                            <td>{{ $portal['label'] }}</td>
                            <td><code>{{ $portal['module_key'] }}</code></td>
                            <td>{{ ($portal['effective_enabled'] ?? false) ? 'Yes' : 'No' }}</td>
                            <td>{{ ($portal['route_guarded'] ?? false) ? 'Yes' : 'No' }}</td>
                            <td>{{ ($portal['backend_enforced'] ?? false) ? 'Yes' : 'No' }}</td>
                            <td class="small text-secondary">{{ $portal['display'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
