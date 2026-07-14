@php
    $recentPermissionAuditLogs = $recentPermissionAuditLogs ?? [];
    $agencyActivityUrl = $agencyActivityUrl ?? null;
@endphp

@if (($showRecentPermissionAuditPanel ?? false) && count($recentPermissionAuditLogs) > 0)
    <div class="card ota-access-panel mb-3" data-testid="recent-permission-changes-panel">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h3 class="card-title mb-0">Recent permission changes</h3>
            @if ($agencyActivityUrl)
                <a href="{{ $agencyActivityUrl }}" class="btn btn-sm btn-outline-secondary">View all agency activity</a>
            @endif
        </div>
        <div class="card-body p-0">
            <div class="table-responsive ota-r-table-wrap">
                <table class="table table-sm table-vcenter mb-0">
                    <thead>
                        <tr>
                            <th>When</th>
                            <th>Actor</th>
                            <th>Source</th>
                            <th>Permissions</th>
                            <th>Agency role</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recentPermissionAuditLogs as $entry)
                            <tr data-testid="recent-permission-change-row-{{ $entry['id'] }}">
                                <td class="text-nowrap">{{ $entry['created_at'] }}</td>
                                <td>{{ $entry['actor_name'] }}</td>
                                <td>{{ $entry['source_label'] }}</td>
                                <td>{{ $entry['old_count'] }} → {{ $entry['new_count'] }}</td>
                                <td>{{ $entry['agency_role_label'] ?? '—' }}</td>
                                <td class="text-end">
                                    @if (! empty($entry['properties']))
                                        <details class="small">
                                            <summary class="text-secondary">Details</summary>
                                            <pre class="small text-secondary mb-0 mt-1 text-wrap">{{ json_encode($entry['properties'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                        </details>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endif
