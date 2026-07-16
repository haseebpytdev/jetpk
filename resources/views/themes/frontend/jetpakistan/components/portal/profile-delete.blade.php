<div class="jp-portal-card jp-portal-profile__danger">
    <div class="jp-portal-card__head">
        <h2 class="jp-portal-card__title">Delete account</h2>
    </div>
    <div class="jp-portal-card__body">
        <p class="jp-portal-profile__hint">Permanently delete your account and associated profile data. This cannot be undone.</p>
        <form method="post" action="{{ route('profile.destroy') }}" class="jp-portal-profile__grid">
            @csrf
            @method('delete')
            <div class="jp-portal-profile__field jp-portal-profile__field--full">
                <label class="jp-portal-profile__label" for="delete_password">Confirm with password</label>
                <input type="password" class="jp-portal-profile__input @error('password', 'userDeletion') is-invalid @enderror" id="delete_password" name="password" autocomplete="current-password">
                @error('password', 'userDeletion')<p class="jp-portal-profile__error">{{ $message }}</p>@enderror
            </div>
            <div class="jp-portal-profile__actions">
                <button type="submit" class="jp-portal-btn jp-portal-btn--ghost" onclick="return confirm('Delete your account permanently?');">Delete my account</button>
            </div>
        </form>
    </div>
</div>
