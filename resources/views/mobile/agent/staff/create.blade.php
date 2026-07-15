@extends(client_layout('mobile-app', 'mobile'))

@section('title', 'Add staff user')

@section('mobile_app_title', 'Add staff')

@section('mobile_app_back')
    <a href="{{ route('agent.staff.index') }}" class="ota-mobile-app__back-btn" aria-label="Back to staff list">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
    </a>
@endsection

@section('content')
    @php
        $selected = collect(old('permissions', $defaultPermissions ?? []))->all();
    @endphp

    <div class="ota-mobile-agent" data-testid="ota-mobile-agent-staff-create">
        <div class="ota-mobile-agent__card ota-mobile-agent__form-card">
            <h1 class="ota-mobile-agent__page-title">Add staff user</h1>
            <p class="ota-mobile-agent__note">Create a staff login for your agency. They cannot access platform admin or create agents.</p>

            <form method="post" action="{{ route('agent.staff.store') }}" class="ota-mobile-agent__form" data-testid="agent-staff-create-form">
                @csrf

                <div class="ota-mobile-agent__field">
                    <label class="ota-mobile-agent__label" for="name">Full name</label>
                    <input type="text" name="name" id="name" class="ota-mobile-agent__input{{ $errors->has('name') ? ' is-invalid' : '' }}" value="{{ old('name') }}" required maxlength="160">
                    @error('name')<p class="ota-mobile-agent__error">{{ $message }}</p>@enderror
                </div>

                <div class="ota-mobile-agent__field">
                    <label class="ota-mobile-agent__label" for="email">Email</label>
                    <input type="email" name="email" id="email" class="ota-mobile-agent__input{{ $errors->has('email') ? ' is-invalid' : '' }}" value="{{ old('email') }}" required maxlength="160">
                    @error('email')<p class="ota-mobile-agent__error">{{ $message }}</p>@enderror
                </div>

                <div class="ota-mobile-agent__field">
                    <label class="ota-mobile-agent__label" for="phone">Phone</label>
                    <input type="text" name="phone" id="phone" class="ota-mobile-agent__input" value="{{ old('phone') }}" maxlength="40">
                </div>

                <div class="ota-mobile-agent__field">
                    <label class="ota-mobile-agent__label" for="password">Password</label>
                    <input type="password" name="password" id="password" class="ota-mobile-agent__input{{ $errors->has('password') ? ' is-invalid' : '' }}" required minlength="8" autocomplete="new-password">
                    @error('password')<p class="ota-mobile-agent__error">{{ $message }}</p>@enderror
                </div>

                <fieldset class="ota-mobile-agent__fieldset">
                    <legend class="ota-mobile-agent__fieldset-title">Permissions</legend>
                    <p class="ota-mobile-agent__note">Staff cannot create agents or access platform admin areas.</p>
                    <div class="ota-mobile-agent__perm-grid">
                        @foreach ($permissionLabels as $key => $label)
                            <label class="ota-mobile-agent__perm-option">
                                <input type="checkbox" name="permissions[]" value="{{ $key }}" @checked(in_array($key, $selected, true))>
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <button type="submit" class="ota-mobile-agent__btn ota-mobile-agent__btn--primary ota-mobile-agent__btn--block">Create staff user</button>
            </form>
        </div>
    </div>
@endsection
