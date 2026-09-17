# JP-OPS-02 OTP and Email Verification Contract

## OTP (login)

- Server-authoritative via `LoginOtpService`
- Gate: `ClientLoginOtpGate` — **configurable**, not hard-forced for JetPK
- Persisted admin authority: `LoginOtpSettingsService` → ClientProfile branding `config.auth.require_login_otp`
- Fallback: `OTA_CLIENT_REQUIRE_LOGIN_OTP` / `config('ota_client.auth.require_login_otp')`
- Admin UI: `/admin/settings/login-otp` (Settings hub → Login OTP)
- One gate applies to customer, agent, staff, and admin
- Dashboard Security MFA preview fields do **not** control login OTP
- Demo patch preserved: `config/ota_otp_demo.php`, `DemoFixedLoginOtpGate`
- OTP values never returned in JSON or UI
- Production provider contract: `App\Contracts\Auth\LoginOtpChannelProvider` (readiness only; live channel external)

### OTP OFF contract

- Valid credentials → authenticate → session regenerate → authorized redirect
- No OTP record / pending challenge
- No OTP email
- No OTP screen redirect

### OTP ON contract

- Valid credentials → OTP challenge → one email → OTP screen → verify → portal redirect

### Demo flags (unchanged)

- `OTP_DEMO_FIXED_ENABLED`
- `OTP_DEMO_FIXED_CODE`
- `OTP_DEMO_ALLOWED_EMAILS`
- `OTP_DEMO_ALLOW_DEVCP`
- `OTP_DEMO_ALLOW_PRODUCTION`

## Email verification

- Signed URL: `GET /verify-email/{id}/{hash}`
- Customer portal gate: `customer.email.portal.verified` middleware
- Resend throttled: `POST /email/verification-notification`
- Generic responses; no account enumeration

## Password recovery

- Generic forgot-password message regardless of account existence
- Reset token one-time with expiry
- No token leakage in logs
