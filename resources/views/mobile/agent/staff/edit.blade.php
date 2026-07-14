@extends('layouts.mobile-app')

@section('title', 'Edit staff user')

@section('mobile_app_title', 'Edit staff')

@section('mobile_app_back')
    <a href="{{ route('agent.staff.index') }}" class="ota-mobile-app__back-btn" aria-label="Back to staff list">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
    </a>
@endsection

@section('content')
    @php
        $selected = collect(old('permissions', $selectedPermissions ?? []))->all();
    @endphp

    <div class="ota-mobile-agent" data-testid="ota-mobile-agent-staff-edit">
        @if (session('status') === 'staff-permissions-updated')
            <div class="ota-mobile-agent__alert ota-mobile-agent__alert--success">Permissions saved.</div>
        @elseif (session('status') === 'staff-permissions-template-applied')
            <div class="ota-mobile-agent__alert ota-mobile-agent__alert--success">Role template applied.</div>
        @endif

        <div class="ota-mobile-agent__card ota-mobile-agent__form-card">
            <h1 class="ota-mobile-agent__page-title">Edit {{ $staff->name }}</h1>

            @include('partials.agent-staff-access-clarification', [
                'showAgentStaffOwnerLabelWarning' => $showAgentStaffOwnerLabelWarning ?? false,
                'showOwnerAccountTypeNote' => false,
                'showApplyTemplateHint' => true,
            ])

            <form method="post" action="{{ route('agent.staff.update', $staff) }}" class="ota-mobile-agent__form" data-testid="agent-staff-edit-form">
                @csrf
                @method('PATCH')

                <div class="ota-mobile-agent__field">
                    <label class="ota-mobile-agent__label" for="name">Full name</label>
                    <input type="text" name="name" id="name" class="ota-mobile-agent__input{{ $errors->has('name') ? ' is-invalid' : '' }}" value="{{ old('name', $staff->name) }}" required maxlength="160">
                    @error('name')<p class="ota-mobile-agent__error">{{ $message }}</p>@enderror
                </div>

                <div class="ota-mobile-agent__field">
                    <label class="ota-mobile-agent__label" for="email">Email</label>
                    <input type="email" name="email" id="email" class="ota-mobile-agent__input{{ $errors->has('email') ? ' is-invalid' : '' }}" value="{{ old('email', $staff->email) }}" required maxlength="160">
                    @error('email')<p class="ota-mobile-agent__error">{{ $message }}</p>@enderror
                </div>

                <div class="ota-mobile-agent__field">
                    <label class="ota-mobile-agent__label" for="phone">Phone</label>
                    <input type="text" name="phone" id="phone" class="ota-mobile-agent__input" value="{{ old('phone', $staff->meta['phone'] ?? '') }}" maxlength="40">
                </div>

                <div class="ota-mobile-agent__field">
                    <label class="ota-mobile-agent__label" for="password">New password (optional)</label>
                    <input type="password" name="password" id="password" class="ota-mobile-agent__input" minlength="8" autocomplete="new-password">
                    @error('password')<p class="ota-mobile-agent__error">{{ $message }}</p>@enderror
                </div>

                <div class="ota-mobile-agent__field">
                    <label class="ota-mobile-agent__label" for="status">Status</label>
                    <select name="status" id="status" class="ota-mobile-agent__input" required>
                        @foreach (\App\Enums\UserAccountStatus::cases() as $statusCase)
                            @if (in_array($statusCase->value, ['active', 'inactive', 'suspended'], true))
                                <option value="{{ $statusCase->value }}" @selected(old('status', $staff->status?->value) === $statusCase->value)>{{ ucfirst($statusCase->value) }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>

                <button type="submit" class="ota-mobile-agent__btn ota-mobile-agent__btn--primary ota-mobile-agent__btn--block">Save profile</button>
            </form>

            @if (! empty($permissionsUpdateRoute))
                <form method="post" action="{{ $permissionsUpdateRoute }}" class="ota-mobile-agent__form mt-3" data-testid="agent-staff-mobile-permissions-form">
                    @csrf
                    @method('PATCH')
                    <fieldset class="ota-mobile-agent__fieldset">
                        <legend class="ota-mobile-agent__fieldset-title">Permissions</legend>
                        <div class="ota-mobile-agent__perm-grid">
                            @foreach ($permissionLabels as $key => $label)
                                <label class="ota-mobile-agent__perm-option">
                                    <input type="checkbox" name="permissions[]" value="{{ $key }}" @checked(in_array($key, $selected, true))>
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                    <button type="submit" class="ota-mobile-agent__btn ota-mobile-agent__btn--primary ota-mobile-agent__btn--block">Save Permissions</button>
                </form>
            @endif

            @if (($canApplyAgentStaffRoleTemplate ?? false) && ! empty($agentPermissionsApplyTemplateRoute))
                <form
                    method="post"
                    action="{{ $agentPermissionsApplyTemplateRoute }}"
                    class="ota-mobile-agent__form mt-2"
                    data-testid="agent-staff-mobile-apply-template-form"
                    onsubmit="return confirm('Apply the permission template for the current agency role?');"
                >
                    @csrf
                    <input type="hidden" name="confirm_template_apply" value="1">
                    <p class="ota-mobile-agent__hint text-secondary small mb-2">Apply Template copies suggested permissions for the selected role.</p>
                    <button type="submit" class="ota-mobile-agent__btn ota-mobile-agent__btn--secondary ota-mobile-agent__btn--block">Apply Template</button>
                </form>
            @endif

            @if (auth()->user()?->isAgentAdmin())
                <form method="post" action="{{ route('agent.staff.destroy', $staff) }}" class="ota-mobile-agent__form ota-mobile-agent__danger-zone" data-testid="agent-staff-disable-form" onsubmit="return confirm('Disable this staff user? They will no longer be able to sign in.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="ota-mobile-agent__btn ota-mobile-agent__btn--danger ota-mobile-agent__btn--block">Disable staff user</button>
                </form>
            @endif
        </div>
    </div>
@endsection
