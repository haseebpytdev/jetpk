# JP-OPS-02 OTP and Email Verification Contract

## OTP (login)

- Server-authoritative via `LoginOtpService`
- JetPK always requires login OTP (`ClientLoginOtpGate`)
- Demo patch preserved: `config/ota_otp_demo.php`, `DemoFixedLoginOtpGate`
- OTP values never returned in JSON or UI
- Production provider contract: `App\Contracts\Auth\LoginOtpChannelProvider` (readiness only; live channel external)

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
