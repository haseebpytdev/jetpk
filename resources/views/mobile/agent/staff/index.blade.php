@extends('layouts.mobile-app')

@section('title', 'Staff users')

@section('mobile_app_title', 'Staff')

@section('mobile_app_top_actions')
    @if (Route::has('agent.staff.create'))
        <a href="{{ route('agent.staff.create') }}" class="ota-mobile-app__top-action" data-testid="ota-mobile-agent-staff-create-link">Add</a>
    @endif
@endsection

@section('content')
    <div class="ota-mobile-agent" data-testid="ota-mobile-agent-staff-index">
        @if (session('status') === 'staff-created')
            @include('mobile.components.alert', ['type' => 'success', 'message' => 'Staff user created.'])
        @elseif (session('status') === 'staff-updated')
            @include('mobile.components.alert', ['type' => 'success', 'message' => 'Staff user updated.'])
        @elseif (session('status') === 'staff-disabled')
            @include('mobile.components.alert', ['type' => 'success', 'message' => 'Staff user disabled.'])
        @endif

        @if ($staffMembers->isEmpty())
            <div class="ota-mobile-agent__empty">
                <p class="ota-mobile-agent__empty-title">No staff users yet</p>
                <p class="ota-mobile-agent__empty-help">Add a staff member to share portal access with your team.</p>
                <a href="{{ route('agent.staff.create') }}" class="ota-mobile-agent__btn ota-mobile-agent__btn--primary">Add staff user</a>
            </div>
        @else
            <div class="ota-mobile-agent__list">
                @foreach ($staffMembers as $member)
                    @php
                        $perms = is_array($member->meta['agent_permissions'] ?? null) ? $member->meta['agent_permissions'] : [];
                    @endphp
                    <article class="ota-mobile-agent__card" data-testid="agent-staff-row-{{ $member->id }}">
                        <div class="ota-mobile-agent__card-head">
                            <span class="ota-mobile-agent__ref">{{ $member->name }}</span>
                            @include('mobile.agent.partials.agent-status-pill', [
                                'label' => ucfirst($member->status?->value ?? 'unknown'),
                            ])
                        </div>
                        <p class="ota-mobile-agent__text-safe ota-mobile-agent__muted">{{ $member->email }}</p>
                        @if ($perms !== [])
                            <div class="ota-mobile-agent__perm-chips">
                                @foreach ($perms as $perm)
                                    @include('mobile.agent.partials.permission-chip', [
                                        'permission' => $perm,
                                        'label' => $permissionLabels[$perm] ?? null,
                                    ])
                                @endforeach
                            </div>
                        @endif
                        <div class="ota-mobile-agent__actions">
                            <a href="{{ route('agent.staff.edit', $member) }}" class="ota-mobile-agent__btn ota-mobile-agent__btn--primary">Edit</a>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
@endsection
