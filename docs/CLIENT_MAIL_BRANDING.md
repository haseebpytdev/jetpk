# Client-scoped mail branding

## Overview

Platform SMTP (ZeptoMail or other) may use **one shared transport**: host, username, password, and default `MAIL_FROM_ADDRESS`. That is acceptable for multi-client OTA deployments.

**Visible sender identity** (From Name, Reply-To, subject copy, body branding) must be **client-scoped per mailable** where the email is sent in a client context (e.g. JetPakistan OTP login).

Resolver: `App\Support\Branding\ClientMailBrandingResolver`.

## Rules

| Field | Platform default | Client override |
|-------|------------------|-----------------|
| SMTP host / port | `.env` | Shared |
| SMTP username / password | `.env` | Shared — **rotate in ZeptoMail if exposed** |
| From address | `MAIL_FROM_ADDRESS` | Usually shared |
| From **name** | `MAIL_FROM_NAME` (Parwaaz) | Per client via mailable envelope |
| Reply-To | Agency / platform settings | Per client when supported |
| Subject / body | Platform templates | Client name + assets |

Do **not** expose SMTP secrets in code, docs, or client-facing UI.

## JetPakistan OTP

- From name: **JetPakistan**
- Subject: `Your JetPakistan login OTP`
- Reply-To: `ticketingjp@jetpakistan.com` (or client branding email in preview)
- Body: JetPakistan only — no Parwaaz / YoursDomain copy

## Master / Parwaaz

Master and default platform emails continue to use `CompanyEmailProfileResolver` and global `MAIL_FROM_NAME` unless a future mailable explicitly opts into client branding.

## Implementation

`LoginOtpMail` calls `ClientMailBrandingResolver::resolve()` when `ClientLoginOtpGate` is active for JetPK (or preview client with OTP). Other mailables can reuse the same resolver for consistent client envelope fields.
