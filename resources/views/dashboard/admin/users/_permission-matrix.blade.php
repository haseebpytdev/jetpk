@php
    use App\Support\Access\RolePermissionMatrix;
@endphp

<div class="col-12">
    <div class="jp-card" id="user-permission-card" @if($initialAccountType === 'customer') hidden @endif>
        <div class="jp-card__head">
            <h3 class="jp-card__title mb-0">Permissions</h3>
        </div>
        <div class="jp-card__body">
            <div data-permission-panel="agent_staff" @if($initialAccountType !== 'agent_staff') hidden @endif>
                <p class="text-secondary mb-3">{{ RolePermissionMatrix::scopeNote(\App\Enums\AccountType::AgentStaff) }}</p>

                <div class="mb-3">
                    <label class="jp-label" for="owner_agent_id">Owner agent</label>
                    <select class="jp-control" name="owner_agent_id" id="owner_agent_id">
                        <option value="">Select agent…</option>
                        @foreach ($agencyAgents as $agentOption)
                            <option
                                value="{{ $agentOption->id }}"
                                @selected((string) old('owner_agent_id', $u->meta['owner_agent_id'] ?? '') === (string) $agentOption->id)
                            >
                                {{ $agentOption->user?->name ?? 'Agent' }} ({{ $agentOption->code }})
                            </option>
                        @endforeach
                    </select>
                    @error('owner_agent_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>

                <p class="text-secondary small mb-2">Toggle agent portal capabilities. Stored in <code>users.meta.agent_permissions</code>.</p>
                <div class="row g-2">
                    @foreach ($agentPermissionLabels as $key => $label)
                        <div class="col-md-6 col-lg-4">
                            <label class="form-check">
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    name="permissions[]"
                                    value="{{ $key }}"
                                    @checked(in_array($key, $selectedAgentPermissions, true))
                                >
                                <span class="form-check-label">{{ $label }}</span>
                            </label>
                        </div>
                    @endforeach
                </div>
            </div>

            @foreach ($permissionMatricesByType as $type => $rows)
                <div data-permission-panel="{{ $type }}" @if($initialAccountType !== $type) hidden @endif>
                    <p class="text-secondary mb-3">{{ $permissionScopeNotes[$type] ?? '' }}</p>
                    <p class="text-secondary small mb-3">Effective access for this role. Individual toggles are not editable unless a full RBAC editor is introduced later.</p>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>Capability area</th>
                                    <th>Access</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rows as $row)
                                    @php
                                        $badge = match ($row['access']) {
                                            RolePermissionMatrix::Allowed => 'bg-success',
                                            RolePermissionMatrix::Limited => 'bg-warning',
                                            default => 'bg-secondary',
                                        };
                                    @endphp
                                    <tr>
                                        <td>{{ $row['area'] }}</td>
                                        <td><span class="badge {{ $badge }}">{{ $row['access'] }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
