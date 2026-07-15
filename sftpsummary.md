# SFTP / Auth UI — working notes

Live app is deployed via SFTP (see `.cursor/rules/sftp-live-server-rules.mdc` and `.vscode/sftp.json`). Do not bulk-sync unless explicitly approved. After controlled uploads, run Laravel clears on the server.

## Main files (login / register)

| Area | Path |
|------|------|
| Login view | `resources/views/auth/login.blade.php` |
| Register view | `resources/views/auth/register.blade.php` |
| Auth shell layout | `resources/views/layouts/auth.blade.php` |
| Frontend layout (CSS/JS version query) | `resources/views/layouts/frontend.blade.php` |
| Registration validation | `app/Http/Requests/Auth/StoreCustomerRegistrationRequest.php` |
| Registration controller | `app/Http/Controllers/Auth/RegisteredUserController.php` |
| Client-side validation | `public/js/public-form-validation.js` |
| Auth/register styles | `public/css/ota-design-system.css` |

## Register layout (current)

- Auth card class: `auth-card--register-compact` (via `@section('auth_card_class', …)` in `register.blade.php`).
- Form pushed with `@push('auth_form')` into `layouts/auth.blade.php` → `@stack('auth_form')`.
- Compact markup classes: `ota-register-compact`, `ota-register-form`, `ota-register-grid`, `ota-register-grid--two`, `ota-register-field-full`, `ota-register-phone-row`, `ota-country-code-select`, `ota-mobile-number-input`.
- Card max-width ~560px; form fields forced full width inside card (scoped CSS in `ota-design-system.css`).

## Auth layout — support block

`layouts/auth.blade.php` does **not** render “Need Help? Contact…” when `auth_card_class` contains:

- `login-premium` (login), or
- `register-compact` (register).

Other auth pages may still show the support paragraph.

## CSS cache bust (required after public CSS upload)

Browsers cache `ota-design-system.css` by URL. After uploading any change under `public/css/ota-design-system.css`, bump the version query in:

`resources/views/layouts/frontend.blade.php`

```blade
<link rel="stylesheet" href="{{ asset('css/ota-design-system.css') }}?v=4" />
```

Increment `v=` on each design-system CSS deploy (e.g. `v=4` → `v=5`). Do not change other asset versions unless that file was also updated.

**Current design-system version:** `v=4`

## Typical upload targets

| Profile | Use for |
|---------|---------|
| **OTA App - Laravel** | `resources/views/**`, `app/**`, `routes/**`, `config/**` |
| **OTA Public - Live Web Root** | `public/css/**`, `public/js/**` (manual; app profile ignores `public/`) |

## Server SSH after upload

```bash
php artisan view:clear
php artisan cache:clear
```

- After Blade/views: `view:clear` (required).
- After routes: `route:clear` or `route:cache` if production uses route cache.
- Hard refresh in browser (Ctrl+F5) and confirm Network tab loads the new `?v=` for CSS.

## Changelog

| Date | Note |
|------|------|
| 2026-05-20 | Register compact layout + full-width field CSS; auth layout skips support on register; `ota-design-system.css` cache bust `v=3` → `v=4`. |
