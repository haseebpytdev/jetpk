# Auth — Login, OTP, Recovery, and Session Visual Contract (JP-UI-05)

## Scope

Visual contract for mockups **#6, #7** (login/signup family) covering login, OTP, forgot password, reset password, and session-expired states. **OTP business logic is unchanged** — this contract governs shell chrome and presentation only.

## Module

`frontend/features/auth/` — canonical owner of auth layout and form chrome.

## Component hierarchy

```
AuthPageShell (data-testid="auth-page-shell")
├── AuthIllustrationPanel
│   ├── ImageSlot → /images/auth/auth-illustration.svg
│   ├── AuthBrandHeader (eyebrow, headline, highlight, description)
│   └── AuthBenefits (LOGIN_BENEFITS | SIGNUP_BENEFITS)
└── AuthFormPanel
    └── AuthFormCard (data-testid="auth-form-card")
        ├── LoginSessionNotice (?reason=session-expired)
        ├── LoginForm | OtpForm | ForgotPasswordForm | ResetPasswordForm
        └── AuthFooterLinks
```

## Split-screen layout

| Breakpoint | Layout |
|------------|--------|
| `lg+` (≥1024px) | Two columns: illustration left (~48%), form right (~52%) |
| `< lg` | Form column first (`order-1`); illustration below (`order-2`) |

- Grid: `lg:grid-cols-[minmax(0,1fr)_minmax(0,1.05fr)]`
- Vertical padding: `py-jp-lg sm:py-jp-xl` inside `PageContainer`
- Illustration and benefits hidden on narrow viewports below `md` for OTP-only focus when appropriate

## Login (`/login`)

### Form fields

| Field | Label | Notes |
|-------|-------|-------|
| Email or username | `Email or username` | Single identifier field |
| Password | `Password` | `PasswordField` with show/hide toggle |

### States

| State | Trigger | Visual |
|-------|---------|--------|
| Default | — | Empty form, primary CTA "Sign in" |
| Password visible | Show password toggle | Plaintext password field |
| Validation errors | Submit empty | Inline field errors + summary alert |
| Invalid credentials | Wrong password fixture | `role="alert"` error message |
| Loading | Slow login fixture | Button text "Signing in…", disabled |
| Rate limited | Rate-limit fixture | Alert with retry guidance |
| Session expired | `?reason=session-expired` | `LoginSessionNotice` informational banner |
| Already authenticated | Valid session | Redirect to customer dashboard shell |

### Social login

- OAuth buttons (`oauth-google`, `oauth-apple`, `oauth-facebook`) and `social-login-row` render **only** when Laravel exposes configured providers.
- When unconfigured: row must be absent (visual audit `forbiddenTestIds` gate).

## OTP (`/login/otp`)

- Uses same `AuthPageShell` / `AuthFormCard` chrome as login.
- **Logic unchanged** from JP-FE-04: verification code input, verify CTA, resend with cooldown.
- States: default, invalid code, expired code, rate limited, resend cooldown.

## Recovery (`/forgot-password`, `/reset-password/[token]`)

### Forgot password

| State | Visual |
|-------|--------|
| Initial | Email field + "Send reset link" CTA |
| Success | Generic success status (no account enumeration) |

### Reset password

| State | Visual |
|-------|--------|
| Valid token | New password + confirm fields |
| Invalid/expired token | Error state with recovery link |
| Success | Status confirmation + link to login |

## Session expired

- Query param: `?reason=session-expired`
- Component: `LoginSessionNotice` — renders only when `reason === "session-expired"`
- Tone: informational (not error); directs user to sign in again
- Must not clear or override other login error states

## Benefits panel

`LOGIN_BENEFITS` (login) — four items: manage bookings, exclusive deals, faster checkout, secure and trusted.

Icons: `ticket`, `tag`, `clock`, `shield` — mapped in `AuthBenefits`.

## Typography and tokens

- Headlines: `text-jp-2xl` / `font-semibold` with optional highlight in brand accent
- Form card: `rounded-jp-lg border border-jp-border bg-jp-surface shadow-jp-sm`
- Focus: `focus-visible:shadow-jp-focus` on inputs and buttons
- Light and dark: all surfaces use `jp-*` semantic tokens

## Test IDs (visual audit)

| testId | Element |
|--------|---------|
| `auth-page-shell` | Root split-screen grid |
| `auth-form-card` | Form card container |

## Forbidden elements (when unconfigured)

- `oauth-google`, `oauth-apple`, `oauth-facebook`, `social-login-row`

## Related scenarios

Login family (20): layout matrix (12) + password-visible, invalid-credentials, validation-errors, loading, session-expired, rate-limited, already-authenticated, social-hidden.

Recovery family (12): OTP desktop/mobile light/dark, OTP error states, forgot/reset flows.
