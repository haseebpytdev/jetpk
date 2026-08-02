# JP-OPS-02 Production Runtime Requirements

Document only — no server configuration changed in this phase.

## Session cookies

- `SESSION_DRIVER=database` (or redis in production)
- `SESSION_SECURE_COOKIE=true` (HTTPS)
- `SESSION_SAME_SITE=lax`
- `SESSION_DOMAIN` aligned with Laravel + Next same-site proxy

## Trusted proxies

- Configure `TrustProxies` for reverse proxy (Nginx) so HTTPS and host are correct

## Next.js proxy

- `/laravel/:path*` → Laravel upstream
- Production Nginx may also proxy `/laravel/*` directly

## CSRF

- Web middleware CSRF on all mutating routes except payment callback exception
- `XSRF-TOKEN` cookie readable by JS for SPA bridge

## OTP production channel

- `LoginOtpChannelProvider` implementation required for live SMS/email
- Demo patch remains until separately authorized for removal
- `OTP_DEMO_*` flags must not be disabled without authorization

## Cache

- Session and CSRF JSON responses: `Cache-Control: no-store, private`

## Mail

- Production mail driver required for OTP email delivery (external runtime readiness)
