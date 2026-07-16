<div class="jp-portal-card jp-portal-profile__password">
    <div class="jp-portal-card__head">
        <h2 class="jp-portal-card__title">Password</h2>
        <p class="jp-portal-profile__hint" style="margin:0">Use a strong, unique password for your account.</p>
    </div>
    <div class="jp-portal-card__body">
        <form method="post" action="{{ route('password.update') }}" class="jp-portal-profile__grid">
            @csrf
            @method('put')
            <div class="jp-portal-profile__field jp-portal-profile__field--full">
                <label class="jp-portal-profile__label" for="update_password_current_password">Current password</label>
                <input type="password" class="jp-portal-profile__input @error('current_password', 'updatePassword') is-invalid @enderror" id="update_password_current_password" name="current_password" autocomplete="current-password">
                @error('current_password', 'updatePassword')<p class="jp-portal-profile__error">{{ $message }}</p>@enderror
            </div>
            <div class="jp-portal-profile__field">
                <label class="jp-portal-profile__label" for="update_password_password">New password</label>
                <input type="password" class="jp-portal-profile__input @error('password', 'updatePassword') is-invalid @enderror" id="update_password_password" name="password" autocomplete="new-password">
                @error('password', 'updatePassword')<p class="jp-portal-profile__error">{{ $message }}</p>@enderror
            </div>
            <div class="jp-portal-profile__field">
                <label class="jp-portal-profile__label" for="update_password_password_confirmation">Confirm password</label>
                <input type="password" class="jp-portal-profile__input @error('password_confirmation', 'updatePassword') is-invalid @enderror" id="update_password_password_confirmation" name="password_confirmation" autocomplete="new-password">
                @error('password_confirmation', 'updatePassword')<p class="jp-portal-profile__error">{{ $message }}</p>@enderror
            </div>
            <div class="jp-portal-profile__actions">
                <button type="submit" class="jp-portal-btn jp-portal-btn--ghost">Update password</button>
                @if (session('status') === 'password-updated')
                    <span class="jp-portal-profile__success" role="status">Password updated.</span>
                @endif
            </div>
        </form>
    </div>
</div>
