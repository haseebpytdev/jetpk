<section class="ota-profile-danger-zone" aria-labelledby="ota-profile-danger-heading">
    <details class="ota-profile-danger">
        <summary class="ota-profile-danger-summary" id="ota-profile-danger-heading">
            <span class="ota-profile-danger-title">Advanced account actions</span>
            <span class="ota-profile-danger-hint">Optional · permanent deletion</span>
        </summary>
        <div class="ota-profile-danger-body">
            <p class="ota-profile-danger-copy">Permanently delete your account and associated profile data. This cannot be undone.</p>
            <button type="button" class="ota-profile-danger-btn" data-bs-toggle="modal" data-bs-target="#confirm-user-deletion">
                Delete my account
            </button>
        </div>
    </details>
</section>

<div class="modal fade" id="confirm-user-deletion" tabindex="-1" aria-labelledby="confirm-user-deletion-label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="{{ route('profile.destroy') }}">
                @csrf
                @method('delete')
                <div class="modal-header">
                    <h2 class="modal-title h5" id="confirm-user-deletion-label">Delete account?</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-secondary">Enter your password to confirm permanent deletion.</p>
                    <label class="ota-profile-label" for="password">Password</label>
                    <input type="password" class="ota-profile-input form-control @error('password', 'userDeletion') is-invalid @enderror" id="password" name="password" autocomplete="current-password">
                    @error('password', 'userDeletion')<div class="ota-profile-field-error">{{ $message }}</div>@enderror
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete account</button>
                </div>
            </form>
        </div>
    </div>
</div>

@if ($errors->userDeletion->isNotEmpty())
    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var el = document.getElementById('confirm-user-deletion');
            if (el && window.bootstrap && bootstrap.Modal) {
                bootstrap.Modal.getOrCreateInstance(el).show();
            }
        });
    </script>
    @endpush
@endif
