@php
    $staffModel = $staff ?? null;
    $selected = collect(old('permissions', $selectedPermissions ?? []))->all();
@endphp

<div class="mb-3">
    <label class="form-label" for="name">Full name</label>
    <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $staffModel?->name) }}" required maxlength="160">
    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label class="form-label" for="email">Email</label>
    <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $staffModel?->email) }}" required maxlength="160">
    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label class="form-label" for="phone">Phone</label>
    <input type="text" name="phone" id="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $staffModel?->meta['phone'] ?? '') }}" maxlength="40">
    @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label class="form-label" for="password">{{ isset($staffModel) ? 'New password (optional)' : 'Password' }}</label>
    <input type="password" name="password" id="password" class="form-control @error('password') is-invalid @enderror" {{ isset($staffModel) ? '' : 'required' }} minlength="8" autocomplete="new-password">
    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

@if (isset($staffModel))
    <div class="mb-3">
        <label class="form-label" for="status">Status</label>
        <select name="status" id="status" class="form-select @error('status') is-invalid @enderror" required>
            @foreach (\App\Enums\UserAccountStatus::cases() as $statusCase)
                @if (in_array($statusCase->value, ['active', 'inactive', 'suspended'], true))
                    <option value="{{ $statusCase->value }}" @selected(old('status', $staffModel->status?->value) === $statusCase->value)>{{ ucfirst($statusCase->value) }}</option>
                @endif
            @endforeach
        </select>
        @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
@endif

@if ($showPermissionFieldset ?? true)
    <fieldset class="ota-agent-staff-permissions mb-0">
        <legend class="ota-agent-form-section__title">Permissions</legend>
        <p class="ota-agent-form-section__hint">Staff cannot create agents or access platform admin areas. <code>Manage staff</code> is off by default.</p>
        <div class="ota-agent-staff-permissions__grid">
            @foreach ($permissionLabels as $key => $label)
                <label class="ota-agent-staff-permissions__item">
                    <input type="checkbox" name="permissions[]" value="{{ $key }}" @checked(in_array($key, $selected, true))>
                    <span>{{ $label }}</span>
                </label>
            @endforeach
        </div>
    </fieldset>
@endif
