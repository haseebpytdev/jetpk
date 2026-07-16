{{-- JP-PORTAL-3 TASK 7 · JetPK portal Agent Staff form (create + edit)
     Replaces dashboard.agent.staff._form on JetPK-resolved pages. The legacy partial REMAINS on
     disk untouched for default/Parwaaz clients.

     Preserved verbatim from dashboard.agent.staff._form:
       • $staffModel = $staff ?? null  — presence of $staff is what switches create vs edit
       • $selected = collect(old('permissions', $selectedPermissions ?? []))->all()
       • field names: name, email, phone, password, status, permissions[]
       • name: required, maxlength="160", old('name', $staffModel?->name)
       • email: required, maxlength="160", old('email', $staffModel?->email)
       • phone: optional, maxlength="40", old('phone', $staffModel?->meta['phone'] ?? '')
         (phone lives in meta — NOT a column)
       • password: label switches to "New password (optional)" when editing; `required` ONLY on
         create; minlength="8"; autocomplete="new-password"; value NEVER echoed
       • status select rendered ONLY when editing, required, filtered to exactly
         ['active','inactive','suspended'] out of UserAccountStatus::cases() — the filter is
         reproduced verbatim so no other status becomes assignable from the portal
       • permissions fieldset gated by ($showPermissionFieldset ?? true) — the EDIT view passes
         false because the richer matrix component renders there instead
       • the hint copy naming `Manage staff` as off by default, verbatim
       • every @error block present in legacy; none invented
     Permission keys are emitted unchanged as permissions[] values.
--}}
@php
    $staffModel = $staff ?? null;
    $selected = collect(old('permissions', $selectedPermissions ?? []))->all();
@endphp

<div class="jp-field">
    <label class="jp-label" for="name">Full name</label>
    <input type="text" name="name" id="name" class="jp-input @error('name') is-invalid @enderror" value="{{ old('name', $staffModel?->name) }}" required maxlength="160">
    @error('name')<p class="jp-field__error">{{ $message }}</p>@enderror
</div>

<div class="jp-field">
    <label class="jp-label" for="email">Email</label>
    <input type="email" name="email" id="email" class="jp-input @error('email') is-invalid @enderror" value="{{ old('email', $staffModel?->email) }}" required maxlength="160">
    @error('email')<p class="jp-field__error">{{ $message }}</p>@enderror
</div>

<div class="jp-field">
    <label class="jp-label" for="phone">Phone</label>
    <input type="text" name="phone" id="phone" class="jp-input @error('phone') is-invalid @enderror" value="{{ old('phone', $staffModel?->meta['phone'] ?? '') }}" maxlength="40">
    @error('phone')<p class="jp-field__error">{{ $message }}</p>@enderror
</div>

<div class="jp-field">
    <label class="jp-label" for="password">{{ isset($staffModel) ? 'New password (optional)' : 'Password' }}</label>
    <input type="password" name="password" id="password" class="jp-input @error('password') is-invalid @enderror" {{ isset($staffModel) ? '' : 'required' }} minlength="8" autocomplete="new-password">
    @error('password')<p class="jp-field__error">{{ $message }}</p>@enderror
</div>

@if (isset($staffModel))
    <div class="jp-field">
        <label class="jp-label" for="status">Status</label>
        <select name="status" id="status" class="jp-select @error('status') is-invalid @enderror" required>
            @foreach (\App\Enums\UserAccountStatus::cases() as $statusCase)
                @if (in_array($statusCase->value, ['active', 'inactive', 'suspended'], true))
                    <option value="{{ $statusCase->value }}" @selected(old('status', $staffModel->status?->value) === $statusCase->value)>{{ ucfirst($statusCase->value) }}</option>
                @endif
            @endforeach
        </select>
        @error('status')<p class="jp-field__error">{{ $message }}</p>@enderror
    </div>
@endif

@if ($showPermissionFieldset ?? true)
    <fieldset class="jp-form__section">
        <legend class="jp-form__section-title">Permissions</legend>
        <p class="jp-field__help">Staff cannot create agents or access platform admin areas. <code>Manage staff</code> is off by default.</p>
        <div class="jp-permission-grid">
            @foreach ($permissionLabels as $key => $label)
                <label class="jp-permission-check">
                    <input type="checkbox" name="permissions[]" value="{{ $key }}" @checked(in_array($key, $selected, true))>
                    <span>{{ $label }}</span>
                </label>
            @endforeach
        </div>
    </fieldset>
@endif
