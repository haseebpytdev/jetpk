@extends(client_layout('dashboard', 'admin'))

@section('title', 'Roles & permissions')

@section('page-header')
    <div class="jp-between">
        <div class="col">
            <div class="page-pretitle">Access model</div>
            <h1 class="jp-page-title">Roles &amp; permissions</h1>
            <div class="text-secondary mt-1">
                Read-only matrix based on account types, middleware route groups, and policy behavior.
            </div>
        </div>
    </div>
@endsection

@section('content')
    <div class="jp-card">
        <div class="card-body border-bottom py-3">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary">Read-only</span>
                <span class="text-secondary">This matrix reflects current middleware and policy behavior. Changes should be made through code/configuration until a full RBAC editor is introduced.</span>
            </div>
        </div>
        <div class="table-responsive">
            <table class="jp-jp-table">
                <thead>
                    <tr>
                        <th>Capability area</th>
                        @foreach ($accountTypes as $accountType)
                            <th>{{ $accountType['label'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($matrix as $row)
                        <tr>
                            <td class="fw-semibold">{{ $row['area'] }}</td>
                            @foreach ($accountTypes as $accountType)
                                @php
                                    $value = $row[$accountType['key']] ?? 'Denied';
                                    $badge = match ($value) {
                                        'Allowed' => 'bg-success',
                                        'Limited' => 'bg-warning',
                                        default => 'bg-secondary',
                                    };
                                @endphp
                                <td><span class="badge {{ $badge }}">{{ $value }}</span></td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection

