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

## Public CMS fixture authority (JP-FULLSTACK-01G)

- `NODE_ENV=production` must **always** deny CMS/public-content fixture substitution regardless of any other env flag
- `NEXT_PUBLIC_ALLOW_CONTENT_FIXTURES=true` is permitted **only** in non-production preview/development builds
- `OTA_ALLOW_SESSION_FIXTURE` controls auth/session Playwright fixtures only — it must **not** grant CMS fixture authority
- Production CMS content is authoritative from Laravel `api.public.content.*` only; empty/unavailable CMS must render honest empty or not-found states, never demo marketing copy

## Cache

- Session and CSRF JSON responses: `Cache-Control: no-store, private`

## Mail

- Production mail driver required for OTP email delivery (external runtime readiness)
