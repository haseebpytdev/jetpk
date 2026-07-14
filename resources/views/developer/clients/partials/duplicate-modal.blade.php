<div class="modal fade" id="duplicate-modal-{{ $profile->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('dev.cp.clients.duplicate', $profile) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Duplicate {{ $profile->name }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    @if ($profile->is_master_profile)
                        <div class="alert alert-warning">
                            You are duplicating the master profile. Confirm to proceed.
                        </div>
                        <div class="mb-3">
                            <label class="form-check">
                                <input type="checkbox" name="confirm_master_edit" value="1" class="form-check-input" required>
                                <span class="form-check-label">I confirm duplicating the master deployment profile</span>
                            </label>
                        </div>
                    @endif
                    <div class="mb-3">
                        <label class="form-label" for="new_name_{{ $profile->id }}">New name</label>
                        <input type="text" name="new_name" id="new_name_{{ $profile->id }}" class="form-control"
                               value="{{ old('new_name', $profile->name.' Copy') }}" required maxlength="255">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="new_slug_{{ $profile->id }}">New slug</label>
                        <input type="text" name="new_slug" id="new_slug_{{ $profile->id }}" class="form-control"
                               value="{{ old('new_slug', $profile->slug.'-copy') }}" required maxlength="255" pattern="[A-Za-z0-9_-]+">
                    </div>
                    <div class="mb-0">
                        <label class="form-check">
                            <input type="checkbox" name="copy_credentials" value="1" class="form-check-input">
                            <span class="form-check-label">Copy supplier credentials (not recommended)</span>
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Duplicate</button>
                </div>
            </form>
        </div>
    </div>
</div>
