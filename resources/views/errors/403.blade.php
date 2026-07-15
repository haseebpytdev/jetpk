{{--
  errors/403.blade.php — permission-denied state (Phase 6: Agent Staff).
  JETPK-DASHBOARD-UI-FOUNDATION · baseline 6fbfae4

  WHY THIS FILE: agent.permission / agent.admin denial is `abort(403)` (EnsureAgentPermission,
  EnsureAgentAdmin), so THIS page *is* the Agent Staff permission-denied UX (Phase 0 gap P-1).
  Baseline showed a generic "You do not have permission to access this area." with no indication
  of what controls access and no route back into the portal.

  WHAT CHANGED (UI only — no middleware, policy, or permission logic touched):
    - Agent-portal users (agent + agent_staff) get a portal-aware return action.
    - Agent Staff additionally get a hint naming the Permission Matrix as the access control,
      matching the wording already used in partials/agent-staff-access-clarification.
    - Every other role/guest keeps the baseline behaviour EXACTLY: the `actions` section is only
      declared for agent-portal users, so `errors.layout`'s @hasSection('actions') stays false and
      its default actions render unchanged. (Conditional @section is an established pattern in this
      repo — see dashboard/travelers/*.)
    - `$message` passed by abort(403, '...') still wins, so backend-supplied copy is preserved.

  Does NOT leak more than policy allows: it never names the specific missing permission or the
  target resource — only who administers access.
--}}
@extends('errors.layout')

@php
    $deniedUser = auth()->user();
    $deniedIsAgentStaff = $deniedUser?->isAgentStaff() ?? false;
    $deniedIsAgentPortal = $deniedUser?->isAgentPortalUser() ?? false;
    $deniedHasAgentHome = $deniedIsAgentPortal && \Illuminate\Support\Facades\Route::has('agent.dashboard');
@endphp

@section('title', 'Forbidden')
@section('heading', 'Access Restricted')
@section('message', $message ?? ($deniedIsAgentStaff
    ? "Your account doesn't have permission for this section of the agent portal."
    : 'You do not have permission to access this area.'))

@if ($deniedIsAgentStaff)
    @push('after-message')
        <p class="support" data-testid="agent-staff-denied-hint">
            Portal access is controlled by your agency's Permission Matrix. Ask your agency
            administrator to grant the access you need.
        </p>
    @endpush
@endif

@if ($deniedHasAgentHome)
    @section('actions')
        <a href="{{ client_route('agent.dashboard') }}" class="btn btn-primary" data-testid="denied-return-agent-portal">Back to agent portal</a>
        <a href="{{ route('support') }}" class="btn btn-secondary">Contact Support</a>
    @endsection
@endif
