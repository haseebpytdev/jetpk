# Customer Profile and Security Contract

## Profile read

- `GET /customer/profile?format=json`
- Fields: `name`, `email`, `username`, profile phone/address/passport fields supported by `ProfileUpdateRequest`
- `email_verified` from Laravel only

## Profile update

- `PATCH /profile` with session cookie + CSRF
- Email change clears `email_verified_at` (Laravel behavior)
- Validation errors returned as JSON 422

## Security / password

- `PUT /password` with `current_password`, `password`, `password_confirmation`
- Laravel `Password::defaults()` policy applies
- No password in URL or client storage

## Excluded

- No role or account-status editing from customer dashboard
- No supplier/agency fields
