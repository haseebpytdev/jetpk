<section class="ota-profile-section ota-profile-section--password" aria-labelledby="ota-profile-password-heading">
    <div class="ota-profile-section-header">
        <h3 class="ota-profile-section-title" id="ota-profile-password-heading">Password</h3>
        <p class="ota-profile-section-lead">Use a strong, unique password for your account.</p>
    </div>
    <form method="post" action="{{ route('password.update') }}" class="ota-profile-password-form">
        @csrf
        @method('put')

        <div class="ota-profile-form-grid is-password">
            <div class="ota-profile-field ota-profile-field--full">
                <label class="ota-profile-label" for="update_password_current_password">Current password</label>
                <input type="password" class="ota-profile-input form-control @error('current_password', 'updatePassword') is-invalid @enderror" id="update_password_current_password" name="current_password" autocomplete="current-password">
                @error('current_password', 'updatePassword')<div class="ota-profile-field-error">{{ $message }}</div>@enderror
            </div>
            <div class="ota-profile-field">
                <label class="ota-profile-label" for="update_password_password">New password</label>
                <input type="password" class="ota-profile-input form-control @error('password', 'updatePassword') is-invalid @enderror" id="update_password_password" name="password" autocomplete="new-password">
                @error('password', 'updatePassword')<div class="ota-profile-field-error">{{ $message }}</div>@enderror
            </div>
            <div class="ota-profile-field">
                <label class="ota-profile-label" for="update_password_password_confirmation">Confirm password</label>
                <input type="password" class="ota-profile-input form-control @error('password_confirmation', 'updatePassword') is-invalid @enderror" id="update_password_password_confirmation" name="password_confirmation" autocomplete="new-password">
                @error('password_confirmation', 'updatePassword')<div class="ota-profile-field-error">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="ota-profile-section-actions ota-r-action-bar">
            <button type="submit" class="ota-profile-password-btn ota-btn">Update password</button>
            @if (session('status') === 'password-updated')
                <span class="ota-profile-status-ok" role="status">Password updated.</span>
            @endif
        </div>
    </form>
</section>
