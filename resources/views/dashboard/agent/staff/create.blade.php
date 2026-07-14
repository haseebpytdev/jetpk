@extends(client_layout('agent-portal', 'agent'))

@section('title', 'Add staff user')

@section('account_title', 'Add staff user')
@section('account_subtitle', 'Create a staff login for your agency. They cannot access platform admin or create agents.')

@section('account_actions')
    <a href="{{ route('agent.staff.index') }}" class="ota-account-btn ota-account-btn--secondary">Back to staff</a>
@endsection

@section('account_content')
    <div class="ota-account-card ota-account-form-card" data-testid="agent-staff-create-form-card">
        <div class="ota-account-card__body">
            <form method="post" action="{{ route('agent.staff.store') }}" data-testid="agent-staff-create-form">
                @csrf
                @include('dashboard.agent.staff._form', ['selectedPermissions' => $defaultPermissions ?? []])
                <div class="ota-agent-form-actions mt-4">
                    <button type="submit" class="ota-account-btn ota-account-btn--primary">Create staff user</button>
                </div>
            </form>
        </div>
    </div>
@endsection
